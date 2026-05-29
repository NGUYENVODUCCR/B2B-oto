<?php

class ReviewRepository
{
    private $table;

    public function __construct()
    {
        global $wpdb;

        $this->table = $wpdb->prefix . 'b2b_reviews';
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
}
