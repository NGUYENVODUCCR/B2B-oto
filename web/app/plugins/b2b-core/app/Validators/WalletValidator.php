<?php

if (!defined('ABSPATH')) {
    exit;
}

class WalletValidator
{
    public static function companyId(array $data): int
    {
        $companyId = RequestHelper::companyId($data);

        if (empty($companyId)) {
            throw new Exception('Tài khoản chưa gắn công ty');
        }

        return (int) $companyId;
    }

    public static function optionalCompanyId(array $data): int
    {
        return RequestHelper::companyId($data);
    }
}
