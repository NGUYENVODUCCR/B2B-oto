<?php

class OrderRepository
{
    private $table;

    public function __construct()
    {
        global $wpdb;

        $this->table = $wpdb->prefix . 'b2b_orders';
    }

    public function create($data)
    {
        global $wpdb;

        $wpdb->insert($this->table, $data);

        if ($wpdb->last_error) {
            throw new Exception($wpdb->last_error);
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
                LIMIT 1
                ",
                $id
            )
        );
    }

    public function findByContractId($contractId)
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "
                SELECT *
                FROM {$this->table}
                WHERE contract_id = %d
                LIMIT 1
                ",
                $contractId
            )
        );
    }

    public function listForCompany($companyId, $search = '')
    {
        return $this->listByScope(
            '(o.buyer_company_id = %d OR o.seller_company_id = %d)',
            [(int) $companyId, (int) $companyId],
            $search
        );
    }

    public function listAllForSupport($search = '')
    {
        return $this->listByScope('1 = 1', [], $search);
    }

    private function listByScope($scopeSql, array $scopeParams = [], $search = '')
    {
        global $wpdb;

        $orders = $this->table;
        $contracts = $wpdb->prefix . 'b2b_contracts';
        $quotations = $wpdb->prefix . 'b2b_quotations';
        $rfqs = $wpdb->prefix . 'b2b_rfqs';
        $payments = $wpdb->prefix . 'b2b_payments';
        $orderItems = $wpdb->prefix . 'b2b_order_items';
        $products = $wpdb->prefix . 'b2b_products';
        $companies = $wpdb->prefix . 'b2b_companies';

        $where = [$scopeSql];
        $params = $scopeParams;

        $this->appendSearchWhere($where, $params, $search);

        $sql = "
            SELECT
                o.*,
                q.rfq_id AS rfq_id,
                r.bulk_id AS bulk_id,
                buyer.company_name AS buyer_company_name,
                seller.company_name AS seller_company_name,
                COALESCE(p.payment_status, 'unpaid') AS payment_status,
                p.amount AS payment_amount,
                p.paid_at,
                p.released_at,
                COALESCE(items.total_items, 0) AS total_items,
                COALESCE(items.total_quantity, 0) AS total_quantity,
                COALESCE(items.product_names, '') AS product_names
            FROM {$orders} o
            LEFT JOIN {$contracts} c ON c.id = o.contract_id
            LEFT JOIN {$quotations} q ON q.id = c.quotation_id
            LEFT JOIN {$rfqs} r ON r.id = q.rfq_id
            LEFT JOIN {$payments} p ON p.order_id = o.id
            LEFT JOIN {$companies} buyer ON buyer.id = o.buyer_company_id
            LEFT JOIN {$companies} seller ON seller.id = o.seller_company_id
            LEFT JOIN (
                SELECT
                    oi.order_id,
                    COUNT(*) AS total_items,
                    COALESCE(SUM(oi.quantity), 0) AS total_quantity,
                    GROUP_CONCAT(DISTINCT COALESCE(pr.name, CONCAT('Product #', oi.product_id)) ORDER BY pr.name SEPARATOR ', ') AS product_names
                FROM {$orderItems} oi
                LEFT JOIN {$products} pr ON pr.id = oi.product_id
                GROUP BY oi.order_id
            ) items ON items.order_id = o.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY COALESCE(p.released_at, p.paid_at, o.updated_at, o.created_at) DESC, o.id DESC
        ";

        if (empty($params)) {
            return $wpdb->get_results($sql);
        }

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    private function appendSearchWhere(array &$where, array &$params, $search)
    {
        global $wpdb;

        $search = trim((string) $search);

        if ($search === '') {
            return;
        }

        $searchLower = strtolower($search);
        $searchLike = '%' . $wpdb->esc_like($searchLower) . '%';
        $normalizedSearch = strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $search));
        $idLike = '';

        $searchParts = [
            "LOWER(CONCAT_WS(' ',
                CAST(o.id AS CHAR),
                CAST(o.contract_id AS CHAR),
                CAST(COALESCE(q.rfq_id, 0) AS CHAR),
                CAST(COALESCE(r.bulk_id, 0) AS CHAR),
                COALESCE(o.status, ''),
                COALESCE(p.payment_status, ''),
                COALESCE(buyer.company_name, ''),
                COALESCE(seller.company_name, ''),
                COALESCE(items.product_names, '')
            )) LIKE %s"
        ];
        $params[] = $searchLike;

        if ($normalizedSearch !== '') {
            $normalizedLike = '%' . $wpdb->esc_like($normalizedSearch) . '%';
            $searchParts[] = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(CONCAT_WS('',
                CAST(o.id AS CHAR),
                CAST(o.contract_id AS CHAR),
                CAST(COALESCE(q.rfq_id, 0) AS CHAR),
                CAST(COALESCE(r.bulk_id, 0) AS CHAR),
                COALESCE(o.status, ''),
                COALESCE(p.payment_status, ''),
                COALESCE(buyer.company_name, ''),
                COALESCE(seller.company_name, ''),
                COALESCE(items.product_names, '')
            )), ' ', ''), '#', ''), '-', ''), '_', ''), '/', '') LIKE %s";
            $params[] = $normalizedLike;
        }

        if (preg_match('/\d+/', $search, $numberMatch)) {
            $idLike = '%' . $wpdb->esc_like((string) $numberMatch[0]) . '%';
        }

        if ($idLike !== '') {
            $searchParts[] = "CAST(o.id AS CHAR) LIKE %s";
            $params[] = $idLike;

            $searchParts[] = "CAST(o.contract_id AS CHAR) LIKE %s";
            $params[] = $idLike;

            $searchParts[] = "CAST(COALESCE(q.rfq_id, 0) AS CHAR) LIKE %s";
            $params[] = $idLike;

            $searchParts[] = "CAST(COALESCE(r.bulk_id, 0) AS CHAR) LIKE %s";
            $params[] = $idLike;
        }

        $where[] = '(' . implode(' OR ', $searchParts) . ')';
    }

    public function sellerHistory($sellerCompanyId, array $filters = [])
    {
        global $wpdb;

        $orders = $this->table;
        $payments = $wpdb->prefix . 'b2b_payments';
        $orderItems = $wpdb->prefix . 'b2b_order_items';
        $products = $wpdb->prefix . 'b2b_products';
        $companies = $wpdb->prefix . 'b2b_companies';

        $where = ['o.seller_company_id = %d'];
        $params = [(int) $sellerCompanyId];

        $status = strtolower(trim((string) ($filters['status'] ?? '')));
        $paymentStatus = strtolower(trim((string) ($filters['payment_status'] ?? '')));
        $dateFrom = (string) ($filters['date_from'] ?? '');
        $dateTo = (string) ($filters['date_to'] ?? '');

        if ($status !== '' && $status !== 'all') {
            $where[] = 'o.status = %s';
            $params[] = $status;
        }

        if ($paymentStatus !== '' && $paymentStatus !== 'all') {
            $where[] = "COALESCE(p.payment_status, 'unpaid') = %s";
            $params[] = $paymentStatus;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $where[] = 'DATE(COALESCE(p.released_at, p.paid_at, o.updated_at, o.created_at)) >= %s';
            $params[] = $dateFrom;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $where[] = 'DATE(COALESCE(p.released_at, p.paid_at, o.updated_at, o.created_at)) <= %s';
            $params[] = $dateTo;
        }

        $limit = (int) ($filters['limit'] ?? 100);
        $limit = max(1, min(300, $limit));
        $params[] = $limit;

        $sql = "
            SELECT
                o.id AS order_id,
                o.contract_id,
                o.buyer_company_id,
                o.seller_company_id,
                o.total_amount,
                o.status AS order_status,
                o.created_at AS order_created_at,
                o.updated_at AS order_updated_at,
                COALESCE(p.payment_status, 'unpaid') AS payment_status,
                p.amount AS payment_amount,
                p.paid_at,
                p.released_at,
                buyer.company_name AS buyer_company_name,
                seller.company_name AS seller_company_name,
                COALESCE(items.total_items, 0) AS total_items,
                COALESCE(items.total_quantity, 0) AS total_quantity,
                COALESCE(items.product_names, '') AS product_names
            FROM {$orders} o
            LEFT JOIN {$payments} p ON p.order_id = o.id
            LEFT JOIN {$companies} buyer ON buyer.id = o.buyer_company_id
            LEFT JOIN {$companies} seller ON seller.id = o.seller_company_id
            LEFT JOIN (
                SELECT
                    oi.order_id,
                    COUNT(*) AS total_items,
                    COALESCE(SUM(oi.quantity), 0) AS total_quantity,
                    GROUP_CONCAT(DISTINCT COALESCE(pr.name, CONCAT('Product #', oi.product_id)) ORDER BY pr.name SEPARATOR ', ') AS product_names
                FROM {$orderItems} oi
                LEFT JOIN {$products} pr ON pr.id = oi.product_id
                GROUP BY oi.order_id
            ) items ON items.order_id = o.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY COALESCE(p.released_at, p.paid_at, o.updated_at, o.created_at) DESC
            LIMIT %d
        ";

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }
}
