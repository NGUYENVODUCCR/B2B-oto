<?php

class ProductImageRepository
{
    private $table;

    public function __construct()
    {
        global $wpdb;

        $this->table = $wpdb->prefix . 'b2b_product_images';
    }

    public function create($data)
    {
        global $wpdb;

        $data['created_at'] = current_time('mysql');

        $wpdb->insert($this->table, $data);

        if ($wpdb->last_error) {
            throw new Exception($wpdb->last_error);
        }

        return $wpdb->insert_id;
    }

    public function findByProductId($productId)
    {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table}
                WHERE product_id = %d",
                $productId
            )
        );
    }
}