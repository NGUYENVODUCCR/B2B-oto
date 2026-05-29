<?php

class WalletRepository
{
    private $companiesTable;
    private $paymentsTable;
    private $columnExistsCache = [];

    public function __construct()
    {
        global $wpdb;

        $prefix = $wpdb->prefix . 'b2b_';
        $this->companiesTable = $prefix . 'companies';
        $this->paymentsTable = $prefix . 'payments';
    }

    public function balance($companyId)
    {
        $companyId = (int) $companyId;

        if ($companyId <= 0 || !$this->walletColumnsReady()) {
            return 0.0;
        }

        $row = $this->companyWalletRow($companyId);
        return round((float) ($row['wallet_balance'] ?? 0), 2);
    }

    public function credit($companyId, $amount, $type, $note = '', $reference = [])
    {
        $amount = round((float) $amount, 2);

        if ($amount <= 0) {
            throw new Exception('Wallet amount must be greater than zero');
        }

        $this->assertWalletColumnsReady();
        $state = $this->walletState((int) $companyId);
        $balance = round((float) ($state['balance'] ?? 0) + $amount, 2);

        $this->saveWalletState((int) $companyId, [
            'wallet_balance' => $balance,
            'wallet_transactions_json' => $this->encodeJson(
                $this->appendTransaction(
                    $state['transactions'],
                    (int) $companyId,
                    $type,
                    $amount,
                    $balance,
                    $note,
                    $reference
                )
            ),
        ]);

        return $balance;
    }

    public function debit($companyId, $amount, $type, $note = '', $reference = [])
    {
        $amount = round((float) $amount, 2);

        if ($amount <= 0) {
            throw new Exception('Wallet amount must be greater than zero');
        }

        $this->assertWalletColumnsReady();
        $state = $this->walletState((int) $companyId);
        $current = round((float) ($state['balance'] ?? 0), 2);

        if ($current < $amount) {
            throw new Exception('INSUFFICIENT_BALANCE: Số dư không đủ để thực hiện giao dịch');
        }

        $balance = round($current - $amount, 2);

        $this->saveWalletState((int) $companyId, [
            'wallet_balance' => $balance,
            'wallet_transactions_json' => $this->encodeJson(
                $this->appendTransaction(
                    $state['transactions'],
                    (int) $companyId,
                    $type,
                    -$amount,
                    $balance,
                    $note,
                    $reference
                )
            ),
        ]);

        return $balance;
    }

    public function transactions($companyId, $limit = 50)
    {
        $companyId = (int) $companyId;
        $limit = max(1, (int) $limit);

        if ($companyId <= 0 || !$this->walletColumnsReady()) {
            return [];
        }

        $state = $this->walletState($companyId);
        $rows = is_array($state['transactions']) ? $state['transactions'] : [];

        return array_slice($rows, 0, $limit);
    }

    public function createDepositRequest($data)
    {
        $this->assertWalletColumnsReady();

        $code = strtoupper((string) ($data['code'] ?? ''));
        $companyId = (int) ($data['company_id'] ?? 0);

        if ($code === '' || $companyId <= 0) {
            throw new Exception('Invalid deposit request');
        }

        $request = $this->normalizeDepositRequest($data);
        $state = $this->walletState($companyId);
        $map = $state['deposit_requests'];
        $map[$code] = $request;

        $this->saveWalletState($companyId, [
            'wallet_deposit_requests_json' => $this->encodeJson($map),
        ]);

        return $request;
    }

    public function depositRequest($code)
    {
        $code = strtoupper((string) $code);

        if ($code === '' || !$this->walletColumnsReady()) {
            return null;
        }

        $found = $this->findCompanyDepositRequest($code);

        if (!$found) {
            return null;
        }

        return $found['request'];
    }

    public function updateDepositRequest($code, $data)
    {
        $this->assertWalletColumnsReady();

        $code = strtoupper((string) $code);
        $found = $this->findCompanyDepositRequest($code);

        if (!$found) {
            throw new Exception('Deposit request not found');
        }

        $companyId = (int) ($found['company_id'] ?? 0);
        $map = $found['requests_map'];
        $current = $map[$code] ?? null;

        if (!is_array($current)) {
            throw new Exception('Deposit request not found');
        }

        $next = array_merge($current, is_array($data) ? $data : []);
        $next = $this->normalizeDepositRequest($next);
        $map[$code] = $next;

        $this->saveWalletState($companyId, [
            'wallet_deposit_requests_json' => $this->encodeJson($map),
        ]);

        return $next;
    }

    public function depositRequests($companyId, $limit = 10)
    {
        $companyId = (int) $companyId;
        $limit = max(1, (int) $limit);

        if ($companyId <= 0 || !$this->walletColumnsReady()) {
            return [];
        }

        $state = $this->walletState($companyId);
        $map = is_array($state['deposit_requests']) ? $state['deposit_requests'] : [];
        $rows = array_values($map);

        usort($rows, function ($a, $b) {
            return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
        });

        return array_slice($rows, 0, $limit);
    }

    public function externalTransaction($provider, $externalId)
    {
        $provider = strtolower(trim((string) $provider));
        $externalId = trim((string) $externalId);

        if ($provider === '' || $externalId === '' || !$this->walletColumnsReady()) {
            return null;
        }

        $key = $this->externalKey($provider, $externalId);
        $rows = $this->candidateCompanyRowsForSearch($externalId, 'wallet_external_transactions_json');

        foreach ($rows as $row) {
            $map = $this->decodeJson((string) ($row['wallet_external_transactions_json'] ?? ''), []);

            if (isset($map[$key]) && is_array($map[$key])) {
                return $map[$key];
            }
        }

        return null;
    }

    public function saveExternalTransaction($provider, $externalId, $data)
    {
        $this->assertWalletColumnsReady();

        $provider = strtolower(trim((string) $provider));
        $externalId = trim((string) $externalId);

        if ($provider === '' || $externalId === '') {
            throw new Exception('Invalid external transaction');
        }

        $companyId = (int) ($data['company_id'] ?? 0);
        $depositCode = strtoupper(trim((string) ($data['deposit_code'] ?? '')));

        if ($companyId <= 0 && $depositCode !== '') {
            $request = $this->depositRequest($depositCode);
            $companyId = (int) ($request['company_id'] ?? 0);
        }

        if ($companyId <= 0) {
            $companyId = $this->carrierCompanyId();
        }

        $payload = [
            'provider' => $provider,
            'external_id' => $externalId,
            'status' => (string) ($data['status'] ?? ''),
            'deposit_code' => $depositCode !== '' ? $depositCode : null,
            'company_id' => $companyId > 0 ? $companyId : null,
            'amount' => round((float) ($data['amount'] ?? 0), 2),
            'raw' => is_array($data['raw'] ?? null) ? $data['raw'] : [],
            'meta' => is_array($data['meta'] ?? null) ? $data['meta'] : [],
            'processed_at' => current_time('mysql'),
        ];

        if ($companyId <= 0) {
            return $payload;
        }

        $state = $this->walletState($companyId);
        $map = is_array($state['external_transactions']) ? $state['external_transactions'] : [];
        $map[$this->externalKey($provider, $externalId)] = $payload;
        $changes = [
            'wallet_external_transactions_json' => $this->encodeJson($map),
        ];

        if (($payload['status'] ?? '') === 'unmatched') {
            $unmatched = is_array($state['unmatched_external']) ? $state['unmatched_external'] : [];
            array_unshift($unmatched, [
                'provider' => $provider,
                'external_id' => $externalId,
                'data' => $payload['raw'],
                'created_at' => current_time('mysql'),
            ]);
            $changes['wallet_unmatched_external_json'] = $this->encodeJson(array_slice($unmatched, 0, 100));
        }

        $this->saveWalletState($companyId, $changes);

        return $payload;
    }

    public function saveUnmatchedExternalTransaction($provider, $externalId, $data)
    {
        $provider = strtolower(trim((string) $provider));
        $externalId = trim((string) $externalId);

        if ($externalId === '') {
            $externalId = 'UNMATCHED_' . strtoupper(substr(md5(uniqid('', true)), 0, 16));
        }

        $existing = $this->externalTransaction($provider, $externalId);

        if ($existing) {
            return;
        }

        $this->saveExternalTransaction($provider, $externalId, [
            'status' => 'unmatched',
            'raw' => is_array($data) ? $data : [],
        ]);
    }

    public function settlement($paymentId)
    {
        global $wpdb;

        $paymentId = (int) $paymentId;

        if ($paymentId <= 0 || !$this->settlementColumnsReady()) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "
                SELECT id, settlement_order_id, settlement_buyer_company_id, settlement_seller_company_id,
                       settlement_payment_amount, settlement_release_amount, settlement_refund_amount,
                       settlement_reason, settlement_meta, settlement_settled_at
                FROM {$this->paymentsTable}
                WHERE id = %d
                LIMIT 1
                ",
                $paymentId
            ),
            ARRAY_A
        );

        if (!is_array($row) || empty($row['settlement_settled_at'])) {
            return null;
        }

        $payload = [
            'payment_id' => (int) ($row['id'] ?? $paymentId),
            'order_id' => (int) ($row['settlement_order_id'] ?? 0),
            'buyer_company_id' => (int) ($row['settlement_buyer_company_id'] ?? 0),
            'seller_company_id' => (int) ($row['settlement_seller_company_id'] ?? 0),
            'payment_amount' => (float) ($row['settlement_payment_amount'] ?? 0),
            'release_amount' => (float) ($row['settlement_release_amount'] ?? 0),
            'refund_amount' => (float) ($row['settlement_refund_amount'] ?? 0),
            'reason' => (string) ($row['settlement_reason'] ?? ''),
            'settled_at' => $row['settlement_settled_at'] ?? null,
        ];

        $meta = $this->decodeJson((string) ($row['settlement_meta'] ?? ''), []);

        if (is_array($meta)) {
            $payload = array_merge($meta, $payload);
        }

        return $payload;
    }

    public function saveSettlement($paymentId, $data)
    {
        global $wpdb;

        if (!$this->settlementColumnsReady()) {
            throw new Exception('Missing settlement columns in wp_b2b_payments');
        }

        $paymentId = (int) $paymentId;
        $payload = is_array($data) ? $data : [];
        $payload['payment_id'] = $paymentId;
        $payload['settled_at'] = current_time('mysql');

        $result = $wpdb->update(
            $this->paymentsTable,
            [
                'settlement_order_id' => (int) ($payload['order_id'] ?? 0),
                'settlement_buyer_company_id' => (int) ($payload['buyer_company_id'] ?? 0),
                'settlement_seller_company_id' => (int) ($payload['seller_company_id'] ?? 0),
                'settlement_payment_amount' => round((float) ($payload['payment_amount'] ?? 0), 2),
                'settlement_release_amount' => round((float) ($payload['release_amount'] ?? 0), 2),
                'settlement_refund_amount' => round((float) ($payload['refund_amount'] ?? 0), 2),
                'settlement_reason' => (string) ($payload['reason'] ?? ''),
                'settlement_meta' => $this->encodeJson($payload),
                'settlement_settled_at' => (string) ($payload['settled_at'] ?? current_time('mysql')),
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $paymentId]
        );

        if ($result === false) {
            throw new Exception($wpdb->last_error ?: 'Cannot save settlement');
        }

        return $this->settlement($paymentId) ?: $payload;
    }

    private function assertWalletColumnsReady()
    {
        if (!$this->walletColumnsReady()) {
            throw new Exception('Missing wallet columns in wp_b2b_companies');
        }
    }

    private function walletColumnsReady()
    {
        return $this->columnExists($this->companiesTable, 'wallet_balance')
            && $this->columnExists($this->companiesTable, 'wallet_transactions_json')
            && $this->columnExists($this->companiesTable, 'wallet_deposit_requests_json')
            && $this->columnExists($this->companiesTable, 'wallet_external_transactions_json')
            && $this->columnExists($this->companiesTable, 'wallet_unmatched_external_json');
    }

    private function settlementColumnsReady()
    {
        return $this->columnExists($this->paymentsTable, 'settlement_settled_at')
            && $this->columnExists($this->paymentsTable, 'settlement_meta');
    }

    private function walletState($companyId)
    {
        $row = $this->companyWalletRow((int) $companyId);

        return [
            'balance' => round((float) ($row['wallet_balance'] ?? 0), 2),
            'transactions' => $this->decodeJson((string) ($row['wallet_transactions_json'] ?? ''), []),
            'deposit_requests' => $this->decodeDepositRequests((string) ($row['wallet_deposit_requests_json'] ?? '')),
            'external_transactions' => $this->decodeJson((string) ($row['wallet_external_transactions_json'] ?? ''), []),
            'unmatched_external' => $this->decodeJson((string) ($row['wallet_unmatched_external_json'] ?? ''), []),
        ];
    }

    private function companyWalletRow($companyId)
    {
        global $wpdb;

        $companyId = (int) $companyId;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "
                SELECT id, wallet_balance, wallet_transactions_json, wallet_deposit_requests_json,
                       wallet_external_transactions_json, wallet_unmatched_external_json
                FROM {$this->companiesTable}
                WHERE id = %d
                LIMIT 1
                ",
                $companyId
            ),
            ARRAY_A
        );

        if (!is_array($row)) {
            throw new Exception('Company not found');
        }

        return $row;
    }

    private function saveWalletState($companyId, $changes)
    {
        global $wpdb;

        $payload = is_array($changes) ? $changes : [];
        $payload['wallet_updated_at'] = current_time('mysql');
        $payload['updated_at'] = current_time('mysql');

        $result = $wpdb->update(
            $this->companiesTable,
            $payload,
            ['id' => (int) $companyId]
        );

        if ($result === false) {
            throw new Exception($wpdb->last_error ?: 'Cannot save wallet data');
        }
    }

    private function appendTransaction($rows, $companyId, $type, $amount, $balanceAfter, $note, $reference)
    {
        $rows = is_array($rows) ? $rows : [];

        array_unshift($rows, [
            'id' => uniqid('txn_', true),
            'company_id' => (int) $companyId,
            'type' => (string) $type,
            'amount' => round((float) $amount, 2),
            'balance_after' => round((float) $balanceAfter, 2),
            'note' => (string) $note,
            'reference' => is_array($reference) ? $reference : [],
            'created_at' => current_time('mysql')
        ]);

        return array_slice($rows, 0, 100);
    }

    private function normalizeDepositRequest($data)
    {
        $code = strtoupper((string) ($data['code'] ?? ($data['id'] ?? '')));
        $companyId = (int) ($data['company_id'] ?? 0);

        if ($code === '' || $companyId <= 0) {
            throw new Exception('Invalid deposit request');
        }

        return [
            'id' => $code,
            'code' => $code,
            'company_id' => $companyId,
            'status' => (string) ($data['status'] ?? 'pending'),
            'requested_amount' => round((float) ($data['requested_amount'] ?? 0), 2),
            'paid_amount' => round((float) ($data['paid_amount'] ?? 0), 2),
            'balance_after' => round((float) ($data['balance_after'] ?? 0), 2),
            'bank_name' => (string) ($data['bank_name'] ?? ''),
            'bank_bin' => (string) ($data['bank_bin'] ?? ''),
            'bank_account_number' => (string) ($data['bank_account_number'] ?? ''),
            'bank_account_name' => (string) ($data['bank_account_name'] ?? ''),
            'transfer_content' => (string) ($data['transfer_content'] ?? $code),
            'vietqr_url' => (string) ($data['vietqr_url'] ?? ''),
            'external_transaction_id' => (string) ($data['external_transaction_id'] ?? ''),
            'bank_transaction_id' => (string) ($data['bank_transaction_id'] ?? ''),
            'meta_json' => is_array($data['meta_json'] ?? null) ? $data['meta_json'] : null,
            'created_at' => (string) ($data['created_at'] ?? current_time('mysql')),
            'expires_at' => $data['expires_at'] ?? null,
            'paid_at' => $data['paid_at'] ?? null,
            'completed_at' => $data['completed_at'] ?? null,
            'updated_at' => $data['updated_at'] ?? null,
        ];
    }

    private function decodeDepositRequests($payload)
    {
        $decoded = $this->decodeJson((string) $payload, []);

        if (!is_array($decoded)) {
            return [];
        }

        if ($this->isAssocArray($decoded)) {
            return $decoded;
        }

        $map = [];

        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }

            $code = strtoupper((string) ($row['code'] ?? $row['id'] ?? ''));

            if ($code === '') {
                continue;
            }

            $map[$code] = $row;
        }

        return $map;
    }

    private function findCompanyDepositRequest($code)
    {
        global $wpdb;

        $code = strtoupper((string) $code);

        if ($code === '') {
            return null;
        }

        $like = '%' . $wpdb->esc_like($code) . '%';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT id, wallet_deposit_requests_json
                FROM {$this->companiesTable}
                WHERE wallet_deposit_requests_json IS NOT NULL
                AND wallet_deposit_requests_json <> ''
                AND wallet_deposit_requests_json LIKE %s
                ",
                $like
            ),
            ARRAY_A
        );

        foreach ((array) $rows as $row) {
            $companyId = (int) ($row['id'] ?? 0);
            $map = $this->decodeDepositRequests((string) ($row['wallet_deposit_requests_json'] ?? ''));

            if (isset($map[$code]) && is_array($map[$code])) {
                return [
                    'company_id' => $companyId,
                    'request' => $map[$code],
                    'requests_map' => $map,
                ];
            }
        }

        return null;
    }

    private function candidateCompanyRowsForSearch($needle, $column)
    {
        global $wpdb;

        $needle = trim((string) $needle);
        $column = (string) $column;

        if ($needle === '') {
            return [];
        }

        $like = '%' . $wpdb->esc_like($needle) . '%';

        return $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT id, {$column}
                FROM {$this->companiesTable}
                WHERE {$column} IS NOT NULL
                AND {$column} <> ''
                AND {$column} LIKE %s
                ",
                $like
            ),
            ARRAY_A
        );
    }

    private function carrierCompanyId()
    {
        global $wpdb;

        $companyId = (int) $wpdb->get_var("SELECT id FROM {$this->companiesTable} ORDER BY id ASC LIMIT 1");
        return $companyId > 0 ? $companyId : 0;
    }

    private function externalKey($provider, $externalId)
    {
        return strtolower(trim((string) $provider)) . ':' . trim((string) $externalId);
    }

    private function columnExists($table, $column)
    {
        global $wpdb;

        $key = $table . ':' . $column;

        if (array_key_exists($key, $this->columnExistsCache)) {
            return $this->columnExistsCache[$key];
        }

        $found = $wpdb->get_var(
            $wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column)
        );

        $this->columnExistsCache[$key] = !empty($found);
        return $this->columnExistsCache[$key];
    }

    private function encodeJson($data)
    {
        if ($data === null || $data === '') {
            return null;
        }

        if (is_string($data)) {
            return $data;
        }

        if (function_exists('wp_json_encode')) {
            $json = wp_json_encode($data);
        } else {
            $json = json_encode($data);
        }

        return $json === false ? null : $json;
    }

    private function decodeJson($json, $default)
    {
        $json = trim((string) $json);

        if ($json === '') {
            return $default;
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : $default;
    }

    private function isAssocArray($value)
    {
        if (!is_array($value) || empty($value)) {
            return false;
        }

        return array_keys($value) !== range(0, count($value) - 1);
    }
}
