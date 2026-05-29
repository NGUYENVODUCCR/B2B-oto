<?php

namespace B2B\Database\Migrations;

use B2B\Database\Migration;

if (!defined('ABSPATH')) {
    exit;
}

class CreateQuotationItemsTable extends Migration
{
    public function up(): void
    {
        global $wpdb;
        $prefix = $this->prefix();
        $charset_collate = $this->charsetCollate();

        $sql = "CREATE TABLE {$prefix}quotation_items (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                quotation_id BIGINT UNSIGNED NOT NULL,
                product_id BIGINT UNSIGNED NOT NULL,
                quantity INT NOT NULL DEFAULT 1,
                price DECIMAL(18,2) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY idx_quotation_items_quotation_id (quotation_id),
                KEY idx_quotation_items_product_id (product_id)
        ) {$charset_collate};";

        $wpdb->query($sql);
    }

    public function down(): void
    {
        global $wpdb;
        $prefix = $this->prefix();
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}quotation_items;");
    }
}

return new CreateQuotationItemsTable();