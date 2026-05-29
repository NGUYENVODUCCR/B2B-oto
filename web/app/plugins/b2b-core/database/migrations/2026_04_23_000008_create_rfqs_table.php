<?php

namespace B2B\Database\Migrations;

use B2B\Database\Migration;

if (!defined('ABSPATH')) {
    exit;
}

class CreateRfqsTable extends Migration
{
    public function up(): void
    {
        global $wpdb;
        $prefix = $this->prefix();
        $charset_collate = $this->charsetCollate();

        $sql = "CREATE TABLE {$prefix}rfqs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                buyer_company_id BIGINT UNSIGNED NOT NULL,
                message LONGTEXT NULL,
                rfq_type ENUM('direct', 'assisted') NOT NULL DEFAULT 'direct',
                created_by ENUM('buyer', 'support') NOT NULL DEFAULT 'buyer',
                status VARCHAR(30) NOT NULL DEFAULT 'pending',
                bulk_id BIGINT UNSIGNED NULL,
                is_bulk_request TINYINT(1) NOT NULL DEFAULT 0,
                support_user_id BIGINT UNSIGNED NULL,
                support_reference VARCHAR(100) NULL,
                bulk_payload_json LONGTEXT NULL,
                support_ticket_id BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT NULL,
                PRIMARY KEY  (id),
                KEY idx_rfqs_buyer_company_id (buyer_company_id),
                KEY idx_rfqs_status (status),
                KEY idx_rfqs_type (rfq_type),
                KEY idx_rfqs_created_by (created_by),
                KEY idx_rfqs_bulk_id (bulk_id),
                KEY idx_rfqs_is_bulk_request (is_bulk_request),
                KEY idx_rfqs_support_user_id (support_user_id),
                KEY idx_rfqs_support_ticket_id (support_ticket_id)
        ) {$charset_collate};";

        $wpdb->query($sql);
    }

    public function down(): void
    {
        global $wpdb;
        $prefix = $this->prefix();
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}rfqs;");
    }
}

return new CreateRfqsTable();
