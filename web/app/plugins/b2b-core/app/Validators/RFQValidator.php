<?php

class RFQValidator
{
    public static function validate($data)
    {
        if (empty($data['buyer_company_id'])) {
            throw new Exception('Thiếu buyer_company_id');
        }

        if (empty($data['items']) && empty($data['product_id'])) {
            throw new Exception('RFQ chưa có sản phẩm');
        }

        if (!empty($data['items']) && !is_array($data['items'])) {
            throw new Exception('items phải là array');
        }

        $items = !empty($data['items']) && is_array($data['items'])
            ? $data['items']
            : [[
                'product_id' => $data['product_id'] ?? 0,
                'quantity' => $data['quantity'] ?? 1,
            ]];

        foreach ($items as $item) {
            if (empty($item['product_id'])) {
                throw new Exception('Thiếu product_id');
            }

            if (empty($item['quantity'])) {
                throw new Exception('Thiếu quantity');
            }

            if ((int) $item['quantity'] <= 0) {
                throw new Exception('Quantity không hợp lệ');
            }
        }
    }

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

    public static function userId(array $data): int
    {
        $userId = RequestHelper::userId($data);

        if ($userId <= 0) {
            throw new Exception('Thiếu user_id');
        }

        return $userId;
    }
}
