<?php

namespace B2B\Database\Migrations;

use B2B\Database\Migration;

if (!defined('ABSPATH')) {
    exit;
}

class CreatePasswordResetsTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $prefix = $this->prefix();
        $charset_collate = $this->charsetCollate();

        $sql = "CREATE TABLE {$prefix}password_resets (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                email VARCHAR(150) NOT NULL,
                otp VARCHAR(255) NOT NULL,
                expired_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY idx_password_resets_email (email),
                KEY idx_password_resets_expired_at (expired_at)
        ) {$charset_collate};";

        $wpdb->query($sql);
    }

    public function down(): void
    {
        global $wpdb;

        $prefix = $this->prefix();
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}password_resets;");
    }
}

return new CreatePasswordResetsTable();