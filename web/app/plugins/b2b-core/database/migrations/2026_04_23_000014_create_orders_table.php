<?php

namespace B2B\Database\Migrations;

use B2B\Database\Migration;

if (!defined('ABSPATH')) {
    exit;
}

class CreateOrdersTable extends Migration
{
    public function up(): void
    {
        global $wpdb;
        $prefix = $this->prefix();
        $charset_collate = $this->charsetCollate();

        $sql = "CREATE TABLE {$prefix}orders (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                contract_id BIGINT UNSIGNED NOT NULL,
                buyer_company_id BIGINT UNSIGNED NOT NULL,
                seller_company_id BIGINT UNSIGNED NOT NULL,
                total_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uq_orders_contract_id (contract_id),
                KEY idx_orders_buyer_company_id (buyer_company_id),
                KEY idx_orders_seller_company_id (seller_company_id),
                KEY idx_orders_status (status)
        ) {$charset_collate};";

        $wpdb->query($sql);
    }

    public function down(): void
    {
        global $wpdb;
        $prefix = $this->prefix();
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}orders;");
    }
}

return new CreateOrdersTable();