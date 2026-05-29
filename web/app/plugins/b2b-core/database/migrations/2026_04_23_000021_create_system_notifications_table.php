<?php

namespace B2B\Database\Migrations;

use B2B\Database\Migration;

if (!defined('ABSPATH')) {
    exit;
}

class CreateSystemNotificationsTable extends Migration
{
    public function up(): void
    {
        global $wpdb;
        $prefix = $this->prefix();
        $charset_collate = $this->charsetCollate();

        $sql = "CREATE TABLE {$prefix}system_notifications (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                title VARCHAR(255) NOT NULL,
                content LONGTEXT NOT NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY idx_system_notifications_user_id (user_id),
                KEY idx_system_notifications_is_read (is_read)
        ) {$charset_collate};";

        $wpdb->query($sql);
    }

    public function down(): void
    {
        global $wpdb;
        $prefix = $this->prefix();
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}system_notifications;");
    }
}

return new CreateSystemNotificationsTable();