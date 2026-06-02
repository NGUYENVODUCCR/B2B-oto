<?php

class BuyerRegisterValidator {

    public static function validate($data) {

        $required = ['name', 'email', 'password', 'confirm_password', 'phone'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || trim($data[$field]) === '') {
                throw new Exception("Thiếu trường: $field");
            }
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || !str_ends_with($data['email'], '@gmail.com')) {
            throw new Exception("Email không hợp lệ (trang web này chỉ hỗ trợ email truyền thống dạng @gmail.com)");
        }

        if (strlen($data['name']) < 3 || strlen($data['name']) > 50) {
            throw new Exception("Tên đầy đủ tối thiểu 3 kí tự, tối đa 50 kí tự");
        }

        $pwd = $data['password'];
        if (strlen($pwd) < 8) {
            throw new Exception("Mật khẩu tối thiểu phải đủ 8 ký tự");
        }
        if (!preg_match('/[A-Z]/', $pwd) || !preg_match('/[a-z]/', $pwd) || 
            !preg_match('/[0-9]/', $pwd) || !preg_match('/[^A-Za-z0-9]/', $pwd)) {
            throw new Exception("Mật khẩu phải bao gồm chữ hoa, chữ thường, số và ký tự đặc biệt");
        }

        if ($pwd !== $data['confirm_password']) {
            throw new Exception("Mật khẩu không khớp");
        }

        if (!preg_match('/^[0-9]{9,11}$/', $data['phone'])) {
            throw new Exception("Số điện thoại không hợp lệ");
        }
    }
}