<?php

if (!defined('ABSPATH')) {
    exit;
}

class AdminLogRepository
{
    private $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'b2b_admin_logs';
    }

    public function create($adminId, $action): int
    {
        global $wpdb;

        $wpdb->insert($this->table, [
            'admin_id' => (int) $adminId,
            'action' => (string) $action,
            'created_at' => TimeHelper::mysql(),
        ]);

        if ($wpdb->last_error) {
            throw new Exception($wpdb->last_error);
        }

        return (int) $wpdb->insert_id;
    }
}
