<?php

class NegotiationRepository extends BaseRepository
{
    public function __construct()
    {
        global $wpdb;

        $this->table = $wpdb->prefix . 'b2b_negotiations';
    }

    public function create($data)
    {
        global $wpdb;

        $wpdb->insert(
            $this->table,
            $data
        );

        return $wpdb->insert_id;
    }

    public function getByRFQ($rfqId)
    {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT *
                FROM {$this->table}
                WHERE rfq_id = %d
                ORDER BY created_at ASC
                ",
                $rfqId
            )
        );
    }
}