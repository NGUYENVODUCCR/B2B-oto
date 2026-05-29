<?php

if (!defined('ABSPATH')) {
    exit;
}

class QuotationValidator
{
    public static function rfqId(array $data): int
    {
        $id = (int) ($data['rfq_id'] ?? ($data['id'] ?? 0));

        if ($id <= 0) {
            throw new Exception('Thiếu rfq_id');
        }

        return $id;
    }

    public static function quotationId(array $data): int
    {
        $id = (int) ($data['quotation_id'] ?? ($data['id'] ?? 0));

        if ($id <= 0) {
            throw new Exception('Thiếu quotation_id');
        }

        return $id;
    }

    public static function sellerCompanyId(array $data): int
    {
        $id = RequestHelper::companyId($data, ['seller_company_id', 'company_id']);

        if ($id <= 0) {
            throw new Exception('Thiếu seller_company_id');
        }

        return $id;
    }
}
