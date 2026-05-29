<?php

require_once __DIR__ . '/../Services/AuthService.php';
require_once __DIR__ . '/../Repositories/CompanyMemberRepository.php';

class AuthHelper
{
    public static function getToken()
    {
        $headers = function_exists('getallheaders')
            ? getallheaders()
            : [];

        if (
            empty($headers) &&
            function_exists('apache_request_headers')
        ) {
            $headers = apache_request_headers();
        }

        $authHeader =
            $headers['Authorization']
            ?? $headers['authorization']
            ?? $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? null;

        if (!$authHeader) {

            throw new Exception(
                'Thiếu Authorization header'
            );
        }

        if (
            !preg_match(
                '/Bearer\s(\S+)/',
                $authHeader,
                $matches
            )
        ) {

            throw new Exception(
                'Token không hợp lệ'
            );
        }

        return $matches[1];
    }

    public static function user()
    {
        try {

            $token = self::getToken();

            $payload =
                AuthService::verifyToken(
                    $token
                );

            if (
                !$payload ||
                empty($payload->user_id)
            ) {

                throw new Exception(
                    'Token không hợp lệ'
                );
            }

            $companyId = null;

            if (class_exists('CompanyMemberRepository')) {
                $companyId = (new CompanyMemberRepository())->findCompanyId((int) $payload->user_id);
            }

            return [
                'id' => $payload->user_id,
                'company_id' => $companyId ? (int) $companyId : null,
            ];

        } catch (Throwable $e) {

            throw new Exception(
                'Token hết hạn hoặc không hợp lệ'
            );
        }
    }

    public static function userId()
    {
        $user = self::user();

        return $user['id'] ?? null;
    }

    public static function companyId()
    {
        $user = self::user();

        return $user['company_id'] ?? null;
    }

    public static function role()
    {
        $tokenRoles = [];
        $payloadUserId = 0;

        try {
            $token = self::getToken();
            $payload = AuthService::verifyToken($token);
            $payloadUserId = (int) ($payload->user_id ?? 0);

            if (!empty($payload->roles)) {
                $tokenRoles = self::normalizeRoles($payload->roles);
            }

            if (empty($tokenRoles) && !empty($payload->role)) {
                $tokenRoles = self::normalizeRoles([$payload->role]);
            }
        } catch (Throwable $e) {
            // Fallback below.
        }

        if ($payloadUserId <= 0) {
            $payloadUserId = class_exists('WpUserService')
                ? (int) WpUserService::instance()->currentId()
                : 0;
        }

        $wpRoles = self::wpRolesForUser($payloadUserId);
        $merged = array_values(array_unique(array_merge($tokenRoles, $wpRoles)));

        if (empty($merged)) {
            return '';
        }

        return count($merged) === 1
            ? $merged[0]
            : $merged;
    }

    private static function wpRolesForUser($userId)
    {
        if ((int) $userId <= 0 || !class_exists('WpUserService')) {
            return [];
        }

        $roles = WpUserService::instance()->roles((int) $userId);

        if (empty($roles)) {
            return [];
        }

        return self::normalizeRoles($roles);
    }

    private static function normalizeRoles($roles)
    {
        $flat = [];
        self::flattenRoles($roles, $flat);

        $expanded = [];

        foreach ($flat as $role) {
            foreach (self::expandRoleAliases($role) as $alias) {
                $expanded[$alias] = true;
            }
        }

        return array_keys($expanded);
    }

    private static function flattenRoles($value, &$collector)
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                self::flattenRoles($item, $collector);
            }

            return;
        }

        if (is_object($value)) {
            self::flattenRoles($value->role_name ?? ($value->name ?? ($value->slug ?? ($value->role ?? ''))), $collector);

            return;
        }

        $value = strtoupper(trim((string) $value));

        if ($value === '') {
            return;
        }

        if ($value[0] === '[') {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                self::flattenRoles($decoded, $collector);
                return;
            }
        }

        $collector[] = $value;
    }

    private static function expandRoleAliases($role)
    {
        $role = strtoupper(trim((string) $role));

        if ($role === '') {
            return [];
        }

        $aliases = [$role];

        if (strpos($role, 'ROLE_') === 0) {
            $aliases[] = substr($role, 5);
        } else {
            $aliases[] = 'ROLE_' . $role;
        }

        if (in_array($role, ['ADMINISTRATOR', 'ADMIN', 'ROLE_ADMIN'], true)) {
            $aliases[] = 'ADMINISTRATOR';
            $aliases[] = 'ADMIN';
            $aliases[] = 'ROLE_ADMIN';
        }

        if (in_array($role, ['SUPPORT', 'ROLE_SUPPORT'], true)) {
            $aliases[] = 'SUPPORT';
            $aliases[] = 'ROLE_SUPPORT';
        }

        if (in_array($role, ['SELLER', 'ROLE_SELLER'], true)) {
            $aliases[] = 'SELLER';
            $aliases[] = 'ROLE_SELLER';
        }

        if (in_array($role, ['BUYER', 'ROLE_BUYER'], true)) {
            $aliases[] = 'BUYER';
            $aliases[] = 'ROLE_BUYER';
        }

        return array_values(array_unique(array_filter($aliases)));
    }
}
