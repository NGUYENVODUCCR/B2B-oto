<?php

namespace B2B\Database\Migrations;

use B2B\Database\Migration;

if (!defined('ABSPATH')) {
    exit;
}

class CreateNegotiationsTable extends Migration
{
    public function up(): void
    {
        global $wpdb;
        $prefix = $this->prefix();
        $charset_collate = $this->charsetCollate();

        $sql = "CREATE TABLE {$prefix}negotiations (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                rfq_id BIGINT UNSIGNED NOT NULL,
                sender_id BIGINT UNSIGNED NOT NULL,
                sender_type VARCHAR(30) NOT NULL,
                message LONGTEXT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY idx_negotiations_rfq_id (rfq_id),
                KEY idx_negotiations_sender_id (sender_id),
                KEY idx_negotiations_sender_type (sender_type)
        ) {$charset_collate};";

        $wpdb->query($sql);
    }

    public function down(): void
    {
        global $wpdb;
        $prefix = $this->prefix();
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}negotiations;");
    }
}

return new CreateNegotiationsTable();