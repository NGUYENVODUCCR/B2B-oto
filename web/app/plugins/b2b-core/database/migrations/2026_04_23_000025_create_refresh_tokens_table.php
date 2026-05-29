<?php

namespace B2B\Database\Migrations;

use B2B\Database\Migration;

if (!defined('ABSPATH')) {
    exit;
}

class CreateRefreshTokensTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $prefix = $this->prefix();
        $charset_collate = $this->charsetCollate();

        $sql = "CREATE TABLE {$prefix}refresh_tokens (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                token TEXT NOT NULL,
                expired_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY idx_refresh_tokens_user_id (user_id),
                KEY idx_refresh_tokens_expired_at (expired_at)
        ) {$charset_collate};";

        $wpdb->query($sql);
    }

    public function down(): void
    {
        global $wpdb;

        $prefix = $this->prefix();
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}refresh_tokens;");
    }
}

return new CreateRefreshTokensTable();