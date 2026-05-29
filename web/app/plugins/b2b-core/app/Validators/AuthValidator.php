<?php

if (!defined('ABSPATH')) {
    exit;
}

class AuthValidator
{
    public static function validateVerify(array $data): void
    {
        if (empty($data['phone']) || empty($data['otp'])) {
            throw new Exception('Thiếu số điện thoại hoặc OTP');
        }
    }

    public static function validateRefreshToken(array $data): void
    {
        if (empty($data['refresh_token'])) {
            throw new Exception('Thiếu refresh token');
        }
    }

    public static function validateForgotPassword(array $data): void
    {
        if (empty($data['email'])) {
            throw new Exception('Thiếu email');
        }
    }

    public static function validateResetPassword(array $data): void
    {
        if (empty($data['email'])) {
            throw new Exception('Thiếu email');
        }

        if (empty($data['otp']) || empty($data['password'])) {
            throw new Exception('Thiếu OTP hoặc password');
        }
    }
}
