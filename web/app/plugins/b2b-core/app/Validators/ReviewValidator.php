<?php

if (!defined('ABSPATH')) {
    exit;
}

class ReviewValidator
{
    public static function validateCreate(array $data): void
    {
        $orderId = (int) ($data['order_id'] ?? 0);
        $rating = (int) ($data['rating'] ?? 0);

        if ($orderId <= 0) {
            throw new Exception('Missing order_id');
        }

        if ($rating < 1 || $rating > 5) {
            throw new Exception('Rating must be between 1 and 5');
        }
    }

    public static function orderId(array $data): int
    {
        $orderId = (int) ($data['order_id'] ?? 0);

        if ($orderId <= 0) {
            throw new Exception('Missing order_id');
        }

        return $orderId;
    }
}
