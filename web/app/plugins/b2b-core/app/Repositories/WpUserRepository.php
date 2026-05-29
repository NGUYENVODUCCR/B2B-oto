<?php

if (!defined('ABSPATH')) {
    exit;
}

class WpUserRepository
{
    public function updateAvatarColumn($userId, string $avatarUrl): bool
    {
        global $wpdb;

        if ((int) $userId <= 0 || $avatarUrl === '') {
            return false;
        }

        $result = $wpdb->update(
            $wpdb->users,
            ['user_avatar' => $avatarUrl],
            ['ID' => (int) $userId],
            ['%s'],
            ['%d']
        );

        return $result !== false;
    }

    public function rawRowById($userId)
    {
        global $wpdb;

        if ((int) $userId <= 0) {
            return null;
        }

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->users} WHERE ID = %d LIMIT 1",
                (int) $userId
            )
        );
    }
}
