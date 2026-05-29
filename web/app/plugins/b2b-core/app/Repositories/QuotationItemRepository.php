<?php

class QuotationItemRepository
{
    private $table;

    public function __construct()
    {
        global $wpdb;

        $this->table = $wpdb->prefix . 'b2b_quotation_items';
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

    public function createMany($quotationId, $items)
    {
        $ids = [];

        foreach ($items as $item) {
            $ids[] = $this->create([
                'quotation_id' => (int) $quotationId,
                'product_id' => (int) $item['product_id'],
                'quantity' => (int) $item['quantity'],
                'price' => (float) $item['price'],
                'created_at' => current_time('mysql')
            ]);
        }

        return $ids;
    }

    public function deleteByQuotationId($quotationId)
    {
        global $wpdb;

        return $wpdb->delete(
            $this->table,
            [
                'quotation_id' => (int) $quotationId
            ]
        );
    }

    public function findByQuotationId($quotationId)
{
    global $wpdb;

    $productsTable = $wpdb->prefix . 'b2b_products';

    return $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT 
                qi.*,

                p.name AS product_name,
                p.company_id AS seller_company_id,

                p.brand AS brand,
                p.brand AS product_brand,

                p.color AS color,
                p.color AS product_color,

                p.years AS year,
                p.years AS product_year,
                p.years AS manufacture_year

            FROM {$this->table} qi
            LEFT JOIN {$productsTable} p ON p.id = qi.product_id
            WHERE qi.quotation_id = %d
            ORDER BY qi.id ASC
            ",
            $quotationId
        )
    );
}

    public function totalByQuotationId($quotationId)
    {
        global $wpdb;

        return (float) $wpdb->get_var(
            $wpdb->prepare(
                "
                SELECT COALESCE(SUM(quantity * price), 0)
                FROM {$this->table}
                WHERE quotation_id = %d
                ",
                $quotationId
            )
        );
    }
}
