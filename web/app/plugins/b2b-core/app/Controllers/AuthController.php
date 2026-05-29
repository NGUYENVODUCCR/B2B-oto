<?php

class AuthController {

    public function register($request) {
        try {
            $data = RequestHelper::jsonParams($request);

            BuyerRegisterValidator::validate($data);

            $service = new AuthService();
            $service->register($data);

            return ResponseHelper::success([
                'message' => 'Đăng ký thành công, vui lòng xác thực OTP được gửi về sms của bạn'
            ]);

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 400);
        }
    }

    public function verify($request) {
        try {
            $data = RequestHelper::jsonParams($request);

            AuthValidator::validateVerify($data);

            $service = new AuthService();
            $service->verifyAccount($data['phone'], $data['otp']);

            return ResponseHelper::success([
                'message' => 'Xác thực thành công'
            ]);

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 400);
        }
    }

    public function login($request) {
        try {
            $data = RequestHelper::jsonParams($request);

            UserValidator::validateLogin($data);

            $service = new AuthService();
            $tokens = $service->login($data);

            return ResponseHelper::success($tokens);

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 401);
        }
    }

    public function refresh($request) {
        try {
            $data = RequestHelper::jsonParams($request);

            AuthValidator::validateRefreshToken($data);

            $service = new AuthService();
            $accessToken = $service->refreshToken($data['refresh_token']);

            return ResponseHelper::success([
                'access_token' => $accessToken
            ]);

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 401);
        }
    }

    public function logout($request) {
        try {
            $data = RequestHelper::jsonParams($request);

            AuthValidator::validateRefreshToken($data);

            $service = new AuthService();
            $service->logout($data['refresh_token']);

            return ResponseHelper::success([
                'message' => 'Đăng xuất thành công'
            ]);

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 400);
        }
    }

    public function forgotPassword($request) {
        try {
            $data = RequestHelper::jsonParams($request);

            AuthValidator::validateForgotPassword($data);

            $service = new AuthService();
            $service->forgotPasswordByEmail($data['email']);

            return ResponseHelper::success(['message' => 'OTP đã gửi']);

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 400);
        }
    }

    public function resetPassword($request) {
        try {
            $data = RequestHelper::jsonParams($request);

            AuthValidator::validateResetPassword($data);

            $service = new AuthService();
            $service->resetPasswordByEmail($data['email'], $data['otp'], $data['password']);

            return ResponseHelper::success("Đổi mật khẩu thành công");

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 400);
        }
    }
}
