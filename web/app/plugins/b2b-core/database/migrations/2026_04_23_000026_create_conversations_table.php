<?php

namespace B2B\Database\Migrations;

use B2B\Database\Migration;

if (!defined('ABSPATH')) {
    exit;
}

class CreateConversationsTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $prefix = $this->prefix();
        $charset_collate = $this->charsetCollate();

        $sql = "CREATE TABLE {$prefix}conversations (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                type VARCHAR(50) NOT NULL,
                is_system TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id)
        ) {$charset_collate};";

        $wpdb->query($sql);
    }

    public function down(): void
    {
        global $wpdb;

        $prefix = $this->prefix();
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}conversations;");
    }
}

return new CreateConversationsTable();