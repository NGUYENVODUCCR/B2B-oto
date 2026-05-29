<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/../Repositories/WpUserRepository.php';

class WpUserService
{
    private $wpUserRepository;

    public function __construct()
    {
        $this->wpUserRepository = new WpUserRepository();
    }
    public static function instance(): self
    {
        static $instance = null;

        if (!$instance) {
            $instance = new self();
        }

        return $instance;
    }

    public function findById($id)
    {
        $user = $this->findRawById($id);

        if (!$user) {
            return null;
        }

        return [
            'ID' => $user->ID,
            'user_login' => $user->user_login,
            'user_email' => $user->user_email,
            'display_name' => $user->display_name,
            'user_avatar' => $user->user_avatar ?? null,
            'roles' => is_array($user->roles ?? null) ? array_values($user->roles) : []
        ];
    }

    public function findRawById($id)
    {
        if ((int) $id <= 0 || !function_exists('get_user_by')) {
            return null;
        }

        return get_user_by('id', (int) $id) ?: null;
    }

    public function exists($id): bool
    {
        return (bool) $this->findRawById($id);
    }

    public function findByPhone($phone)
    {
        return function_exists('get_user_by') ? get_user_by('login', $phone) : null;
    }

    public function findByEmail($email)
    {
        return function_exists('get_user_by') ? get_user_by('email', $email) : null;
    }

    public function create($data)
    {
        if (!function_exists('wp_insert_user')) {
            throw new Exception('WordPress user API chưa sẵn sàng.');
        }

        $userId = wp_insert_user($data);

        if (function_exists('is_wp_error') && is_wp_error($userId)) {
            throw new Exception('Tạo WP user thất bại: ' . $userId->get_error_message());
        }

        return (int) $userId;
    }

    public function checkPassword($password, $wpUser): bool
    {
        if (!$wpUser || !function_exists('wp_check_password')) {
            return false;
        }

        return (bool) wp_check_password($password, $wpUser->user_pass, $wpUser->ID);
    }

    public function updatePassword($userId, $newPassword): void
    {
        if (!function_exists('wp_set_password')) {
            throw new Exception('WordPress password API chưa sẵn sàng.');
        }

        wp_set_password($newPassword, $userId);
    }

    public function update($userId, $data)
    {
        if (!function_exists('wp_update_user')) {
            throw new Exception('WordPress user API chưa sẵn sàng.');
        }

        $data['ID'] = $userId;
        $result = wp_update_user($data);

        if (function_exists('is_wp_error') && is_wp_error($result)) {
            throw new Exception('Update WP user thất bại: ' . $result->get_error_message());
        }

        return $this->findRawById($userId);
    }

    public function delete($userId): bool
    {
        if (!function_exists('wp_delete_user') && defined('ABSPATH')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }

        if (!function_exists('wp_delete_user')) {
            return false;
        }

        return (bool) wp_delete_user((int) $userId);
    }

    public function currentId(): int
    {
        return function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
    }

    public function currentCan(string $capability): bool
    {
        return function_exists('current_user_can') ? (bool) current_user_can($capability) : false;
    }

    public function getMeta($userId, string $key, bool $single = true)
    {
        return function_exists('get_user_meta') ? get_user_meta((int) $userId, $key, $single) : null;
    }

    public function updateMeta($userId, string $key, $value)
    {
        if (!function_exists('update_user_meta')) {
            return false;
        }

        return update_user_meta((int) $userId, $key, $value);
    }


    public function addRole($userId, string $role): bool
    {
        $user = $this->findRawById($userId);

        if (!$user || !method_exists($user, 'add_role')) {
            return false;
        }

        $user->add_role($role);
        return true;
    }

    public function setRole($userId, string $role): bool
    {
        $user = $this->findRawById($userId);

        if (!$user || !method_exists($user, 'set_role')) {
            return false;
        }

        $user->set_role($role);
        return true;
    }

    public function roles($userId): array
    {
        $user = $this->findRawById($userId);

        if (!$user || empty($user->roles) || !is_array($user->roles)) {
            return [];
        }

        return array_values($user->roles);
    }

    public function listUsers(array $args = []): array
    {
        if (!function_exists('get_users')) {
            return [];
        }

        $users = get_users($args);
        return is_array($users) ? $users : [];
    }

    public function displayName($userId, string $default = ''): string
    {
        $user = $this->findRawById($userId);

        if (!$user) {
            return $default;
        }

        return $user->display_name ?: ($user->user_login ?: $default);
    }


    public function updateAvatarColumn($userId, string $avatarUrl): bool
    {
        return $this->wpUserRepository->updateAvatarColumn($userId, $avatarUrl);
    }

    public function rawRowById($userId)
    {
        return $this->wpUserRepository->rawRowById($userId);
    }

}
