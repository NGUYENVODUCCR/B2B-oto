<?php

namespace B2B\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

class RoleHelper
{
    public const BUYER = 'ROLE_BUYER';

    public const SELLER = 'ROLE_SELLER';

    public const SUPPORT = 'ROLE_SUPPORT';

    public const ADMIN = 'ROLE_ADMIN';

    protected static function rolesConfig(): array
    {
        return require B2B_PLUGIN_PATH . 'config/roles.php';
    }

    public static function getWpRole(
        string $roleCode
    ): ?string {

        $roles = self::rolesConfig();

        return $roles[$roleCode]['wp_role']
            ?? null;
    }

    public static function hasRole(
        int $userId,
        string $roleCode
    ): bool {

        $wpRole = self::getWpRole($roleCode);

        if (!$wpRole) {
            return false;
        }

        $roles = class_exists('\WpUserService')
            ? \WpUserService::instance()->roles($userId)
            : [];

        return in_array(
            $wpRole,
            $roles,
            true
        );
    }

    public static function assignRole(
        int $userId,
        string $roleCode
    ): bool {

        $wpRole = self::getWpRole($roleCode);

        if (!$wpRole) {
            return false;
        }

        if (!class_exists('\WpUserService') || !\WpUserService::instance()->addRole($userId, $wpRole)) {
            return false;
        }

        if (!class_exists('\RoleRepository') || !class_exists('\UserRoleRepository')) {
            return false;
        }

        $roleId = (new \RoleRepository())->idByName($roleCode);

        if (!$roleId) {
            return false;
        }

        return (new \UserRoleRepository())->assignRole($userId, $roleId);
    }

    public static function getRoles(
        int $userId
    ): array {

        if (!class_exists('\RoleRepository')) {
            return [];
        }

        return (new \RoleRepository())->namesByUserId($userId);
    }

    public static function getRoleLabel(
        string $roleCode
    ): string {

        $roles = self::rolesConfig();

        return $roles[$roleCode]['label']
            ?? $roleCode;
    }
}