<?php

class UserRoleRepository {

    private $table;

    public function __construct() {
        global $wpdb;

        $this->table = $wpdb->prefix . 'b2b_user_roles';
    }

    public function assignRole($userId, $roleId) {
        global $wpdb;

        $exists = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table}
                WHERE user_id = %d
                AND role_id = %d",
                $userId,
                $roleId
            )
        );

        if ($exists) {
            return true;
        }

        $wpdb->insert(
            $this->table,
            [
                'user_id' => $userId,
                'role_id' => $roleId,
                'assigned_at' => current_time('mysql')
            ]
        );

        if ($wpdb->last_error) {
            throw new Exception($wpdb->last_error);
        }

        return true;
    }

 public function getIdRole($userId){
    global $wpdb;

    $result = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT role_id 
            FROM {$this->table} 
            WHERE user_id = %d",
            $userId
        )
    );

    if (empty($result)){
        throw new Exception('Lỗi khi lấy role id mới nhất từ bảng b2b_user_role.');
    }

    return $result;
}


    public function exists(int $userId, int $roleId): bool
    {
        global $wpdb;

        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table} WHERE user_id = %d AND role_id = %d LIMIT 1",
            $userId,
            $roleId
        ));
    }
}