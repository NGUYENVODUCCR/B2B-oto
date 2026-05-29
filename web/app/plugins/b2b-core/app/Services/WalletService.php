<?php

class WalletService
{
    private $repository;

    public function __construct()
    {
        $this->repository = new WalletRepository();
    }

    public function summaryForViewer($companyId, array $roles = [])
    {
        $companyId = (int) $companyId;

        if ($companyId <= 0) {
            if (PermissionHelper::hasAdminOrSupportRole($roles)) {
                return [
                    'company_id' => 0,
                    'balance' => 0,
                    'transactions' => [],
                    'deposit_requests' => [],
                    'readonly' => true,
                ];
            }

            throw new Exception('Tài khoản chưa gắn công ty');
        }

        return $this->summary($companyId);
    }

    public function summary($companyId)
    {
        $companyId = $this->companyId($companyId);

        return [
            'company_id' => $companyId,
            'balance' => $this->repository->balance($companyId),
            'transactions' => $this->repository->transactions($companyId),
            'deposit_requests' => $this->repository->depositRequests($companyId)
        ];
    }

    public function deposit($companyId, $data)
    {
        $companyId = $this->companyId($companyId);
        $amount = $this->amount($data['amount'] ?? 0);

        $bankConfig = $this->bankConfig();
        $code = $this->depositCode();
        $createdAt = current_time('mysql');

        return $this->repository->createDepositRequest([
            'id' => $code,
            'code' => $code,
            'company_id' => $companyId,
            'status' => 'pending',
            'requested_amount' => $amount,
            'paid_amount' => 0,
            'bank_name' => $bankConfig['name'],
            'bank_bin' => $bankConfig['bin'],
            'bank_account_number' => $bankConfig['account_number'],
            'bank_account_name' => $bankConfig['account_name'],
            'transfer_content' => $code,
            'vietqr_url' => $this->vietQrUrl($bankConfig, $amount, $code),
            'created_at' => $createdAt,
            'expires_at' => date('Y-m-d H:i:s', strtotime($createdAt . ' +1 day'))
        ]);

        $bank = $this->text($data['bank'] ?? 'manual');

        $balance = $this->repository->credit(
            $companyId,
            $amount,
            'deposit',
            'Nạp tiền qua ' . strtoupper($bank),
            [
                'bank' => $bank
            ]
        );

        return $this->withBalance($companyId, $balance);
    }

    public function withdraw($companyId, $data)
    {
        $companyId = $this->companyId($companyId);
        $amount = $this->amount($data['amount'] ?? 0);
        $bank = $this->text($data['bank'] ?? 'manual');
        $account = $this->text($data['account_number'] ?? '');

        $balance = $this->repository->debit(
            $companyId,
            $amount,
            'withdraw',
            'Rút tiền về ' . strtoupper($bank),
            [
                'bank' => $bank,
                'account_number' => $account
            ]
        );

        return $this->withBalance($companyId, $balance);
    }

    public function chargeEscrow($buyerCompanyId, $amount, $paymentId, $orderId)
    {
        $buyerCompanyId = $this->companyId($buyerCompanyId);
        $amount = $this->amount($amount);

        return $this->repository->debit(
            $buyerCompanyId,
            $amount,
            'escrow_hold',
            'Thanh toán escrow cho order #' . (int) $orderId,
            [
                'payment_id' => (int) $paymentId,
                'order_id' => (int) $orderId
            ]
        );
    }

    public function releaseEscrow($payment, $order, $releaseAmount, $refundAmount = 0, $reason = 'release', array $settlementMeta = [])
    {
        $existing = $this->repository->settlement($payment->id);

        if ($existing) {
            return $existing;
        }

        $releaseAmount = round((float) $releaseAmount, 2);
        $refundAmount = round((float) $refundAmount, 2);
        $total = round((float) $payment->amount, 2);

        if ($releaseAmount < 0 || $refundAmount < 0) {
            throw new Exception('Settlement amount cannot be negative');
        }

        if (round($releaseAmount + $refundAmount, 2) > $total) {
            throw new Exception('Settlement amount exceeds payment amount');
        }

        if ($releaseAmount > 0) {
            $this->repository->credit(
                $order->seller_company_id,
                $releaseAmount,
                'escrow_release',
                'Giải ngân order #' . (int) $order->id,
                [
                    'payment_id' => (int) $payment->id,
                    'order_id' => (int) $order->id,
                    'reason' => $reason
                ]
            );
        }

        if ($refundAmount > 0) {
            $this->repository->credit(
                $order->buyer_company_id,
                $refundAmount,
                'refund',
                'Hoàn tiền order #' . (int) $order->id,
                [
                    'payment_id' => (int) $payment->id,
                    'order_id' => (int) $order->id,
                    'reason' => $reason
                ]
            );
        }

        return $this->repository->saveSettlement($payment->id, array_merge([
            'order_id' => (int) $order->id,
            'buyer_company_id' => (int) $order->buyer_company_id,
            'seller_company_id' => (int) $order->seller_company_id,
            'payment_amount' => $total,
            'release_amount' => $releaseAmount,
            'refund_amount' => $refundAmount,
            'reason' => $reason
        ], $settlementMeta));
    }

    public function settlement($paymentId)
    {
        return $this->repository->settlement($paymentId);
    }

    public function handleCassoWebhook($request)
    {
        $payload = method_exists($request, 'get_json_params')
            ? $request->get_json_params()
            : (array) $request;

        $this->assertCassoToken($request, $payload);

        $transactions = $payload['data'] ?? [];

        if (isset($transactions['id'])) {
            $transactions = [$transactions];
        }

        if (!is_array($transactions)) {
            throw new Exception('Invalid Casso payload');
        }

        $result = [
            'processed' => 0,
            'skipped' => 0,
            'unmatched' => 0
        ];

        foreach ($transactions as $transaction) {
            if (!is_array($transaction)) {
                $result['skipped']++;
                continue;
            }

            $status = $this->processCassoTransaction($transaction);
            $result[$status] = ($result[$status] ?? 0) + 1;
        }

        return $result;
    }

    private function withBalance($companyId, $balance)
    {
        return [
            'company_id' => (int) $companyId,
            'balance' => (float) $balance,
            'transactions' => $this->repository->transactions($companyId),
            'deposit_requests' => $this->repository->depositRequests($companyId)
        ];
    }

    private function companyId($companyId)
    {
        $companyId = (int) $companyId;

        if ($companyId <= 0) {
            throw new Exception('Missing company_id');
        }

        return $companyId;
    }

    private function amount($amount)
    {
        $amount = round((float) $amount, 2);

        if ($amount <= 0) {
            throw new Exception('Amount must be greater than zero');
        }

        return $amount;
    }

    private function text($value)
    {
        return function_exists('sanitize_text_field')
            ? sanitize_text_field((string) $value)
            : trim((string) $value);
    }

    private function processCassoTransaction($transaction)
    {
        $externalId = $this->text($transaction['id'] ?? ($transaction['tid'] ?? ($transaction['reference'] ?? '')));

        if ($externalId === '') {
            return 'skipped';
        }

        if ($this->repository->externalTransaction('casso', $externalId)) {
            return 'skipped';
        }

        $amount = round((float) ($transaction['amount'] ?? 0), 2);

        if ($amount <= 0) {
            $this->repository->saveExternalTransaction('casso', $externalId, [
                'status' => 'ignored_non_income',
                'raw' => $transaction
            ]);

            return 'skipped';
        }

        $code = $this->extractDepositCode($transaction['description'] ?? '');

        if (!$code) {
            $this->repository->saveUnmatchedExternalTransaction('casso', $externalId, $transaction);

            return 'unmatched';
        }

        $request = $this->repository->depositRequest($code);

        if (!$request) {
            $this->repository->saveUnmatchedExternalTransaction('casso', $externalId, $transaction);

            return 'unmatched';
        }

        if (($request['status'] ?? '') === 'paid') {
            $this->repository->saveExternalTransaction('casso', $externalId, [
                'status' => 'ignored_request_already_paid',
                'deposit_code' => $code,
                'raw' => $transaction
            ]);

            return 'skipped';
        }

        $bankTransactionId = $transaction['tid'] ?? ($transaction['reference'] ?? '');
        $bankSubAccount = $transaction['bank_sub_acc_id'] ?? ($transaction['subAccId'] ?? ($transaction['accountNumber'] ?? ''));
        $paidAt = $transaction['when'] ?? ($transaction['transactionDateTime'] ?? current_time('mysql'));

        $companyId = $this->companyId($request['company_id'] ?? 0);
        $balance = $this->repository->credit(
            $companyId,
            $amount,
            'deposit',
            'Nap tien Casso ' . $code,
            [
                'provider' => 'casso',
                'deposit_code' => $code,
                'external_transaction_id' => $externalId,
                'bank_transaction_id' => $bankTransactionId,
                'bank_sub_account' => $bankSubAccount
            ]
        );

        $this->repository->updateDepositRequest($code, [
            'status' => 'paid',
            'paid_amount' => $amount,
            'balance_after' => $balance,
            'external_transaction_id' => $externalId,
            'bank_transaction_id' => $bankTransactionId,
            'paid_at' => $paidAt,
            'completed_at' => current_time('mysql')
        ]);

        $this->repository->saveExternalTransaction('casso', $externalId, [
            'status' => 'processed',
            'deposit_code' => $code,
            'company_id' => $companyId,
            'amount' => $amount,
            'raw' => $transaction
        ]);

        return 'processed';
    }

    private function assertCassoToken($request, $payload = [])
    {
        $expected = $this->cassoWebhookToken();

        if ($expected === '') {
            throw new Exception('Casso webhook token is not configured');
        }

        $actual = $this->requestHeader($request, 'secure-token');

        if ($actual !== '' && hash_equals($expected, (string) $actual)) {
            return;
        }

        $signature = $this->requestHeader($request, 'x-casso-signature');

        if ($signature !== '' && $this->validCassoSignature($signature, $payload, $expected)) {
            return;
        }

        throw new Exception('Invalid Casso webhook token');
    }

    private function requestHeader($request, $name)
    {
        $value = method_exists($request, 'get_header')
            ? (string) $request->get_header($name)
            : '';

        if ($value !== '') {
            return $value;
        }

        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        return (string) ($_SERVER[$serverKey] ?? '');
    }

    private function validCassoSignature($signatureHeader, $payload, $secret)
    {
        if (!preg_match('/t=(\d+),v1=([a-f0-9]+)/i', $signatureHeader, $matches)) {
            return false;
        }

        $sortedPayload = $this->sortDataByKey((array) $payload);
        $json = json_encode($sortedPayload, JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return false;
        }

        $expected = hash_hmac('sha512', $matches[1] . '.' . $json, $secret);

        return hash_equals($expected, strtolower($matches[2]));
    }

    private function sortDataByKey($data)
    {
        if (!is_array($data)) {
            return $data;
        }

        $sorted = [];
        $keys = array_keys($data);
        sort($keys);

        foreach ($keys as $key) {
            $sorted[$key] = $this->sortDataByKey($data[$key]);
        }

        return $sorted;
    }

    private function cassoWebhookToken()
    {
        if (defined('B2B_CASSO_WEBHOOK_TOKEN')) {
            return trim((string) B2B_CASSO_WEBHOOK_TOKEN);
        }

        return trim((string) getenv('B2B_CASSO_WEBHOOK_TOKEN'));
    }

    private function depositCode()
    {
        for ($i = 0; $i < 5; $i++) {
            $random = function_exists('random_bytes')
                ? strtoupper(bin2hex(random_bytes(5)))
                : strtoupper(substr(md5(uniqid('', true)), 0, 10));
            $code = 'B2BNAP' . $random;

            if (!$this->repository->depositRequest($code)) {
                return $code;
            }
        }

        throw new Exception('Cannot create unique deposit code');
    }

    private function extractDepositCode($description)
    {
        $description = strtoupper((string) $description);

        return preg_match('/B2BNAP[0-9A-F]{10}/', $description, $matches)
            ? $matches[0]
            : null;
    }

    private function bankConfig()
    {
        return [
            'name' => defined('B2B_BANK_NAME') ? B2B_BANK_NAME : (getenv('B2B_BANK_NAME') ?: 'MB Bank'),
            'bin' => defined('B2B_BANK_BIN') ? B2B_BANK_BIN : (getenv('B2B_BANK_BIN') ?: '970422'),
            'account_number' => defined('B2B_BANK_ACCOUNT_NUMBER') ? B2B_BANK_ACCOUNT_NUMBER : (getenv('B2B_BANK_ACCOUNT_NUMBER') ?: '0207729018888'),
            'account_name' => defined('B2B_BANK_ACCOUNT_NAME') ? B2B_BANK_ACCOUNT_NAME : (getenv('B2B_BANK_ACCOUNT_NAME') ?: 'HUYNH DUC HIEU')
        ];
    }

    private function vietQrUrl($bank, $amount, $code)
    {
        return 'https://img.vietqr.io/image/'
            . rawurlencode($bank['bin']) . '-'
            . rawurlencode($bank['account_number']) . '-compact2.png'
            . '?amount=' . (int) $amount
            . '&addInfo=' . rawurlencode($code)
            . '&accountName=' . rawurlencode($bank['account_name']);
    }
}
