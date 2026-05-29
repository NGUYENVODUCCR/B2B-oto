<?php

if (!defined('ABSPATH')) {
    exit;
}

class OrderValidator
{
    public static function contractId(array $data): int
    {
        $id = (int) ($data['contract_id'] ?? 0);

        if ($id <= 0) {
            throw new Exception('Thiếu contract_id');
        }

        return $id;
    }

    public static function orderId(array $data): int
    {
        $id = (int) ($data['order_id'] ?? ($data['id'] ?? 0));

        if ($id <= 0) {
            throw new Exception('Thiếu order_id');
        }

        return $id;
    }

    public static function companyId(array $data = []): int
    {
        $companyId = RequestHelper::companyId($data);

        if ($companyId <= 0) {
            throw new Exception('Thiếu company_id');
        }

        return $companyId;
    }
    
    public static function optionalCompanyId(array $data = []): int
    {   
        return RequestHelper::companyId($data);
    }
}
