<?php

class UserRepository {

    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'b2b_users';
    }

    public function create($data) {
        global $wpdb;

        $data['created_at'] = current_time('mysql');

        $result = $wpdb->insert($this->table, $data);

        if ($result === false) {
            throw new Exception('Tạo user thất bại: ' . $wpdb->last_error);
        }

        return $wpdb->insert_id;
    }

    public function findById($id) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE id = %d LIMIT 1",
                $id
            )
        );
    }

    // public function findByName($name) {
    //     global $wpdb;

    //     return $wpdb->get_row(
    //         $wpdb->prepare(
    //             "SELECT * FROM {$this->table} WHERE name = %s LIMIT 1",
    //             $name
    //         )
    //     );
    // }

    public function findByPhone($phone) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE phone = %s LIMIT 1",
                $phone
            )
        );
    }

    public function findByWpUserId($wpUserId) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE wp_user_id = %d LIMIT 1",
                $wpUserId
            )
        );
    }

    public function update($id, $data) {
        global $wpdb;

        $data['updated_at'] = current_time('mysql');

        $result = $wpdb->update(
            $this->table,
            $data,
            ['id' => $id]
        );

        if ($result === false) {
            throw new Exception('Update thất bại: ' . $wpdb->last_error);
        }

        return $this->findById($id);
    }

    public function updateByPhone($phone, $data) {
        global $wpdb;

        $data['updated_at'] = current_time('mysql');

        $result = $wpdb->update(
            $this->table,
            $data,
            ['phone' => $phone]
        );

        if ($result === false) {
            throw new Exception('Update theo phone thất bại: ' . $wpdb->last_error);
        }

        return true;
    }

    public function updateVerified($phone){
        global $wpdb;
        $result = $wpdb->update(
            $this->table,
            [
                "status" => "active",
                "phone_verified_at" => current_time('mysql')
            ],
            ['phone' => $phone]
        );
        if($result === false){
            throw new Exception("Cập nhật status và thời gian xác thực thất bại");
        }
        return true;
    }

    public function deleteById($id) {
        global $wpdb;

        $user = $this->findById($id);

        if (!$user) return false;

        if (!empty($user->wp_user_id)) {
            WpUserService::instance()->delete($user->wp_user_id);
        }

        return $wpdb->delete($this->table, ['id' => $id]);
    }

    public function cleanupPendingUsers() {
        global $wpdb;

        error_log("=== CLEANUP START ===");

        $users = $wpdb->get_results(
            "SELECT id, wp_user_id, phone, created_at 
            FROM {$this->table}
            WHERE status = 'pending'
            AND created_at < (NOW() - INTERVAL 15 MINUTE)"
        );

        if (empty($users)) {
            error_log("No pending users to delete");
            echo "No pending users\n";
            return;
        }

        if (!class_exists('PhoneVerificationRepository')) {
            require_once __DIR__ . '/PhoneVerificationRepository.php';
        }
        $verifyRepo = new PhoneVerificationRepository();

        if (!class_exists('CompanyRepository')) {
            require_once __DIR__ . '/CompanyRepository.php';
        }
        $companyRepo = new CompanyRepository();

        if (!class_exists('CompanyMemberRepository')) {
            require_once __DIR__ . '/CompanyMemberRepository.php';
        }
        $companyMemberRepo = new CompanyMemberRepository();

        foreach ($users as $user) {

            error_log("Deleting user ID: {$user->id} | Phone: {$user->phone}");

            if (!empty($user->wp_user_id)) {
                $wpdb->delete(
                    $wpdb->prefix . 'b2b_user_roles',
                    [
                        'user_id' => $user->wp_user_id
                    ]
                );
                error_log("Deleted role for WP user: {$user->wp_user_id}");
            }

            try {
                $verifyRepo->deleteOtp($user->phone);
                error_log("Deleted OTP for: {$user->phone}");
            } catch (Exception $e) {
                error_log("Delete OTP error: " . $e->getMessage());
            }

            $companyId = 0;

            try {
                if (!empty($user->wp_user_id)) {
                    $companyId = (int) $companyMemberRepo->findCompanyId($user->wp_user_id);
                    error_log("Found company ID: {$companyId} for WP user: {$user->wp_user_id}");
                }
            } catch (Exception $e) {
                error_log("Find company error: " . $e->getMessage());
            }

            try {
                if (!empty($user->wp_user_id)) {
                    $companyMemberRepo->deleteCron($user->wp_user_id);
                    error_log("Deleted company member for: {$user->wp_user_id}");
                }
            } catch (Exception $e) {
                error_log("Delete company member error: " . $e->getMessage());
            }

            try {
                if ($companyId > 0) {
                    $companyRepo->deleteCron($companyId);
                    error_log("Deleted company for: {$companyId}");
                }
            } catch (Exception $e) {
                error_log("Delete company error: " . $e->getMessage());
            }
            
            if (!empty($user->wp_user_id)) {
                try {
                    WpUserService::instance()->delete($user->wp_user_id);
                    error_log("Deleted WP user: {$user->wp_user_id}");
                } catch (Exception $e) {
                    error_log("Delete WP user error: " . $e->getMessage());
                }
            }

            $deleted = $wpdb->delete($this->table, ['id' => $user->id]);

            if ($deleted === false) {
                error_log("Delete DB user failed: " . $wpdb->last_error);
            } else {
                error_log("Deleted DB user ID: {$user->id}");
            }
        }

        error_log("=== CLEANUP DONE ===");
        echo "Cleanup done\n";
    }

        public function updateStatus($userId, $status)
        {
        global $wpdb;

        return $wpdb->update(
            $this->table,
            ['status' => $status],
            ['wp_user_id' => $userId]
        );
    }
}