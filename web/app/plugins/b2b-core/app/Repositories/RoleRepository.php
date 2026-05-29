<?php

class RoleRepository {

    private $table;

    public function __construct() {
        global $wpdb;

        $this->table = $wpdb->prefix . 'b2b_roles';
    }

    public function findByName($name) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table}
                WHERE role_name = %s",
                $name
            )
        );
    }

public function findRoleById($listId){
    global $wpdb;

    $listId = array_unique((array) $listId);

    if (empty($listId)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($listId), '%d'));
    
    $query = "SELECT role_name FROM {$this->table} WHERE id IN ($placeholders)";

    $listRole = $wpdb->get_results(
        $wpdb->prepare($query, $listId)
    );

    if (empty($listRole)) {
        throw new Exception("Không thể lấy danh sách ROLE NAME của người dùng này.");
    }

    return $listRole;
}


    public function idByName(string $roleName): int
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table} WHERE role_name = %s LIMIT 1",
            $roleName
        ));
    }

    public function namesByUserId(int $userId): array
    {
        global $wpdb;

        $userRolesTable = $wpdb->prefix . 'b2b_user_roles';

        return $wpdb->get_col($wpdb->prepare(
            "SELECT r.role_name
             FROM {$userRolesTable} ur
             INNER JOIN {$this->table} r ON r.id = ur.role_id
             WHERE ur.user_id = %d",
            $userId
        )) ?: [];
    }
}