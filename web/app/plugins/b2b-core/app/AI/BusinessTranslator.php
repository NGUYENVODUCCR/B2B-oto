<?php

class BusinessTranslator
{
    public static function humanize($text)
    {
        $map = [

            'order' =>
                'đơn hàng',

            'quotation' =>
                'báo giá',

            'contract' =>
                'hợp đồng',

            'negotiation' =>
                'thương lượng',

            'payment' =>
                'thanh toán',

            'seller' =>
                'Người bán',

            'buyer' =>
                'Người mua',

            'status' =>
                'trạng thái',

        ];

        foreach ($map as $search => $replace) {

            $pattern =
                '/\b'
                . preg_quote($search, '/')
                . '\b/i';

            $text =
                preg_replace(
                    $pattern,
                    $replace,
                    $text
                );
        }

        return $text;
    }
}