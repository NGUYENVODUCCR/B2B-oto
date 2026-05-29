<?php

class BuyerRegisterValidator {

    public static function validate($data) {

        $required = ['name', 'email', 'password', 'confirm_password'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || trim($data[$field]) === '') {
                throw new Exception("Thiếu trường: $field");
            }
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Email không hợp lệ");
        }

        if (strlen($data['name']) < 3) {
            throw new Exception("name phải >= 3 ký tự");
        }

        if (strlen($data['name']) > 50) {
            throw new Exception("name tối đa 50 ký tự");
        }

        if (strlen($data['password']) < 6) {
            throw new Exception("Password phải >= 6 ký tự");
        }

        if ($data['password'] !== $data['confirm_password']) {
            throw new Exception("Mật khẩu không khớp");
        }

        if (!empty($data['phone'])) {
            if (!preg_match('/^[0-9]{9,11}$/', $data['phone'])) {
                throw new Exception("Số điện thoại không hợp lệ");
            }
        }
    }
}