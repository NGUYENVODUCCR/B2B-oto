<?php

namespace B2B\Database\Migrations;

use B2B\Database\Migration;

if (!defined('ABSPATH')) {
    exit;
}

class CreateContractsTable extends Migration
{
    public function up(): void
    {
        global $wpdb;
        $prefix = $this->prefix();
        $charset_collate = $this->charsetCollate();

        $sql = "CREATE TABLE {$prefix}contracts (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                quotation_id BIGINT UNSIGNED NOT NULL,
                contract_file LONGTEXT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'draft',
                signed_at DATETIME NULL,
                cancelled_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uq_contracts_quotation_id (quotation_id),
                KEY idx_contracts_status (status)
        ) {$charset_collate};";

        $wpdb->query($sql);
    }

    public function down(): void
    {
        global $wpdb;
        $prefix = $this->prefix();
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}contracts;");
    }
}

return new CreateContractsTable();