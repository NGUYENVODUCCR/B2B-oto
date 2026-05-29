<?php

namespace B2B\Database;

if (!defined('ABSPATH')) {
    exit;
}

abstract class Migration
{
    abstract public function up(): void;
    abstract public function down(): void;

    protected function prefix(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'b2b_';
    }

    protected function charsetCollate(): string
    {
        global $wpdb;
        return $wpdb->get_charset_collate();
    }
}
