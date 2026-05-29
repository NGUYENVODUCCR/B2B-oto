<?php

if (!defined('ABSPATH')) {
    exit;
}

class PaymentValidator
{
    public static function orderId(array $data): int
    {
        $id = (int) ($data['order_id'] ?? 0);

        if ($id <= 0) {
            throw new Exception('Thiếu order_id');
        }

        return $id;
    }

    public static function paymentId(array $data): int
    {
        $id = (int) ($data['payment_id'] ?? ($data['id'] ?? 0));

        if ($id <= 0) {
            throw new Exception('Thiếu payment_id');
        }

        return $id;
    }

    public static function companyId(array $data = []): int
    {
        return RequestHelper::companyId($data);
    }

    public static function assertAdminOrSupport(string $message): void
    {
        PermissionHelper::assertAdminOrSupport($message);
    }

    public static function limit(array $data, int $default = 200): int
    {
        $limit = (int) ($data['limit'] ?? $default);
        return $limit > 0 ? $limit : $default;
    }
}
