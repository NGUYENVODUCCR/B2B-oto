<?php

class SupportMessageRepository
{
    private $table;

    public function __construct()
    {
        global $wpdb;

        $this->table = $wpdb->prefix . 'b2b_support_messages';
    }

    public function create($data)
    {
        global $wpdb;

        $wpdb->insert($this->table, $data);

        if ($wpdb->last_error) {
            throw new Exception($wpdb->last_error);
        }

        return $wpdb->insert_id;
    }

    public function listByTicket($ticketId)
    {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT *
                FROM {$this->table}
                WHERE ticket_id = %d
                ORDER BY id ASC
                ",
                $ticketId
            )
        );
    }
}
