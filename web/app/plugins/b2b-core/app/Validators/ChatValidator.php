<?php

if (!defined('ABSPATH')) {
    exit;
}

class ChatValidator
{
    public static function rfqId(array $data): int
    {
        $rfqId = (int) ($data['rfq_id'] ?? ($data['id'] ?? 0));

        if ($rfqId <= 0) {
            throw new Exception('Thiếu rfq_id');
        }

        return $rfqId;
    }

    public static function companyId(array $data): int
    {
        $companyId = RequestHelper::companyId($data);

        if ($companyId <= 0) {
            throw new Exception('Thiếu company_id');
        }

        return $companyId;
    }

    public static function optionalCompanyId(array $data): int
    {
        return RequestHelper::companyId($data);
    }

    public static function userId(array $data): int
    {
        $userId = RequestHelper::userId($data);

        if ($userId <= 0) {
            throw new Exception('Thiếu user_id');
        }

        return $userId;
    }

    public static function message(array $data): void
    {
        self::rfqId($data);
        self::companyId($data);
        self::userId($data);

        if (trim((string) ($data['message'] ?? '')) === '') {
            throw new Exception('Tin nhắn không được để trống');
        }
    }
}
