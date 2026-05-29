<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

require_once __DIR__ . '/app/Database/Migration.php';
require_once __DIR__ . '/app/Database/Migrator.php';

$migrator = new B2B\Database\Migrator(
    plugin_dir_path(__FILE__) . 'database/migrations',
    'b2b_marketplace_migrations_applied'
);

$migrator->rollbackAll();