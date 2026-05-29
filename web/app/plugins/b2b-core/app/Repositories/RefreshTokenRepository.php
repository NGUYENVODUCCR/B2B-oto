<?php

class RefreshTokenRepository {

    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'b2b_refresh_tokens';
    }

    public function create($userId, $token, $expiredAt) {
        global $wpdb;

        $result = $wpdb->insert($this->table, [
            'user_id' => $userId,
            'token' => $token,
            'expired_at' => $expiredAt
        ]);

        if ($result === false) {
            throw new Exception("Lưu refresh token thất bại: " . $wpdb->last_error);
        }

        return $wpdb->insert_id;
    }

    public function find($token) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE token = %s LIMIT 1",
                $token
            )
        );
    }

    public function delete($token) {
        global $wpdb;

        $result = $wpdb->delete($this->table, ['token' => $token]);

        if ($result === false) {
            throw new Exception("Xóa refresh token thất bại");
        }

        return true;
    }

    public function deleteExpired() {
        global $wpdb;

        $wpdb->query(
            "DELETE FROM {$this->table} WHERE expired_at < NOW()"
        );
    }
}