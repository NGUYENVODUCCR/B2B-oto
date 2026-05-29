<?php

if (!defined('ABSPATH')) {
    exit;
}

class SupportValidator
{
    public static function ticketId(array $data): int
    {
        $id = (int) ($data['ticket_id'] ?? 0);

        if ($id <= 0) {
            throw new Exception('Thiếu ticket_id');
        }

        return $id;
    }

    public static function senderId(array $data): int
    {
        $id = RequestHelper::userId($data, ['sender_id', 'user_id', 'auth_user_id']);

        if ($id <= 0) {
            throw new Exception('Thiếu sender_id');
        }

        return $id;
    }

    public static function userId(array $data): int
    {
        $id = RequestHelper::userId($data, ['user_id', 'auth_user_id']);

        if ($id <= 0) {
            throw new Exception('Missing user_id');
        }

        return $id;
    }
}
