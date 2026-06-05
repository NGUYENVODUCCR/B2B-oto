<?php

require_once __DIR__ . '/../Repositories/CompanyRepository.php';
require_once __DIR__ . '/../Repositories/CompanyMemberRepository.php';
require_once __DIR__ . '/../Repositories/SellerRequestRepository.php';
require_once __DIR__ . '/../Helpers/RoleHelper.php';
require_once __DIR__ . '/../Helpers/TextHelper.php';
require_once __DIR__ . '/WpUserService.php';

use B2B\Helpers\RoleHelper;

class UserService {

    private $userRepo;
    private $wpUserService;
    private $companyRepo;
    private $companyMemberRepo;
    private $sellerRequestRepo;

    public function __construct() {

        $this->userRepo = new UserRepository();

        $this->wpUserService = new WpUserService();

        $this->companyRepo = new CompanyRepository();

        $this->companyMemberRepo = new CompanyMemberRepository();

        $this->sellerRequestRepo = new SellerRequestRepository();
    }

    public function getUserById($id)
    {
        $userB2b = $this->userRepo->findByWpUserId($id);

        if (!$userB2b) {
            throw new Exception('User không tồn tại');
        }

        $userWp = $this->wpUserService
            ->findById($userB2b->wp_user_id);

        if (!$userWp) {
            throw new Exception('WP User không tồn tại');
        }

        unset($userWp->password);

        $roles = RoleHelper::getRoles(
            $userB2b->wp_user_id
        );

        $member = $this->companyMemberRepo
            ->findByUserId($userB2b->wp_user_id);

        $company = null;

        if ($member) {

            $company = $this->companyRepo
                ->findById($member->company_id);
        }

        $sellerRequest = $this->sellerRequestRepo
            ->findLatestByUserId(
                $userB2b->wp_user_id
            );

        return [

            'user' => [
                'id' => $userB2b->id,
                'fullname' => $userB2b->fullname,
                'phone' => $userB2b->phone,
                'status' => $userB2b->status,
                'phone_verified_at' =>
                    $userB2b->phone_verified_at,
            ],

            'wp_user' => [
                'id' => $userWp['ID'],
                'email' => $userWp['user_email'],
                'display_name' => $userWp['display_name'],
                'user_avatar' => $userWp['user_avatar'] ?? null,
                'wp_roles' => is_array($userWp['roles'] ?? null) ? $userWp['roles'] : [],
            ],

            'roles' => $roles,

            'wp_roles' => is_array($userWp['roles'] ?? null) ? $userWp['roles'] : [],

            'company' => $company,

            'company_member' => $member,

            'seller_request' => $sellerRequest
        ];
    }

    public function getCapabilitiesByUserId($id)
    {
        $userId = (int) $id;

        if ($userId <= 0) {
            throw new Exception('User ID is invalid');
        }

        $roleRows = RoleHelper::getRoles($userId);
        $roleNames = $this->normalizeRoleNames($roleRows);

        $wpRoles = $this->wpUserService->roles($userId);

        $roleNames = array_values(array_unique(array_merge(
            $roleNames,
            $this->normalizeRoleNames($wpRoles)
        )));

        $isAdmin = $this->hasRoleLike($roleNames, ['ADMIN', 'ADMINISTRATOR']);
        $isSupport = $this->hasRoleLike($roleNames, ['SUPPORT']);
        $isSeller = $this->hasRoleLike($roleNames, ['SELLER']);
        $isBuyer = $this->hasRoleLike($roleNames, ['BUYER']);
        $canManageSupportWorkspace = $isAdmin || $isSupport;
        $canOpenSellerChannel = $canManageSupportWorkspace || $isSeller;

        return [
            'roles' => $roleNames,
            'flags' => [
                'is_admin' => $isAdmin,
                'is_support' => $isSupport,
                'is_seller' => $isSeller,
                'is_buyer' => $isBuyer,
                'can_manage_support_workspace' => $canManageSupportWorkspace,
                'can_open_seller_channel' => $canOpenSellerChannel,
            ],
            'support_default_view' => $canManageSupportWorkspace ? 'workspace' : 'help',
        ];
    }

    private function normalizeRoleNames($rawRoles)
    {
        $roles = [];

        foreach ((array) $rawRoles as $raw) {
            if (is_object($raw)) {
                $name = isset($raw->role_name) ? $raw->role_name : (isset($raw->name) ? $raw->name : '');
            } elseif (is_array($raw)) {
                $name = $raw['role_name'] ?? ($raw['name'] ?? ($raw['slug'] ?? ($raw['role'] ?? '')));
            } else {
                $name = (string) $raw;
            }

            $name = strtoupper(trim((string) $name));

            if ($name === '') {
                continue;
            }

            $roles[] = $name;

            if (strpos($name, 'ROLE_') === 0) {
                $roles[] = substr($name, 5);
            } else {
                $roles[] = 'ROLE_' . $name;
            }
        }

        return array_values(array_unique(array_filter($roles)));
    }

    private function hasRoleLike($roles, $keywords)
    {
        $roles = (array) $roles;
        $keywords = array_map(function ($item) {
            return strtoupper(trim((string) $item));
        }, (array) $keywords);

        foreach ($roles as $role) {
            $roleValue = strtoupper(trim((string) $role));

            if ($roleValue === '') {
                continue;
            }

            foreach ($keywords as $keyword) {
                if ($keyword === '') {
                    continue;
                }

                if ($roleValue === $keyword || $roleValue === 'ROLE_' . $keyword || strpos($roleValue, $keyword) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function updateProfile($userId, $data)
    {
        $wpUserId = (int) $userId;

        if ($wpUserId <= 0) {
            throw new Exception('WP user ID không hợp lệ');
        }

        $updateDataB2b = [];
        $updateDataWp = [];

        if (!empty($data['phone']) || !empty($data['email'])) {
            throw new Exception('Email và số điện thoại là duy nhất (không thể sửa)');
        }

        if (
            !empty($data['status']) ||
            !empty($data['phone_verified_at']) ||
            !empty($data['created_at']) ||
            !empty($data['updated_at']) ||
            !empty($data['wp_user_id'])
        ) {
            throw new Exception('Trường hệ thống không thể tự cập nhật');
        }

        $profileName = !empty($data['display_name'])
            ? $data['display_name']
            : (!empty($data['name']) ? $data['name'] : '');

        if (!empty($profileName) && $profileName !== 'Đang tải...') {
            $cleanName = TextHelper::clean($profileName);
            $updateDataB2b['fullname'] = $cleanName;
            $updateDataWp['display_name'] = $cleanName;
        }

        $resultOfB2b = $this->userRepo->findByWpUserId($wpUserId);

        if (!$resultOfB2b) {
            throw new Exception('Không tìm thấy user B2B tương ứng với WP user ID: ' . $wpUserId);
        }

        if (!empty($updateDataB2b)) {
            $resultOfB2b = $this->userRepo->updateByWpUserId($wpUserId, $updateDataB2b);
        }

        if (!empty($updateDataWp)) {
            $this->wpUserService->update($wpUserId, $updateDataWp);
        }

        if (!empty($data['user_avatar'])) {
            $this->wpUserService->updateAvatarColumn($wpUserId, TextHelper::url($data['user_avatar']));
        }

        $resultOfB2b = $this->userRepo->findByWpUserId($wpUserId);
        $resultOfWp = $this->wpUserService->rawRowById($wpUserId);

        return [
            'user' => $resultOfB2b,
            'wp_user' => $resultOfWp,
            'user_avatar' => isset($resultOfWp->user_avatar) ? $resultOfWp->user_avatar : null,
            'display_name' => $resultOfWp->display_name ?? ($resultOfB2b->fullname ?? '')
        ];
    }
}
