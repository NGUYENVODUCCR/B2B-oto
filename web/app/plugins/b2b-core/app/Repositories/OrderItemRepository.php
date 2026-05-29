<?php

class OrderItemRepository
{
    private $table;

    public function __construct()
    {
        global $wpdb;

        $this->table = $wpdb->prefix . 'b2b_order_items';
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

    public function createMany($orderId, $items)
    {
        $ids = [];

        foreach ($items as $item) {
            $ids[] = $this->create([
                'order_id' => (int) $orderId,
                'product_id' => (int) $item['product_id'],
                'quantity' => (int) $item['quantity'],
                'price' => (float) $item['price'],
                'created_at' => current_time('mysql')
            ]);
        }

        return $ids;
    }

    public function findByOrderId($orderId)
    {
        global $wpdb;

        $productsTable = $wpdb->prefix . 'b2b_products';

        return $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT 
                    oi.*,

                    p.name AS product_name,

                    p.brand AS brand,
                    p.brand AS product_brand,

                    p.color AS color,
                    p.color AS product_color,

                    p.years AS year,
                    p.years AS product_year,
                    p.years AS manufacture_year,

                    oi.price AS unit_price,
                    (oi.quantity * oi.price) AS line_total

                FROM {$this->table} oi
                LEFT JOIN {$productsTable} p ON p.id = oi.product_id
                WHERE oi.order_id = %d
                ORDER BY oi.id ASC
                ",
                $orderId
            )
        );
    }
}
