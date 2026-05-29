<?php

namespace B2B\Database\Migrations;

use B2B\Database\Migration;

if (!defined('ABSPATH')) {
    exit;
}

class CreateProductsTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $prefix = $this->prefix();

        $charset_collate = $this->charsetCollate();

        $sql = "CREATE TABLE {$prefix}products (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                company_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(255) NOT NULL,
                description LONGTEXT NULL,
                price_from DECIMAL(18,2) NULL,
                years INT NULL,
                color VARCHAR(50) NOT NULL,
                brand VARCHAR(255) NOT NULL,
                quantity INT NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'draft',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT NULL,
                PRIMARY KEY  (id),
                KEY idx_products_company_id (company_id),
                KEY idx_products_status (status)
        ) {$charset_collate};";
        $wpdb->query($sql);
    }

    public function down(): void
    {
        global $wpdb;

        $prefix = $this->prefix();
        $wpdb->query(
            "DROP TABLE IF EXISTS {$prefix}products;"
        );
    }
}

return new CreateProductsTable();