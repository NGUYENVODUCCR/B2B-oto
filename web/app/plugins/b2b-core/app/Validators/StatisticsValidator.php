<?php

if (!defined('ABSPATH')) {
    exit;
}

class StatisticsValidator
{
    public static function revenueFilters(array $data): array
    {
        $companyId = isset($data['company_id']) ? (int) $data['company_id'] : 0;
        $sellerCompanyId = isset($data['seller_company_id']) ? (int) $data['seller_company_id'] : 0;
        $sellerId = isset($data['seller_id']) ? (int) $data['seller_id'] : 0;

        $dateFrom = TextHelper::clean($data['date_from'] ?? $data['from'] ?? '');
        $dateTo = TextHelper::clean($data['date_to'] ?? $data['to'] ?? '');

        return [
            'company_id' => $companyId,
            'seller_id' => $sellerId,
            'seller_company_id' => $sellerCompanyId > 0 ? $sellerCompanyId : ($sellerId > 0 ? $sellerId : $companyId),
            'brand' => TextHelper::clean($data['brand'] ?? ''),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'from' => $dateFrom,
            'to' => $dateTo,
            'group_by' => TextHelper::clean($data['group_by'] ?? ''),
        ];
    }
}
