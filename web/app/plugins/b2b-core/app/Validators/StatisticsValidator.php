<?php

if (!defined('ABSPATH')) {
    exit;
}

class StatisticsValidator
{
    public static function revenueFilters(array $data): array
    {
        return [
            'company_id' => isset($data['company_id']) ? (int) $data['company_id'] : 0,
            'seller_company_id' => isset($data['seller_company_id']) ? (int) $data['seller_company_id'] : 0,
            'from' => TextHelper::clean($data['from'] ?? ''),
            'to' => TextHelper::clean($data['to'] ?? ''),
            'group_by' => TextHelper::clean($data['group_by'] ?? ''),
        ];
    }
}
