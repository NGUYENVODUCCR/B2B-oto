<?php

class SellerRequestRepository {

    private $table;

    public function __construct() {
        global $wpdb;

        $this->table = $wpdb->prefix . 'b2b_seller_requests';
    }

    public function create($data) {
        global $wpdb;

        $data['created_at'] = current_time('mysql');

        $wpdb->insert($this->table, $data);

        if ($wpdb->last_error) {
            throw new Exception($wpdb->last_error);
        }

        return $wpdb->insert_id;
    }

    public function findLatestByUserId($userId)
    
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table}
                WHERE user_id = %d
                ORDER BY id DESC
                LIMIT 1",
                $userId
            )
        );
    }

    public function findRowByUserId($userId)
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "
                SELECT *
                FROM {$this->table}
                WHERE user_id = %d
                LIMIT 1
                ",
                $userId
            )
        );
    }

    public function findLinkFile($documents)
    {
        if (empty($documents)) {
            return [];
        }

        $documents = json_decode($documents, true);

        if (!is_array($documents)) {
            return [];
        }

        $mapping = [

            'citizen_front' =>
                'CCCD mặt trước',

            'citizen_back' =>
                'CCCD mặt sau',

            'business_license' =>
                'Giấy phép kinh doanh',

            'inspection_certificate' =>
                'Giấy kiểm định',

            'company_logo' =>
                'Logo công ty',
        ];

        $result = [];

        foreach ($documents as $doc) {

            $type = $doc['type'] ?? null;

            $url = $doc['url'] ?? null;

            if (!$type || !$url) {
                continue;
            }

            $label =
                $mapping[$type]
                ?? $type;

            $result[] = [

                'label' => $label,

                'url' => $url,
            ];
        }

        return $result;
    }

    public function findById($id) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE id = %d",
                $id
            )
        );
    }

    public function findPendingByUserId($userId) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table}
                 WHERE user_id = %d AND status = 'pending'
                 LIMIT 1",
                $userId
            )
        );
    }

    public function findByUserId($userId) {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table}
                 WHERE user_id = %d
                 ORDER BY created_at DESC",
                $userId
            )
        );
    }

    public function all() {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT * FROM {$this->table}
             ORDER BY created_at DESC"
        );
    }

    public function approve($id, $data = [])
    {
        return $this->update($id, array_merge($data, [
            'status' => 'verified'
        ]));
    }

    public function reject($id, $data = []) {
        return $this->update($id, array_merge($data, [
            'status' => 'unverified'
        ]));
    }

    public function getPendingList() {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT * FROM {$this->table}
             WHERE status = 'pending'
             ORDER BY created_at DESC"
        );
    }

    public function update($id, $data) {
        global $wpdb;

        $result = $wpdb->update(
            $this->table,
            $data,
            ['id' => $id]
        );

        if ($wpdb->last_error) {
            throw new Exception($wpdb->last_error);
        }

        return $result !== false;
    }
}