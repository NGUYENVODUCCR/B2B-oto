<?php

class ResponseHelper {

    public static function success($data = [], $status = 200) {
        return new WP_REST_Response([
            'success' => true,
            'data' => $data
        ], $status);
    }

    public static function error($message = 'Error', $status = 400) {
        return new WP_REST_Response([
            'success' => false,
            'message' => $message
        ], $status);
    }
}