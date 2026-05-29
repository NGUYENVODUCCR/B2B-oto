<?php

namespace B2B\Database\Migrations;

use B2B\Database\Migration;

if (!defined('ABSPATH')) {
    exit;
}

class CreateCompaniesTable extends Migration
{
    public function up(): void
    {
        global $wpdb;
        $prefix = $this->prefix();
        $charset_collate = $this->charsetCollate();

        $sql = "CREATE TABLE {$prefix}companies (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                company_name VARCHAR(255) NOT NULL,
                tax_code VARCHAR(50) NULL,
                bank_name VARCHAR(255) NULL,
                bank_account VARCHAR(100) NULL,
                address TEXT NULL,
                verification_status VARCHAR(30) NOT NULL DEFAULT 'pending',
                wallet_balance DECIMAL(18,2) NOT NULL DEFAULT 0,
                wallet_transactions_json LONGTEXT NULL,
                wallet_deposit_requests_json LONGTEXT NULL,
                wallet_external_transactions_json LONGTEXT NULL,
                wallet_unmatched_external_json LONGTEXT NULL,
                wallet_updated_at DATETIME NULL DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uq_companies_tax_code (tax_code)
        ) {$charset_collate};";

        $wpdb->query($sql);
    }

    public function down(): void
    {
        global $wpdb;
        $prefix = $this->prefix();
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}companies;");
    }
}

return new CreateCompaniesTable();
