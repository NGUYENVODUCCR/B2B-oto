<?php

if (!defined('ABSPATH')) {
    exit;
}

class ContractValidator
{
    public static function quotationId(array $data): int
    {
        $id = (int) ($data['quotation_id'] ?? 0);

        if ($id <= 0) {
            throw new Exception('Thiếu quotation_id');
        }

        return $id;
    }

    public static function contractId(array $data): int
    {
        $id = (int) ($data['contract_id'] ?? ($data['id'] ?? 0));

        if ($id <= 0) {
            throw new Exception('Thiếu contract_id');
        }

        return $id;
    }
}
