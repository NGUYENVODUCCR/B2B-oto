<?php

namespace B2B\Database\Migrations;

use B2B\Database\Migration;

if (!defined('ABSPATH')) {
    exit;
}

class CreateQuotationsTable extends Migration
{
    public function up(): void
    {
        global $wpdb;
        $prefix = $this->prefix();
        $charset_collate = $this->charsetCollate();

        $sql = "CREATE TABLE {$prefix}quotations (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                rfq_id BIGINT UNSIGNED NOT NULL,
                seller_company_id BIGINT UNSIGNED NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                valid_until DATETIME NULL,
                is_selected TINYINT(1) NOT NULL DEFAULT 0,
                subtotal_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
                discount_total DECIMAL(18,2) NOT NULL DEFAULT 0,
                total_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
                pricing_json LONGTEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT NULL,
                PRIMARY KEY  (id),
                KEY idx_quotations_rfq_id (rfq_id),
                KEY idx_quotations_seller_company_id (seller_company_id),
                KEY idx_quotations_status (status),
                KEY idx_quotations_is_selected (is_selected)
        ) {$charset_collate};";

        $wpdb->query($sql);
    }

    public function down(): void
    {
        global $wpdb;
        $prefix = $this->prefix();
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}quotations;");
    }
}

return new CreateQuotationsTable();
