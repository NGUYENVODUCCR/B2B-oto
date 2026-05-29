<?php

class RFQRepository
{
    private $table;

    public function __construct()
    {
        global $wpdb;

        $this->table =
            $wpdb->prefix . 'b2b_rfqs';
    }

    public function create($data)
    {
        global $wpdb;

        $wpdb->insert(
            $this->table,
            $data
        );

        if ($wpdb->last_error) {

            throw new Exception(
                $wpdb->last_error
            );
        }

        return $wpdb->insert_id;
    }

    public function update($id, $data)
    {
        global $wpdb;

        $data['updated_at'] = current_time('mysql');

        $result = $wpdb->update(
            $this->table,
            $data,
            [
                'id' => (int) $id
            ]
        );

        if ($wpdb->last_error) {
            throw new Exception($wpdb->last_error);
        }

        return $result !== false;
    }

    public function findById($id)
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "
                SELECT *
                FROM {$this->table}
                WHERE id = %d
                ",
                $id
            )
        );
    }

    public function listForCompany($companyId)
    {
        global $wpdb;

        $productsTable = $wpdb->prefix . 'b2b_products';
        $itemsTable = $wpdb->prefix . 'b2b_rfq_items';
        $companiesTable = $wpdb->prefix . 'b2b_companies';
        $negotiationsTable = $wpdb->prefix . 'b2b_negotiations';

        return $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT r.*,
                    CASE
                        WHEN r.buyer_company_id = %d THEN 'buyer'
                        ELSE 'seller'
                    END AS participant_role,
                    %d AS current_company_id,
                    (
                        SELECT c.company_name
                        FROM {$companiesTable} c
                        WHERE c.id = r.buyer_company_id
                        LIMIT 1
                    ) AS buyer_company_name,
                    (
                        SELECT GROUP_CONCAT(DISTINCT sp.company_name SEPARATOR ', ')
                        FROM {$itemsTable} sri
                        INNER JOIN {$productsTable} spr ON spr.id = sri.product_id
                        LEFT JOIN {$companiesTable} sp ON sp.id = spr.company_id
                        WHERE sri.rfq_id = r.id
                    ) AS seller_company_names,
                    CASE
                        WHEN r.buyer_company_id = %d THEN (
                            SELECT GROUP_CONCAT(DISTINCT sp2.company_name SEPARATOR ', ')
                            FROM {$itemsTable} sri2
                            INNER JOIN {$productsTable} spr2 ON spr2.id = sri2.product_id
                            LEFT JOIN {$companiesTable} sp2 ON sp2.id = spr2.company_id
                            WHERE sri2.rfq_id = r.id
                        )
                        ELSE (
                            SELECT bc2.company_name
                            FROM {$companiesTable} bc2
                            WHERE bc2.id = r.buyer_company_id
                            LIMIT 1
                        )
                    END AS counterparty_name,
                    (
                        SELECT GROUP_CONCAT(DISTINCT pp.name SEPARATOR ', ')
                        FROM {$itemsTable} pri
                        INNER JOIN {$productsTable} pp ON pp.id = pri.product_id
                        WHERE pri.rfq_id = r.id
                    ) AS product_names,
                    (
                        SELECT n.message
                        FROM {$negotiationsTable} n
                        WHERE n.rfq_id = r.id
                        ORDER BY n.id DESC
                        LIMIT 1
                    ) AS last_message,
                    (
                        SELECT n.created_at
                        FROM {$negotiationsTable} n
                        WHERE n.rfq_id = r.id
                        ORDER BY n.id DESC
                        LIMIT 1
                    ) AS last_message_at
                FROM {$this->table} r
                WHERE r.buyer_company_id = %d
                   OR EXISTS (
                        SELECT 1
                        FROM {$itemsTable} ri
                        INNER JOIN {$productsTable} p ON p.id = ri.product_id
                        WHERE ri.rfq_id = r.id
                        AND p.company_id = %d
                   )
                ORDER BY r.id DESC
                ",
                $companyId,
                $companyId,
                $companyId,
                $companyId,
                $companyId
            )
        );
    }

    public function sellerCompanyIds($rfqId)
    {
        global $wpdb;

        $productsTable = $wpdb->prefix . 'b2b_products';
        $itemsTable = $wpdb->prefix . 'b2b_rfq_items';

        return $wpdb->get_col(
            $wpdb->prepare(
                "
                SELECT DISTINCT p.company_id
                FROM {$itemsTable} ri
                INNER JOIN {$productsTable} p ON p.id = ri.product_id
                WHERE ri.rfq_id = %d
                ",
                $rfqId
            )
        );
    }

    public function updateStatus($id, $status)
    {
        return $this->update($id, ['status' => $status]);
    }


    public function tableReady(): bool
    {
        global $wpdb;

        $exists = $wpdb->get_var(
            $wpdb->prepare('SHOW TABLES LIKE %s', $this->table)
        );

        return !empty($exists);
    }

    public function hasColumn(string $column): bool
    {
        global $wpdb;

        $found = $wpdb->get_var(
            $wpdb->prepare("SHOW COLUMNS FROM {$this->table} LIKE %s", $column)
        );

        return !empty($found);
    }

    public function resolveReferences($rfqId, $bulkId): array
    {
        global $wpdb;

        $rfqId = (int) $rfqId;
        $bulkId = (int) $bulkId;

        if (!$this->tableReady()) {
            return [
                'rfq_id' => $rfqId,
                'bulk_id' => $bulkId,
            ];
        }

        if ($rfqId > 0) {
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id, bulk_id FROM {$this->table} WHERE id = %d LIMIT 1",
                    $rfqId
                ),
                ARRAY_A
            );

            if (is_array($row) && $bulkId <= 0 && $this->hasColumn('bulk_id')) {
                $bulkId = (int) ($row['bulk_id'] ?? 0);
            }
        }

        if ($rfqId <= 0 && $bulkId > 0 && $this->hasColumn('bulk_id')) {
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id FROM {$this->table} WHERE bulk_id = %d ORDER BY id DESC LIMIT 1",
                    $bulkId
                ),
                ARRAY_A
            );

            if (is_array($row)) {
                $rfqId = (int) ($row['id'] ?? 0);
            }
        }

        return [
            'rfq_id' => $rfqId,
            'bulk_id' => $bulkId,
        ];
    }

    public function nextBulkId(): int
    {
        global $wpdb;

        if (!$this->tableReady()) {
            throw new Exception('RFQ table is missing');
        }

        $hasBulkFlag = $this->hasColumn('is_bulk_request');
        $hasBulkId = $this->hasColumn('bulk_id');

        if ($hasBulkFlag) {
            $maxId = (int) $wpdb->get_var(
                "SELECT MAX(
                    CASE
                        WHEN bulk_id IS NULL OR bulk_id = 0 THEN id
                        ELSE bulk_id
                    END
                )
                FROM {$this->table}
                WHERE is_bulk_request = 1"
            );

            return $maxId > 0 ? $maxId + 1 : 1;
        }

        if ($hasBulkId) {
            $maxId = (int) $wpdb->get_var("SELECT MAX(bulk_id) FROM {$this->table} WHERE bulk_id IS NOT NULL AND bulk_id > 0");
            return $maxId > 0 ? $maxId + 1 : 1;
        }

        throw new Exception('Missing bulk columns in RFQ table');
    }

    public function bulkIndex(): array
    {
        global $wpdb;

        $hasBulkFlag = $this->hasColumn('is_bulk_request');
        $hasBulkId = $this->hasColumn('bulk_id');

        if (!$this->tableReady() || (!$hasBulkFlag && !$hasBulkId)) {
            return [];
        }

        $where = $hasBulkFlag
            ? 'is_bulk_request = 1'
            : 'bulk_id IS NOT NULL AND bulk_id > 0';

        $rows = $wpdb->get_results(
            "SELECT id, bulk_id FROM {$this->table} WHERE {$where} ORDER BY id DESC",
            ARRAY_A
        );

        if (!is_array($rows)) {
            return [];
        }

        $bulkIds = [];

        foreach ($rows as $row) {
            $bulkRef = $hasBulkId ? (int) ($row['bulk_id'] ?? 0) : 0;

            if ($bulkRef <= 0) {
                $bulkRef = (int) ($row['id'] ?? 0);
            }

            if ($bulkRef > 0 && !in_array($bulkRef, $bulkIds, true)) {
                $bulkIds[] = $bulkRef;
            }
        }

        return $bulkIds;
    }

    public function saveBulk(array $bulk): void
    {
        global $wpdb;

        if (!$this->tableReady()) {
            throw new Exception('RFQ table is missing');
        }

        $bulkId = (int) ($bulk['id'] ?? 0);
        if ($bulkId <= 0) {
            throw new Exception('Bulk id is invalid');
        }

        if (!$this->hasColumn('bulk_id')) {
            throw new Exception('RFQ bulk_id column is missing');
        }

        $exists = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$this->table} WHERE bulk_id = %d ORDER BY id DESC LIMIT 1",
                $bulkId
            )
        );

        $dbData = [
            'buyer_company_id' => (int) ($bulk['buyer_company_id'] ?? 0),
            'message' => (string) ($bulk['broadcast_message'] ?? ('Bulk RFQ #' . $bulkId)),
            'rfq_type' => 'assisted',
            'created_by' => 'support',
            'status' => (string) ($bulk['status'] ?? 'pending'),
            'bulk_id' => $bulkId,
            'support_reference' => 'BULK #' . $bulkId,
            'updated_at' => current_time('mysql'),
        ];

        if ($this->hasColumn('is_bulk_request')) {
            $dbData['is_bulk_request'] = 1;
        }

        if ($this->hasColumn('support_user_id')) {
            $dbData['support_user_id'] = isset($bulk['support_user_id']) ? (int) $bulk['support_user_id'] : null;
        }

        if ($this->hasColumn('bulk_payload_json')) {
            $dbData['bulk_payload_json'] = wp_json_encode($bulk);
        }

        if ($this->hasColumn('support_ticket_id')) {
            $dbData['support_ticket_id'] = isset($bulk['ticket_id']) ? (int) $bulk['ticket_id'] : null;
        }

        if ($exists > 0) {
            $wpdb->update($this->table, $dbData, ['id' => $exists]);
        } else {
            $dbData['created_at'] = (string) ($bulk['created_at'] ?? current_time('mysql'));
            $wpdb->insert($this->table, $dbData);
        }

        if ($wpdb->last_error) {
            throw new Exception($wpdb->last_error);
        }
    }

    public function bulkById($bulkId): ?array
    {
        global $wpdb;

        $bulkId = (int) $bulkId;
        $hasBulkFlag = $this->hasColumn('is_bulk_request');
        $hasBulkId = $this->hasColumn('bulk_id');

        if (!$this->tableReady() || !$hasBulkId || $bulkId <= 0) {
            return null;
        }

        $where = $hasBulkFlag
            ? 'bulk_id = %d AND is_bulk_request = 1'
            : 'bulk_id = %d';

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE {$where} ORDER BY id DESC LIMIT 1",
                $bulkId
            ),
            ARRAY_A
        );

        if (!is_array($row)) {
            return null;
        }

        $decoded = !empty($row['bulk_payload_json'])
            ? json_decode((string) $row['bulk_payload_json'], true)
            : [];

        if (!is_array($decoded)) {
            $decoded = [];
        }

        $decoded['id'] = $bulkId;
        $decoded['rfq_id'] = (int) ($row['id'] ?? 0);
        $decoded['buyer_company_id'] = isset($decoded['buyer_company_id'])
            ? (int) $decoded['buyer_company_id']
            : (int) ($row['buyer_company_id'] ?? 0);
        $decoded['status'] = $decoded['status'] ?? ($row['status'] ?? 'pending');
        $decoded['ticket_id'] = isset($decoded['ticket_id'])
            ? (int) $decoded['ticket_id']
            : (int) ($row['support_ticket_id'] ?? 0);
        $decoded['support_user_id'] = isset($decoded['support_user_id'])
            ? (int) $decoded['support_user_id']
            : (int) ($row['support_user_id'] ?? 0);

        return $decoded;
    }

}
