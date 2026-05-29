<?php

if (!defined('ABSPATH')) {
    exit;
}

class PermissionHelper
{
    public static function currentRoles(): array
    {
        try {
            return self::normalizeRoles(AuthHelper::role());
        } catch (Throwable $e) {
            return [];
        }
    }

    public static function normalizeRoles($roles): array
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

    public static function hasAnyRole($roles, array $allowed): bool
    {
        $normalizedRoles = self::normalizeRoles($roles);
        $normalizedAllowed = self::normalizeRoles($allowed);

        return !empty(array_intersect($normalizedRoles, $normalizedAllowed));
    }

    public static function hasAdminRole($roles): bool
    {
        return self::hasAnyRole($roles, ['ROLE_ADMIN', 'ADMIN', 'ADMINISTRATOR']);
    }

    public static function hasSupportRole($roles): bool
    {
        return self::hasAnyRole($roles, ['ROLE_SUPPORT', 'SUPPORT']);
    }

    public static function hasAdminOrSupportRole($roles): bool
    {
        return self::hasAnyRole($roles, ['ROLE_ADMIN', 'ADMIN', 'ADMINISTRATOR', 'ROLE_SUPPORT', 'SUPPORT']);
    }

    public static function assertAdminOrSupport(string $message = 'Bạn không có quyền thực hiện hành động này.'): void
    {
        if (!self::hasAdminOrSupportRole(self::currentRoles())) {
            throw new Exception($message);
        }
    }

    private static function flattenRoles($value, array &$collector): void
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

        $role = strtoupper(trim((string) $value));
        if ($role === '') {
            return;
        }

        if ($role[0] === '[') {
            $decoded = json_decode($role, true);
            if (is_array($decoded)) {
                self::flattenRoles($decoded, $collector);
                return;
            }
        }

        $collector[] = $role;
    }

    private static function expandRoleAliases(string $role): array
    {
        $role = strtoupper(trim($role));
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
            $aliases = array_merge($aliases, ['ADMINISTRATOR', 'ADMIN', 'ROLE_ADMIN']);
        }

        if (in_array($role, ['SUPPORT', 'ROLE_SUPPORT'], true)) {
            $aliases = array_merge($aliases, ['SUPPORT', 'ROLE_SUPPORT']);
        }

        if (in_array($role, ['SELLER', 'ROLE_SELLER'], true)) {
            $aliases = array_merge($aliases, ['SELLER', 'ROLE_SELLER']);
        }

        if (in_array($role, ['BUYER', 'ROLE_BUYER'], true)) {
            $aliases = array_merge($aliases, ['BUYER', 'ROLE_BUYER']);
        }

        return array_values(array_unique(array_filter($aliases)));
    }
}
