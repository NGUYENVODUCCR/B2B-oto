<?php

class AuthMiddleware {

    public static function handle($request) {

        $authHeader = $request->get_header('authorization');

        if (!$authHeader) {
            return new WP_Error('unauthorized', 'Thiếu token', ['status' => 401]);
        }

        $token = str_replace('Bearer ', '', $authHeader);

        try {

            $decoded = AuthService::verifyToken($token);

            if (!isset($decoded->user_id)) {
                throw new Exception('Invalid token');
            }

            $request->set_param('auth_user_id', $decoded->user_id);
            $request->set_param('auth_role', $decoded->role ?? null);

            return true;

        } catch (Throwable $e) {
            return new WP_Error('unauthorized', 'Token không hợp lệ', ['status' => 401]);
        }
    }
}
