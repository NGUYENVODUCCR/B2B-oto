<?php

class UserValidator
{
    public static function validateLogin($data)
    {
        if (empty($data['phone'])) {
            throw new Exception('Vui lòng nhập số điện thoại');
        }

        if (empty($data['password'])) {
            throw new Exception('Vui lòng nhập mật khẩu');
        }
    }

    public static function userId(array $data = [], $request = null): int
    {
        $userId = RequestHelper::currentUserId();

        if ($userId <= 0) {
            $userId = (int) ($data['auth_user_id'] ?? ($request ? RequestHelper::getParam($request, 'auth_user_id', 0) : 0));
        }

        if ($userId <= 0) {
            throw new Exception('Không tìm thấy mã ID người dùng. Phiên đăng nhập hợp lệ?', 401);
        }

        return $userId;
    }

    public static function displayName(array $data, $request = null): string
    {
        $displayName = isset($data['display_name'])
            ? TextHelper::clean($data['display_name'])
            : TextHelper::clean($request ? RequestHelper::getParam($request, 'display_name', '') : '');

        return $displayName === 'Đang tải...' ? '' : $displayName;
    }

    public static function updateData(array $data, $request = null): array
    {
        $updateData = [];
        $displayName = self::displayName($data, $request);

        if ($displayName !== '') {
            $updateData['display_name'] = $displayName;
        }

        return $updateData;
    }
}
