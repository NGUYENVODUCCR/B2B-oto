<?php

class ProductValidator
{
    public static function validateCreate($data)
    {
        if (empty($data['name'])) {
            throw new Exception('Tên sản phẩm bắt buộc');
        }

        if (mb_strlen(trim($data['name'])) < 2) {
            throw new Exception('Tên sản phẩm quá ngắn');
        }

        if (empty($data['description'])) {
            throw new Exception('Mô tả bắt buộc');
        }

        if (mb_strlen(trim($data['description'])) < 10) {
            throw new Exception('Mô tả quá ngắn');
        }

        if (!isset($data['price_from'])) {
            throw new Exception('Giá sản phẩm bắt buộc');
        }

        if (!is_numeric($data['price_from']) || $data['price_from'] < 0) {
            throw new Exception('Giá không hợp lệ');
        }

        if (isset($data['years'])) {
            $years = (int) $data['years'];

            if (!is_numeric($data['years']) || $years < 1900 || $years > date('Y') + 1) {
                throw new Exception('Năm sản xuất không hợp lệ');
            }
        }

        if (isset($data['quantity'])) {
            if (!is_numeric($data['quantity']) || (int) $data['quantity'] < 0) {
                throw new Exception('Số lượng không hợp lệ');
            }
        }

        if (isset($data['status'])) {
            self::status($data['status']);
        }
    }

    public static function userId(array $data): int
    {
        $userId = RequestHelper::userId($data, ['auth_user_id']);

        if ($userId <= 0) {
            throw new Exception('Không tìm thấy mã định danh tài khoản người đăng.', 401);
        }

        return $userId;
    }

    public static function currentUserId(): int
    {
        $userId = RequestHelper::currentUserId();

        if ($userId <= 0) {
            throw new Exception('Chưa xác định được ID tài khoản.', 401);
        }

        return $userId;
    }

    public static function productId($value): int
    {
        $id = (int) $value;

        if ($id <= 0) {
            throw new Exception('Thiếu ID sản phẩm hợp lệ');
        }

        return $id;
    }

    public static function status($value): string
    {
        $status = strtolower(trim((string) $value));
        $allowedStatus = ['draft', 'active', 'inactive', 'blocked', 'deleted'];

        if (!in_array($status, $allowedStatus, true)) {
            throw new Exception('Status không hợp lệ');
        }

        return $status;
    }

    public static function keyword($value): string
    {
        return TextHelper::clean($value);
    }

    public static function filters(array $data): array
    {
        return [
            'brand'   => TextHelper::clean($data['brand'] ?? ''),
            'color'   => TextHelper::clean($data['color'] ?? ''),
            'years'   => TextHelper::clean($data['years'] ?? ''),
            'company' => TextHelper::clean($data['company'] ?? ''),
        ];
    }
}
