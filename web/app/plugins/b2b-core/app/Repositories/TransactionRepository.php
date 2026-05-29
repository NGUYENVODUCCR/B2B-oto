<?php

if (!defined('ABSPATH')) {
    exit;
}

class TransactionRepository
{
    public function start(): void
    {
        global $wpdb;
        $wpdb->query('START TRANSACTION');
    }

    public function commit(): void
    {
        global $wpdb;
        $wpdb->query('COMMIT');
    }

    public function rollback(): void
    {
        global $wpdb;
        $wpdb->query('ROLLBACK');
    }
}
