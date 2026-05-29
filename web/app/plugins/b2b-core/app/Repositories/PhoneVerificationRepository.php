<?php

class  PhoneVerificationRepository {

    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'b2b_phone_verifications';
    }

    public function create($phone, $otp, $expiredAt) {
        global $wpdb;

        $result = $wpdb->insert($this->table, [
            'phone' => $phone,
            'otp' => password_hash($otp, PASSWORD_DEFAULT),
            'expired_at' => $expiredAt
        ]);

        if ($result === false) {
            throw new Exception("Lưu OTP thất bại: " . $wpdb->last_error);
        }

        return $wpdb->insert_id;
    }

    public function findLatestByPhone($phone) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table}
                 WHERE phone = %s
                 ORDER BY id DESC
                 LIMIT 1",
                $phone
            )
        );
    }

    public function deleteOtp($phone) {
        global $wpdb;

        $result = $wpdb->delete($this->table, ['phone' => $phone]);

        if ($result === false) {
            throw new Exception("Xóa OTP thất bại");
        }

        return true;
    }

    public function deleteExpired() {
        global $wpdb;

        $result = $wpdb->query(
            "DELETE FROM {$this->table} WHERE expired_at < NOW()"
        );

        if ($result === false) {
            throw new Exception("Xóa OTP phone thất bại: " . $wpdb->last_error);
        }

        return $result;
    }
}