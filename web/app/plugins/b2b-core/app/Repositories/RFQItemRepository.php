<?php

class RFQItemRepository
{
    private $table;

    public function __construct()
    {
        global $wpdb;

        $this->table =
            $wpdb->prefix . 'b2b_rfq_items';
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

    public function findByRFQId($rfqId)
    {
        global $wpdb;

        $productsTable = $wpdb->prefix . 'b2b_products';

        return $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT ri.*, p.name AS product_name, p.company_id AS seller_company_id, p.price_from
                FROM {$this->table}
                ri
                LEFT JOIN {$productsTable} p ON p.id = ri.product_id
                WHERE ri.rfq_id = %d
                ORDER BY ri.id ASC
                ",
                $rfqId
            )
        );
    }

    public function countByRFQId($rfqId)
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "
                SELECT COUNT(*)
                FROM {$this->table}
                WHERE rfq_id = %d
                ",
                $rfqId
            )
        );
    }
}
