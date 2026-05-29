<?php

class AdminRepository {

    private $wpdb;
    private $conversationTable;
    private $messageTable;

    public function __construct() {
        global $wpdb;

        $this->wpdb = $wpdb;
        $this->conversationTable = $wpdb->prefix . 'b2b_conversations';
        $this->messageTable = $wpdb->prefix . 'b2b_messages';
    }

public function findConversationBetweenUsers($userA, $userB, $type) {
        $userA = intval($userA);
        $userB = intval($userB);

        $sql = "
            SELECT c.*
            FROM {$this->conversationTable} c
            WHERE c.type = %s
            AND c.id IN (
                SELECT DISTINCT conversation_id 
                FROM {$this->messageTable} 
                WHERE (sender_id = %d AND receiver_id = %d)
                   OR (sender_id = %d AND receiver_id = %d)
            )
            LIMIT 1
        ";

        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                $sql,
                $type,
                $userA,
                $userB,
                $userB,
                $userA
            )
        );
    }
 
    public function createConversation($type, $isSystem = 'no') {

        $inserted = $this->wpdb->insert(
            $this->conversationTable,
            [
                'type' => $type,
                'is_system' => $isSystem,
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s']
        );

        if (!$inserted) {
            return false;
        }

        return $this->wpdb->insert_id;
    }


    public function createMessage($conversationId, $senderId, $receiverId, $message) {

        $inserted = $this->wpdb->insert(
            $this->messageTable,
            [
                'conversation_id' => $conversationId,
                'sender_id' => $senderId,
                'receiver_id' => $receiverId,
                'message' => sanitize_textarea_field($message),
                'created_at' => current_time('mysql')
            ],
            ['%d', '%d', '%d', '%s', '%s']
        );

        if (!$inserted) {
            return false;
        }

        return $this->wpdb->insert_id;
    }

    public function getConversationMessages($conversationId) {

        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "
                SELECT
                    id,
                    conversation_id,
                    sender_id,
                    receiver_id,
                    message,
                    created_at
                FROM {$this->messageTable}
                WHERE conversation_id = %d
                ORDER BY created_at ASC
                ",
                $conversationId
            )
        );
    }


    public function pagedUserIds(string $role, int $perPage, int $offset): array
    {
        global $wpdb;

        $usersTable = $wpdb->prefix . 'users';
        $userRolesTable = $wpdb->prefix . 'b2b_user_roles';
        $b2bUsersTable = $wpdb->prefix . 'b2b_users';
        $roleMapping = $this->roleMapping();

        if ($role !== 'all' && isset($roleMapping[$role])) {
            $roleId = (int) $roleMapping[$role];

            return $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT ur.user_id
                 FROM {$userRolesTable} ur
                 LEFT JOIN {$b2bUsersTable} bu ON ur.user_id = bu.wp_user_id
                 WHERE ur.role_id = %d AND (bu.status IS NULL OR bu.status != 'deleted')
                 ORDER BY ur.user_id DESC
                 LIMIT %d OFFSET %d",
                $roleId,
                $perPage,
                $offset
            )) ?: [];
        }

        return $wpdb->get_col($wpdb->prepare(
            "SELECT u.ID
             FROM {$usersTable} u
             LEFT JOIN {$b2bUsersTable} bu ON u.ID = bu.wp_user_id
             WHERE bu.status IS NULL OR bu.status != 'deleted'
             ORDER BY u.ID DESC
             LIMIT %d OFFSET %d",
            $perPage,
            $offset
        )) ?: [];
    }

    public function countUsers(string $role): int
    {
        global $wpdb;

        $usersTable = $wpdb->prefix . 'users';
        $userRolesTable = $wpdb->prefix . 'b2b_user_roles';
        $b2bUsersTable = $wpdb->prefix . 'b2b_users';
        $roleMapping = $this->roleMapping();

        if ($role !== 'all' && isset($roleMapping[$role])) {
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT ur.user_id)
                 FROM {$userRolesTable} ur
                 LEFT JOIN {$b2bUsersTable} bu ON ur.user_id = bu.wp_user_id
                 WHERE ur.role_id = %d AND (bu.status IS NULL OR bu.status != 'deleted')",
                (int) $roleMapping[$role]
            ));
        }

        return (int) $wpdb->get_var(
            "SELECT COUNT(u.ID)
             FROM {$usersTable} u
             LEFT JOIN {$b2bUsersTable} bu ON u.ID = bu.wp_user_id
             WHERE bu.status IS NULL OR bu.status != 'deleted'"
        );
    }

    public function wpUserBasic($wpUserId)
    {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT user_login, user_email, display_name, user_registered
             FROM {$wpdb->prefix}users
             WHERE ID = %d
             LIMIT 1",
            (int) $wpUserId
        ));
    }

    public function roleIds($wpUserId): array
    {
        global $wpdb;

        return $wpdb->get_col($wpdb->prepare(
            "SELECT role_id FROM {$wpdb->prefix}b2b_user_roles WHERE user_id = %d",
            (int) $wpUserId
        )) ?: [];
    }

    public function b2bUserByWpId($wpUserId)
    {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT fullname, phone, status FROM {$wpdb->prefix}b2b_users WHERE wp_user_id = %d LIMIT 1",
            (int) $wpUserId
        ));
    }

    public function fullnameByWpId($wpUserId): string
    {
        global $wpdb;

        return (string) $wpdb->get_var($wpdb->prepare(
            "SELECT fullname FROM {$wpdb->prefix}b2b_users WHERE wp_user_id = %d LIMIT 1",
            (int) $wpUserId
        ));
    }

    public function wpUserIdByB2bUserId($b2bUserId): int
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT wp_user_id FROM {$wpdb->prefix}b2b_users WHERE id = %d LIMIT 1",
            (int) $b2bUserId
        ));
    }

    public function verifiedSellerRequestConfig($companyId): array
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT user_id, bank_name, bank_account, citizen_id_number, documents
             FROM {$wpdb->prefix}b2b_seller_requests
             WHERE company_id = %d AND status = 'verified'
             ORDER BY id DESC LIMIT 1",
            (int) $companyId
        ), ARRAY_A);

        return is_array($row) ? $row : [];
    }

    public function sellerRequestExists($wpUserId, $companyId): bool
    {
        global $wpdb;

        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}b2b_seller_requests WHERE user_id = %d AND company_id = %d LIMIT 1",
            (int) $wpUserId,
            (int) $companyId
        ));
    }

    public function createSellerRequest(array $data): int
    {
        global $wpdb;

        $wpdb->insert($wpdb->prefix . 'b2b_seller_requests', $data);

        if ($wpdb->last_error) {
            throw new Exception($wpdb->last_error);
        }

        return (int) $wpdb->insert_id;
    }

    public function verifiedCompanies(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT c.id, c.company_name, c.tax_code
             FROM {$wpdb->prefix}b2b_companies AS c
             INNER JOIN {$wpdb->prefix}b2b_seller_requests AS r ON c.id = r.company_id
             WHERE r.status = 'verified'
             ORDER BY c.company_name ASC"
        );

        return is_array($rows) ? $rows : [];
    }

    public function updateB2bFullname($wpUserId, string $fullname): bool
    {
        global $wpdb;

        $result = $wpdb->update(
            $wpdb->prefix . 'b2b_users',
            ['fullname' => $fullname],
            ['wp_user_id' => (int) $wpUserId],
            ['%s'],
            ['%d']
        );

        if ($wpdb->last_error) {
            throw new Exception($wpdb->last_error);
        }

        return $result !== false;
    }

    public function clearUserRoles($wpUserId): bool
    {
        global $wpdb;

        $result = $wpdb->delete(
            $wpdb->prefix . 'b2b_user_roles',
            ['user_id' => (int) $wpUserId],
            ['%d']
        );

        if ($wpdb->last_error) {
            throw new Exception($wpdb->last_error);
        }

        return $result !== false;
    }

    public function softDeleteUser($wpUserId): bool
    {
        global $wpdb;

        $result = $wpdb->update(
            $wpdb->prefix . 'b2b_users',
            ['status' => 'deleted'],
            ['wp_user_id' => (int) $wpUserId],
            ['%s'],
            ['%d']
        );

        if ($wpdb->last_error) {
            throw new Exception($wpdb->last_error);
        }

        return $result !== false;
    }

    public function updateProductsStatusByUser($wpUserId, string $status): bool
    {
        global $wpdb;

        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}products SET status = %s WHERE user_id = %d",
            $status,
            (int) $wpUserId
        ));

        if ($wpdb->last_error) {
            throw new Exception($wpdb->last_error);
        }

        return $result !== false;
    }

    private function roleMapping(): array
    {
        return [
            'buyer' => 1,
            'seller' => 2,
            'support' => 3,
            'admin' => 4,
        ];
    }
}