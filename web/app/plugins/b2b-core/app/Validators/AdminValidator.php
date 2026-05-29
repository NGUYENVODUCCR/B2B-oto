<?php

if (!defined('ABSPATH')) {
    exit;
}

class AdminValidator
{
    public static function assertAdmin(): int
    {
        $userId = RequestHelper::currentUserId();

        if (!$userId) {
            throw new Exception('Yêu cầu không hợp lệ! Bạn chưa đăng nhập hoặc token hết hạn.', 401);
        }

        $wpUsers = WpUserService::instance();
        $user = $wpUsers->findRawById($userId);

        if (!$user) {
            throw new Exception('Tài khoản không tồn tại trên hệ thống.', 404);
        }

        $roles = array_merge(
            is_array($user->roles ?? null) ? $user->roles : [],
            (array) $wpUsers->getMeta($userId, 'roles', true)
        );

        if (!$wpUsers->currentCan('manage_options') && !PermissionHelper::hasAdminRole($roles)) {
            throw new Exception('Hành động bị từ chối! Bạn không có quyền quản trị.', 403);
        }

        return (int) $userId;
    }

    public static function assertInternalChat(): int
    {
        $userId = RequestHelper::currentUserId();

        if (!$userId) {
            throw new Exception('Yêu cầu không hợp lệ! Bạn chưa đăng nhập hoặc token hết hạn.', 401);
        }

        $wpUsers = WpUserService::instance();
        $user = $wpUsers->findRawById($userId);

        if (!$user) {
            throw new Exception('Tài khoản không tồn tại trên hệ thống.', 404);
        }

        $roles = array_merge(
            is_array($user->roles ?? null) ? $user->roles : [],
            (array) $wpUsers->getMeta($userId, 'roles', true)
        );

        if (!$wpUsers->currentCan('manage_options') && !PermissionHelper::hasAdminOrSupportRole($roles)) {
            throw new Exception('Hành động bị từ chối! Bạn không có quyền truy cập kênh nội bộ.', 403);
        }

        return (int) $userId;
    }

    public static function userId(array $data): int
    {
        $userId = (int) ($data['user_id'] ?? 0);

        if ($userId <= 0) {
            throw new Exception('Thiếu ID tài khoản cần xử lý.', 400);
        }

        return $userId;
    }

    public static function toggleStatus(array $data): array
    {
        $userId = self::userId($data);
        $status = (string) ($data['status'] ?? '');

        if (!in_array($status, ['active', 'blocked'], true)) {
            throw new Exception('Status không hợp lệ');
        }

        return [$userId, $status];
    }

    public static function chatTarget(array $data, string $key, string $message): int
    {
        $id = (int) ($data[$key] ?? 0);

        if ($id <= 0) {
            throw new Exception($message, 400);
        }

        return $id;
    }

    public static function chatMessage(array $data): string
    {
        $message = trim((string) ($data['message'] ?? ''));

        if ($message === '') {
            throw new Exception('Tin nhắn không được để trống.', 400);
        }

        return $message;
    }
}
