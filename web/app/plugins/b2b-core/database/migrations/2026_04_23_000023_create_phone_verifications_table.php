<?php

namespace B2B\Database\Migrations;

use B2B\Database\Migration;

if (!defined('ABSPATH')) {
    exit;
}

class CreateEmailVerificationsTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $prefix = $this->prefix();
        $charset_collate = $this->charsetCollate();

        $sql = "CREATE TABLE {$prefix}phone_verifications (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            phone VARCHAR(20) NOT NULL,
            otp VARCHAR(255) NOT NULL,
            expired_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_phone (phone),
            KEY idx_expired (expired_at)
        ) {$charset_collate};";

        $wpdb->query($sql);
    }

    public function down(): void
    {
        global $wpdb;

        $prefix = $this->prefix();
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}phone_verifications;");
    }
}

return new CreateEmailVerificationsTable();