<?php

namespace B2B\Database\Migrations;

use B2B\Database\Migration;

if (!defined('ABSPATH')) {
    exit;
}

class CreateSellerRequestsTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $prefix = $this->prefix();
        $charset_collate = $this->charsetCollate();

        $sql = "CREATE TABLE {$prefix}seller_requests (

                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                representative_name VARCHAR(255) NOT NULL,
                citizen_id_number VARCHAR(50) NOT NULL,
                company_name VARCHAR(255) NOT NULL,
                bank_name VARCHAR(255) NULL,
                bank_account VARCHAR(100) NULL,
                tax_code VARCHAR(100) NOT NULL,
                address TEXT NOT NULL,
                company_email VARCHAR(255) NULL,
                documents LONGTEXT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'pending',
                company_id BIGINT UNSIGNED NULL,
                reviewed_by BIGINT UNSIGNED NULL,
                reviewed_at DATETIME NULL,
                note TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_seller_requests_user_id (user_id),
                KEY idx_seller_requests_company_id (company_id),
                KEY idx_seller_requests_reviewed_by (reviewed_by),
                KEY idx_seller_requests_status (status)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;

        $prefix = $this->prefix();

        $wpdb->query(
            "DROP TABLE IF EXISTS {$prefix}seller_requests;"
        );
    }
}

return new CreateSellerRequestsTable();