<?php

if (!defined('ABSPATH')) {
    exit;
}

class RequestHelper
{
    public static function params($request): array
    {
        if (is_object($request) && method_exists($request, 'get_params')) {
            $params = $request->get_params();
            return is_array($params) ? $params : [];
        }

        return is_array($request) ? $request : (array) $request;
    }

    public static function jsonParams($request): array
    {
        if (is_object($request) && method_exists($request, 'get_json_params')) {
            $params = $request->get_json_params();
            return is_array($params) ? $params : [];
        }

        return [];
    }

    public static function queryParams($request): array
    {
        if (is_object($request) && method_exists($request, 'get_query_params')) {
            $params = $request->get_query_params();
            return is_array($params) ? $params : [];
        }

        return [];
    }

    public static function fileParams($request): array
    {
        if (is_object($request) && method_exists($request, 'get_file_params')) {
            $files = $request->get_file_params();
            if (!empty($files) && is_array($files)) {
                return $files;
            }
        }

        return !empty($_FILES) ? $_FILES : [];
    }

    public static function formParams($request): array
    {
        $params = self::params($request);
        $post = !empty($_POST) ? $_POST : [];

        return array_merge($params, $post);
    }

    public static function jsonOrFormParams($request): array
    {
        return array_merge(
            self::formParams($request),
            self::jsonParams($request)
        );
    }

    public static function getParam($request, string $key, $default = null)
    {
        if (is_object($request) && method_exists($request, 'get_param')) {
            $value = $request->get_param($key);
            return $value !== null ? $value : $default;
        }

        $params = self::params($request);
        return $params[$key] ?? $default;
    }

    public static function intValue(array $data, array $keys, int $default = 0): int
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && $data[$key] !== '') {
                return (int) $data[$key];
            }
        }

        return $default;
    }

    public static function currentUserId(): int
    {
        try {
            $id = AuthHelper::userId();
            if (!empty($id)) {
                return (int) $id;
            }
        } catch (Throwable $e) {
        }

        if (class_exists('WpUserService')) {
            return (int) WpUserService::instance()->currentId();
        }

        return 0;
    }

    public static function userId(array $data, array $fallbackKeys = ['user_id', 'auth_user_id']): int
    {
        $id = self::currentUserId();
        if ($id > 0) {
            return $id;
        }

        return self::intValue($data, $fallbackKeys);
    }

    public static function companyId(array $data, array $fallbackKeys = ['company_id', 'buyer_company_id']): int
    {
        try {
            $id = AuthHelper::companyId();
            if (!empty($id)) {
                return (int) $id;
            }
        } catch (Throwable $e) {
        }

        return self::intValue($data, $fallbackKeys);
    }

    public static function header(string $name): string
    {
        $headers = [];

        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        }

        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) === strtolower($name)) {
                return (string) $value;
            }
        }

        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return isset($_SERVER[$serverKey]) ? (string) $_SERVER[$serverKey] : '';
    }

    public static function cleanText($value): string
    {
        return class_exists('TextHelper')
            ? TextHelper::clean($value)
            : trim((string) $value);
    }
}
