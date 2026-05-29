<?php

if (!defined('ABSPATH')) {
    exit;
}

class SellerRequestValidator
{
    public static function validate(
        array $data,
        array $files = []
    ): void {



        if (
            empty($data['representative_name'])
        ) {
            throw new Exception(
                'Thiếu họ tên người đại diện'
            );
        }

        if (
            empty($data['citizen_id_number'])
        ) {
            throw new Exception(
                'Thiếu số CCCD'
            );
        }

        if (
            strlen(
                trim($data['citizen_id_number'])
            ) < 9
        ) {
            throw new Exception(
                'Số CCCD không hợp lệ'
            );
        }

        if (
            empty($data['company_name'])
        ) {
            throw new Exception(
                'Thiếu thông tin company_name'
            );
        }

        if (
            empty($data['tax_code'])
        ) {
            throw new Exception(
                'Thiếu thông tin tax_code'
            );
        }

        if (
            !array_key_exists('address', $data) ||
            empty(trim((string)$data['address']))
        ) {
            throw new Exception(
                'Thiếu thông tin address'
            );
        }


        if (
            !empty($data['company_email']) &&
            !filter_var(
                $data['company_email'],
                FILTER_VALIDATE_EMAIL
            )
        ) {
            throw new Exception(
                'Email công ty không hợp lệ'
            );
        }
        if (
            empty($data['bank_name'])
        ) {
            throw new Exception(
                'Thiếu tên ngân hàng seller'
            );
        }

        if (
            empty($data['bank_account'])
        ) {
            throw new Exception(
                'Thiếu số tài khoản seller'
            );
        }


        $requiredFiles = [

            'citizen_front',
            'citizen_back',
            'business_license',
            'inspection_certificate'
        ];

        foreach ($requiredFiles as $fileKey) {

            if (
                empty($files[$fileKey]) ||
                empty($files[$fileKey]['tmp_name'])
            ) {

                throw new Exception(
                    "Thiếu file {$fileKey}"
                );
            }
        }


        if (
            !empty($files['company_logo']) &&
            empty($files['company_logo']['tmp_name'])
        ) {
            throw new Exception(
                'File company_logo không hợp lệ'
            );
        }
    }
}