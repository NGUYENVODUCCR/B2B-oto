<?php

namespace B2B\Database\Seeders;

if (!defined('ABSPATH')) {
    exit;
}

class RoleSeeder
{
    public function run(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'b2b_roles';

        $roles = require B2B_PLUGIN_PATH . 'config/roles.php';

        foreach ($roles as $role) {

            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$table} WHERE role_name = %s",
                    $role['code']
                )
            );

            if (!$exists) {

                $wpdb->insert($table, [
                    'role_name' => $role['code'],
                    'created_at' => current_time('mysql'),
                ]);
            }
        }
    }
}