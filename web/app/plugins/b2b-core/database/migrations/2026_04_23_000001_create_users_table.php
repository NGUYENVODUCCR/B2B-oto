<?php

namespace B2B\Database\Migrations;

use B2B\Database\Migration;

if (!defined('ABSPATH')) {
    exit;
}

class CreateUsersTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $prefix = $this->prefix();
        $charset_collate = $this->charsetCollate();
        $wp_users_table = $wpdb->users;

        $sql = "CREATE TABLE IF NOT EXISTS {$prefix}users (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            wp_user_id BIGINT UNSIGNED NOT NULL,
            fullname VARCHAR(255) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            phone_verified_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL,

            PRIMARY KEY (id),

            UNIQUE KEY uq_users_wp_user_id (wp_user_id),
            UNIQUE KEY uq_users_phone (phone),

            KEY idx_users_wp_user_id (wp_user_id),

            CONSTRAINT fk_users_wp_user
                FOREIGN KEY (wp_user_id)
                REFERENCES {$wp_users_table}(ID)
                ON DELETE CASCADE
        ) {$charset_collate};";

        dbDelta($sql);
    }

    public function down(): void
    {
        global $wpdb;
        $prefix = $this->prefix();

        $wpdb->query("DROP TABLE IF EXISTS {$prefix}users;");
    }
}

return new CreateUsersTable();