<?php

namespace B2B\Database\Migrations;

use B2B\Database\Migration;

if (!defined('ABSPATH')) {
    exit;
}

class CreateSupportTicketsTable extends Migration
{
    public function up(): void
    {
        global $wpdb;
        $prefix = $this->prefix();
        $charset_collate = $this->charsetCollate();

        $sql = "CREATE TABLE {$prefix}support_tickets (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                order_id BIGINT UNSIGNED NULL,
                support_user_id BIGINT UNSIGNED NULL,
                rfq_id BIGINT UNSIGNED NULL,
                bulk_id BIGINT UNSIGNED NULL,
                support_reference VARCHAR(100) NULL,
                meta_json LONGTEXT NULL,
                type VARCHAR(50) NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'open',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT NULL,
                PRIMARY KEY  (id),
                KEY idx_support_tickets_user_id (user_id),
                KEY idx_support_tickets_order_id (order_id),
                KEY idx_support_tickets_status (status),
                KEY idx_support_tickets_support_user_id (support_user_id),
                KEY idx_support_tickets_rfq_id (rfq_id),
                KEY idx_support_tickets_bulk_id (bulk_id),
                KEY idx_support_tickets_support_reference (support_reference)
        ) {$charset_collate};";

        $wpdb->query($sql);
    }

    public function down(): void
    {
        global $wpdb;
        $prefix = $this->prefix();
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}support_tickets;");
    }
}

return new CreateSupportTicketsTable();
