<?php

class PasswordResetRepository {

    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'b2b_password_resets';
    }


    public function create($email, $otp, $expiredAt) {
        global $wpdb;

        $result = $wpdb->insert($this->table, [
            'email' => $email,
            'otp' => password_hash($otp, PASSWORD_DEFAULT),
            'expired_at' => $expiredAt
        ]);

        if ($result === false) {
            throw new Exception("Không lưu được OTP: " . $wpdb->last_error);
        }

        return $wpdb->insert_id;
    }


    public function findLatestOtp($email) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} 
                 WHERE email = %s 
                 ORDER BY id DESC 
                 LIMIT 1",
                $email
            )
        );
    }


    public function findByOtp($otp) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE otp = %s LIMIT 1",
                $otp
            )
        );
    }

 
    public function deleteByOtp($otp) {
        global $wpdb;

        return $wpdb->delete(
            $this->table,
            ['otp' => $otp]
        );
    }

    public function deleteOtp($email) {
        global $wpdb;

        return $wpdb->delete(
            $this->table,
            ['email' => $email]
        );
    }

    public function deleteExpired() {
        global $wpdb;

        $result = $wpdb->query(
            "DELETE FROM {$this->table} WHERE expired_at < NOW()"
        );

        if ($result === false) {
            throw new Exception("Xóa OTP hết hạn thất bại: " . $wpdb->last_error);
        }

        return $result;
    }
}