<?php

class CompanyMemberRepository {

    private $table;

    public function __construct() {
        global $wpdb;

        $this->table = $wpdb->prefix . 'b2b_company_members';
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

    public function update($id, $data)
    {
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

    public function exists($companyId, $userId)
    {
        global $wpdb;

        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$this->table}
                WHERE company_id = %d
                AND user_id = %d",
                $companyId,
                $userId
            )
        );
    }

    public function findByUserId($userId)
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table}
                WHERE user_id = %d
                LIMIT 1",
                $userId
            )
        );
    }

    public function findCompanyId($userId)
        {
            global $wpdb;

            return $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT company_id
                    FROM {$this->table}
                    WHERE user_id = %d",
                    $userId
                )
            );
        }

     public function deleteCron($userId)
        {
            global $wpdb;
            return $wpdb->delete(
                $this->table,
                ['user_id' => $userId]
            );
        }

        
    public function findByCompanyAndUser($companyId, $userId)
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table}
                WHERE company_id = %d
                AND user_id = %d
                LIMIT 1",
                $companyId,
                $userId
            )
        );
    }

    public function existsRole($companyId, $userId, $companyRole)
    {
        global $wpdb;

        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id
                FROM {$this->table}
                WHERE company_id = %d
                AND user_id = %d
                AND company_role = %s
                LIMIT 1",
                $companyId,
                $userId,
                $companyRole
            )
        );
    }
}