<?php

class PaymentRepository
{
    private $table;

    public function __construct()
    {
        global $wpdb;

        $this->table = $wpdb->prefix . 'b2b_payments';
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
        $data = $this->filterExistingColumns($data);

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

    private function filterExistingColumns($data)
    {
        $columns = $this->columns();

        return array_filter(
            $data,
            function ($key) use ($columns) {
                return isset($columns[$key]);
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    private function columns()
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        global $wpdb;

        $cache = [];

        foreach ($wpdb->get_results("SHOW COLUMNS FROM {$this->table}") ?: [] as $row) {
            if (!empty($row->Field)) {
                $cache[$row->Field] = true;
            }
        }

        return $cache;
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

    public function findByOrderId($orderId)
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "
                SELECT *
                FROM {$this->table}
                WHERE order_id = %d
                LIMIT 1
                ",
                $orderId
            )
        );
    }

    public function findByTransaction($gatewayRef)
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "
                SELECT *
                FROM {$this->table}
                WHERE gateway_ref = %s
                LIMIT 1
                ",
                $gatewayRef
            )
        );
    }

    public function overdueEscrow($days)
    {
        global $wpdb;

        $ordersTable = $wpdb->prefix . 'b2b_orders';

        return $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT p.*
                FROM {$this->table} p
                INNER JOIN {$ordersTable} o ON o.id = p.order_id
                WHERE p.payment_status = 'escrow'
                AND o.status = 'delivering'
                AND o.updated_at IS NOT NULL
                AND o.updated_at <= DATE_SUB(NOW(), INTERVAL %d DAY)
                ORDER BY p.id ASC
                ",
                (int) $days
            )
        );
    }

    public function findByGatewayOrderCode($orderCode)
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "
                SELECT *
                FROM {$this->table}
                WHERE gateway_ref = %s
                LIMIT 1
                ",
                (string) $orderCode
            )
        );
    }


    public function pendingReleases(int $limit = 200): array
    {
        global $wpdb;

        $limit = max(1, min(500, (int) $limit));
        $ordersTable = $wpdb->prefix . 'b2b_orders';
        $contractsTable = $wpdb->prefix . 'b2b_contracts';
        $quotationsTable = $wpdb->prefix . 'b2b_quotations';
        $companiesTable = $wpdb->prefix . 'b2b_companies';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT
                    p.id AS payment_id,
                    p.payment_status,
                    p.amount AS payment_amount,
                    p.created_at AS payment_created_at,
                    p.paid_at,
                    o.id AS order_id,
                    o.contract_id,
                    o.buyer_company_id,
                    o.seller_company_id,
                    o.total_amount AS order_total_amount,
                    o.status AS order_status,
                    o.created_at AS order_created_at,
                    o.updated_at AS order_updated_at,
                    q.rfq_id,
                    bc.company_name AS buyer_company_name,
                    sc.company_name AS seller_company_name
                FROM {$this->table} p
                INNER JOIN {$ordersTable} o ON o.id = p.order_id
                LEFT JOIN {$contractsTable} c ON c.id = o.contract_id
                LEFT JOIN {$quotationsTable} q ON q.id = c.quotation_id
                LEFT JOIN {$companiesTable} bc ON bc.id = o.buyer_company_id
                LEFT JOIN {$companiesTable} sc ON sc.id = o.seller_company_id
                WHERE o.status = 'completed'
                  AND p.payment_status IN ('escrow', 'paid')
                ORDER BY COALESCE(o.updated_at, o.created_at) DESC, p.id DESC
                LIMIT %d
                ",
                $limit
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }
}
