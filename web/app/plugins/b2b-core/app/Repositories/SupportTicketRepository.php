<?php

class SupportTicketRepository
{
    private $table;

    public function __construct()
    {
        global $wpdb;

        $this->table = $wpdb->prefix . 'b2b_support_tickets';
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

    public function update($id, $data)
    {
        global $wpdb;

        $data['updated_at'] = current_time('mysql');

        $result = $wpdb->update(
            $this->table,
            $data,
            [
                'id' => (int) $id
            ]
        );

        if ($wpdb->last_error) {
            throw new Exception($wpdb->last_error);
        }

        return $result !== false;
    }

    public function findById($id)
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "
                SELECT *
                FROM {$this->table}
                WHERE id = %d
                LIMIT 1
                ",
                $id
            )
        );
    }

    public function listByUser($userId)
    {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT *
                FROM {$this->table}
                WHERE user_id = %d
                ORDER BY id DESC
                ",
                $userId
            )
        );
    }

    public function listAll()
    {
        global $wpdb;

        return $wpdb->get_results(
            "
            SELECT *
            FROM {$this->table}
            ORDER BY id DESC
            "
        );
    }

    public function findLatestByType($type)
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "
                SELECT *
                FROM {$this->table}
                WHERE type = %s
                ORDER BY id DESC
                LIMIT 1
                ",
                $type
            )
        );
    }
}
