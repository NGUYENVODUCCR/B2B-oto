<?php

require_once __DIR__ . '/../Repositories/UserRepository.php';
require_once __DIR__ . '/../Repositories/PhoneVerificationRepository.php';
require_once __DIR__ . '/../Repositories/CompanyRepository.php';
require_once __DIR__ . '/../Repositories/CompanyMemberRepository.php';
require_once __DIR__ . '/../Repositories/UserRoleRepository.php';
require_once __DIR__ . '/../Repositories/RoleRepository.php';
require_once __DIR__ . '/../Repositories/AdminRepository.php';
require_once __DIR__ . '/WpUserService.php';

class AdminService
{
    private $userRepo;
    private $phoneVerifyRepo;
    private $companyRepo;
    private $companyMemberRepo;
    private $userRoleRepo;
    private $roleRepo;
    private $adminRepo;
    private $wpUserService;

    protected $allowed_roles = ['buyer', 'seller', 'support', 'admin'];

    public function __construct()
    {
        $this->userRepo = new UserRepository();
        $this->phoneVerifyRepo = new PhoneVerificationRepository();
        $this->companyRepo = new CompanyRepository();
        $this->companyMemberRepo = new CompanyMemberRepository();
        $this->userRoleRepo = new UserRoleRepository();
        $this->roleRepo = new RoleRepository();
        $this->adminRepo = new AdminRepository();
        $this->wpUserService = WpUserService::instance();
    }

    public function getUsers($params)
    {
        $paged = isset($params['page']) && (int) $params['page'] > 0 ? (int) $params['page'] : 1;
        $perPage = isset($params['per_page']) && (int) $params['per_page'] > 0 ? (int) $params['per_page'] : 10;
        $role = $this->normalizeRoleFilter($params['role'] ?? 'all');
        $offset = max(0, ($paged - 1) * $perPage);

        $totalUsers = $this->adminRepo->countUsers($role);
        $userIds = $this->adminRepo->pagedUserIds($role, $perPage, $offset);
        $data = [];
        $roleNameMapping = [1 => 'buyer', 2 => 'seller', 3 => 'support', 4 => 'admin'];

        foreach ($userIds as $id) {
            $userId = (int) $id;
            $userInfo = $this->adminRepo->wpUserBasic($userId);

            if (!$userInfo) {
                continue;
            }

            $formattedRoles = $this->formatRoleIds($this->adminRepo->roleIds($userId), $roleNameMapping);

            if (empty($formattedRoles)) {
                $formattedRoles = $this->fallbackWpRoles($userId, ['buyer']);
            }

            $b2bRow = $this->adminRepo->b2bUserByWpId($userId);
            $fullname = (!empty($b2bRow) && !empty($b2bRow->fullname)) ? $b2bRow->fullname : $userInfo->display_name;
            $phone = !empty($b2bRow) ? $b2bRow->phone : '';
            $status = !empty($b2bRow) ? $b2bRow->status : 'active';
            $isBanned = $this->wpUserService->getMeta($userId, 'is_banned', true);

            $data[] = [
                'id' => $userId,
                'company_id' => (int) ($this->companyMemberRepo->findCompanyId($userId) ?: 0),
                'username' => $userInfo->user_login,
                'email' => $userInfo->user_email,
                'fullname' => $fullname,
                'phone' => $phone,
                'roles' => $formattedRoles,
                'role_slug' => !empty($formattedRoles) ? $formattedRoles[0] : 'buyer',
                'is_banned' => (bool) $isBanned,
                'status' => $status,
                'registered' => $userInfo->user_registered,
            ];
        }

        return [
            'users' => $data,
            'pagination' => [
                'total' => (int) $totalUsers,
                'current_page' => $paged,
                'per_page' => $perPage,
                'pages' => (int) ceil($totalUsers / max(1, $perPage)),
            ],
        ];
    }

    public function createUser($data)
    {
        $name = TextHelper::clean($data['name'] ?? '');
        $email = TextHelper::email($data['email'] ?? '');
        $phoneRaw = TextHelper::clean($data['phone'] ?? '');
        $password = $data['password'] ?? '';
        $confirmPassword = $data['confirm_password'] ?? '';
        $roleInput = isset($data['role']) ? strtolower(TextHelper::clean($data['role'])) : 'buyer';
        $selectedCompanyId = isset($data['company_id']) ? (int) $data['company_id'] : 0;
        $companyName = TextHelper::clean($data['company_name'] ?? '');
        $taxCode = TextHelper::clean($data['tax_code'] ?? '');
        $address = TextHelper::textarea($data['address'] ?? '');

        if (!$email || !$name || !$password || !$phoneRaw) {
            throw new Exception('Thiếu dữ liệu bắt buộc (name, email, phone, password).', 400);
        }

        if ($password !== $confirmPassword) {
            throw new Exception('Mật khẩu nhập lại không khớp.', 400);
        }

        if (!in_array($roleInput, $this->allowed_roles, true)) {
            throw new Exception('Quyền phân bổ (role) không hợp lệ.', 400);
        }

        $phoneDb = $this->normalizePhoneForDb($phoneRaw);

        if ($this->userRepo->findByPhone($phoneDb)) {
            throw new Exception('Số điện thoại này đã được sử dụng trên hệ thống.');
        }

        if ($this->wpUserService->findByEmail($email)) {
            throw new Exception('Email này đã được sử dụng bởi một tài khoản khác.');
        }

        $wpUserId = $this->wpUserService->create([
            'user_login' => $phoneDb,
            'user_pass' => $password,
            'user_email' => $email,
            'display_name' => $name,
        ]);

        $this->wpUserService->updateMeta($wpUserId, 'phone', $phoneDb);

        $userId = $this->userRepo->create([
            'wp_user_id' => $wpUserId,
            'fullname' => $name,
            'phone' => $phoneDb,
            'status' => 'active',
            'phone_verified_at' => TimeHelper::mysql(),
        ]);

        $roleNameInDb = $this->roleNameForSlug($roleInput);
        $this->assignB2bRole($wpUserId, $roleNameInDb);

        if (in_array($roleInput, ['buyer', 'seller'], true)) {
            $companyContext = $this->prepareCompanyContext(
                $wpUserId,
                $name,
                $email,
                $roleInput,
                $selectedCompanyId,
                $companyName,
                $taxCode,
                $address
            );

            if ($roleInput === 'seller') {
                $this->ensureSellerRequest($wpUserId, $companyContext, $name, $email);
            }
        }

        $this->phoneVerifyRepo->deleteOtp($phoneDb);
        $otp = random_int(100000, 999999);
        $expired = TimeHelper::afterSeconds(600);
        $this->phoneVerifyRepo->create($phoneDb, $otp, $expired);
        error_log('Admin khởi tạo tài khoản - Mã OTP là: ' . $otp);

        return [
            'user_id' => $userId,
            'wp_user_id' => $wpUserId,
            'name' => $name,
            'phone' => $phoneDb,
            'role' => $roleNameInDb,
            'otp_debug' => $otp,
            'message' => 'Đăng ký tài khoản thành công, tài khoản đã kích hoạt trạng thái xác thực và đồng bộ môi trường.',
        ];
    }

    public function getCompanies()
    {
        return array_map(function ($row) {
            return [
                'id' => (int) ($row->id ?? 0),
                'company_name' => (string) ($row->company_name ?? ''),
                'tax_code' => (string) ($row->tax_code ?? ''),
            ];
        }, $this->adminRepo->verifiedCompanies());
    }

    public function updateUser($data)
    {
        $userIdInput = isset($data['user_id']) ? (int) $data['user_id'] : 0;

        if (!$userIdInput) {
            throw new Exception('Thiếu ID tài khoản cần chỉnh sửa.');
        }

        $wpUserId = $this->resolveWpUserId($userIdInput);

        if (!$wpUserId || !$this->wpUserService->findRawById($wpUserId)) {
            throw new Exception('Tài khoản không tồn tại trên hệ thống WordPress.');
        }

        $userdata = ['ID' => $wpUserId];

        if (isset($data['email'])) {
            $userdata['user_email'] = TextHelper::email($data['email']);
        }

        if (!empty($data['password'])) {
            $userdata['user_pass'] = $data['password'];
        }

        if (!empty($data['name'])) {
            $userdata['display_name'] = TextHelper::clean($data['name']);
        }

        $this->wpUserService->update($wpUserId, $userdata);

        if (!empty($data['name'])) {
            $this->adminRepo->updateB2bFullname($wpUserId, TextHelper::clean($data['name']));
        }

        if (!empty($data['role'])) {
            $roleClean = strtolower(TextHelper::clean($data['role']));

            if (!in_array($roleClean, $this->allowed_roles, true)) {
                throw new Exception('Quyền phân bổ (role) không hợp lệ.');
            }

            $this->wpUserService->setRole($wpUserId, $roleClean);
            $this->adminRepo->clearUserRoles($wpUserId);
            $this->assignB2bRole($wpUserId, $this->roleNameForSlug($roleClean));
        }

        $updatedWpUser = $this->wpUserService->findRawById($wpUserId);
        $b2bRow = $this->adminRepo->b2bUserByWpId($wpUserId);
        $formattedRoles = $this->formatRoleIds($this->adminRepo->roleIds($wpUserId), [1 => 'buyer', 2 => 'seller', 3 => 'support', 4 => 'admin']);

        return [
            'id' => $wpUserId,
            'username' => $updatedWpUser ? $updatedWpUser->user_login : '',
            'email' => $updatedWpUser ? $updatedWpUser->user_email : '',
            'fullname' => (!empty($b2bRow) && !empty($b2bRow->fullname)) ? $b2bRow->fullname : ($updatedWpUser ? $updatedWpUser->display_name : ''),
            'phone' => !empty($b2bRow) ? $b2bRow->phone : '',
            'roles' => $formattedRoles,
            'role_slug' => !empty($formattedRoles) ? $formattedRoles[0] : 'buyer',
            'status' => !empty($b2bRow) ? $b2bRow->status : 'active',
        ];
    }

    public function deleteUser($userIdInput)
    {
        $wpUserId = $this->resolveWpUserId((int) $userIdInput);

        if (!$wpUserId || !$this->wpUserService->findRawById($wpUserId)) {
            throw new Exception('Tài khoản không tồn tại trên hệ thống.', 404);
        }

        if ($this->wpUserService->currentId() === $wpUserId) {
            throw new Exception('Bạn không thể tự xóa tài khoản của chính mình.', 400);
        }

        if (!$this->adminRepo->softDeleteUser($wpUserId)) {
            throw new Exception('Lỗi hệ thống, không thể cập nhật trạng thái tài khoản.', 500);
        }

        return true;
    }

    public function toggleUserStatus($userId)
    {
        $userId = (int) $userId;

        if (!$userId || !$this->wpUserService->findRawById($userId)) {
            throw new Exception('Tài khoản không tồn tại.');
        }

        if ($this->wpUserService->currentId() === $userId) {
            throw new Exception('Bạn không thể tự khóa tài khoản của chính mình.');
        }

        $isBanned = (bool) $this->wpUserService->getMeta($userId, 'is_banned', true);
        $newStatus = !$isBanned;
        $this->wpUserService->updateMeta($userId, 'is_banned', $newStatus);
        $this->adminRepo->updateProductsStatusByUser($userId, $newStatus ? 'blocked' : 'active');

        return [
            'is_banned' => $newStatus,
            'message' => $newStatus
                ? 'Đã khóa tài khoản và toàn bộ sản phẩm.'
                : 'Đã mở khóa tài khoản và khôi phục sản phẩm.',
        ];
    }

    public function getSupportTeamList()
    {
        $wpUsers = $this->wpUserService->listUsers([
            'role__in' => ['support', 'administrator', 'admin'],
            'orderby' => 'display_name',
            'order' => 'ASC',
        ]);
        $result = [];
        $currentId = $this->wpUserService->currentId();

        foreach ($wpUsers as $wpUser) {
            if ((int) $wpUser->ID === $currentId) {
                continue;
            }

            $status = ($this->adminRepo->b2bUserByWpId((int) $wpUser->ID)->status ?? '') ?: 'active';
            $roleIds = array_map('intval', $this->adminRepo->roleIds((int) $wpUser->ID));
            $highestRole = $this->internalChatRole($roleIds, is_array($wpUser->roles ?? null) ? $wpUser->roles : []);

            if ($highestRole === '') {
                continue;
            }

            $result[] = (object) [
                'id' => (int) $wpUser->ID,
                'fullname' => $wpUser->display_name ?: $wpUser->user_login,
                'phone' => $this->wpUserService->getMeta($wpUser->ID, 'phone', true) ?: '',
                'status' => $status,
                'role' => $highestRole,
                'role_slug' => $highestRole,
            ];
        }

        return $result;
    }

    public function getSupportMessages($adminId, $supportId)
    {
        return $this->messagesWithSenderName($adminId, $supportId, 'as', 'Thành viên');
    }

    public function sendToSupport($adminId, $supportId, $message)
    {
        if ($supportId <= 0) {
            throw new Exception('support_id không hợp lệ.');
        }

        if (trim((string) $message) === '') {
            throw new Exception('Tin nhắn không được để trống.');
        }

        $conversationId = $this->conversationId($adminId, $supportId, 'as', 'no', 'Không thể tạo conversation.');
        $messageId = $this->adminRepo->createMessage($conversationId, $adminId, $supportId, $message);

        if (!$messageId) {
            throw new Exception('Không thể lưu tin nhắn.');
        }

        return [
            'conversation_id' => $conversationId,
            'message_id' => $messageId,
            'message' => 'Gửi tin nhắn thành công.',
        ];
    }

    public function sendToAdmin($senderId, $receiverId, $message)
    {
        if ($receiverId <= 0) {
            throw new Exception('receiver_id không hợp lệ.');
        }

        if (trim((string) $message) === '') {
            throw new Exception('Tin nhắn không được để trống.');
        }

        $conversationId = $this->conversationId($senderId, $receiverId, 'aa', 'yes', 'Không thể tạo room admin.');
        $messageId = $this->adminRepo->createMessage($conversationId, $senderId, $receiverId, $message);

        if (!$messageId) {
            throw new Exception('Không thể gửi tin nhắn.');
        }

        return [
            'conversation_id' => $conversationId,
            'message_id' => $messageId,
        ];
    }

    public function getAdminMessages($adminA, $adminB)
    {
        return $this->messagesWithSenderName($adminA, $adminB, 'aa', 'Quản trị viên');
    }

    private function normalizeRoleFilter($role): string
    {
        $role = strtolower(TextHelper::clean($role ?: 'all'));

        if ($role === '' || $role === 'undefined') {
            return 'all';
        }

        $aliases = [
            'administrator' => 'admin',
            'role_support' => 'support',
            'roles_support' => 'support',
            'customer' => 'buyer',
        ];

        return $aliases[$role] ?? $role;
    }

    private function formatRoleIds(array $roleIds, array $mapping): array
    {
        $roles = [];

        foreach ($roleIds as $roleId) {
            $roleId = (int) $roleId;
            if (isset($mapping[$roleId])) {
                $roles[] = $mapping[$roleId];
            }
        }

        return array_values(array_unique($roles));
    }

    private function fallbackWpRoles($userId, array $default = []): array
    {
        $wpUser = $this->wpUserService->findRawById($userId);

        if (!$wpUser || empty($wpUser->roles) || !is_array($wpUser->roles)) {
            return $default;
        }

        return array_map(function ($role) {
            return ($role === 'administrator' || $role === 'admin') ? 'admin' : $role;
        }, $wpUser->roles);
    }

    private function normalizePhoneForDb($phone)
    {
        $phone = preg_replace('/\D/', '', (string) $phone);

        if (strpos($phone, '84') === 0) {
            $phone = '0' . substr($phone, 2);
        }

        if (!preg_match('/^0\d{9}$/', $phone)) {
            throw new Exception('SĐT không hợp lệ');
        }

        return $phone;
    }

    private function roleNameForSlug(string $roleSlug): string
    {
        switch ($roleSlug) {
            case 'seller':
                return 'ROLE_SELLER';
            case 'support':
                return 'ROLE_SUPPORT';
            case 'admin':
                return 'ROLE_ADMIN';
            case 'buyer':
            default:
                return 'ROLE_BUYER';
        }
    }

    private function assignB2bRole(int $wpUserId, string $roleNameInDb): void
    {
        if (class_exists('B2B\\Helpers\\RoleHelper')) {
            \B2B\Helpers\RoleHelper::assignRole($wpUserId, $roleNameInDb);
        }
    }

    private function prepareCompanyContext($wpUserId, $name, $email, $roleInput, $selectedCompanyId, $companyName, $taxCode, $address): array
    {
        $companyId = 0;
        $metaCompany = [
            'bank_name' => 'Chưa cấu hình',
            'bank_account' => '0000000000',
            'citizen_id_number' => '000000000000',
            'documents' => json_encode([]),
        ];

        if ($selectedCompanyId > 0) {
            $company = $this->companyRepo->findById($selectedCompanyId);

            if ($company) {
                $companyId = (int) $company->id;
                $companyName = $company->company_name ?? '';
                $taxCode = $company->tax_code ?? '';
                $address = $company->address ?? '';
                $existingConfig = $this->adminRepo->verifiedSellerRequestConfig($companyId);

                if (!empty($existingConfig)) {
                    $metaCompany['bank_name'] = $existingConfig['bank_name'] ?? $metaCompany['bank_name'];
                    $metaCompany['bank_account'] = $existingConfig['bank_account'] ?? $metaCompany['bank_account'];
                    $metaCompany['citizen_id_number'] = $existingConfig['citizen_id_number'] ?? $metaCompany['citizen_id_number'];
                    $metaCompany['documents'] = $existingConfig['documents'] ?? $metaCompany['documents'];
                    $this->copyCloudinaryMeta((int) ($existingConfig['user_id'] ?? 0), $wpUserId);
                }
            }
        } elseif ($companyName !== '') {
            $companyId = $this->companyRepo->create([
                'company_name' => $companyName,
                'tax_code' => $taxCode ?: null,
                'address' => $address,
                'verification_status' => 'unverified',
            ]);
        }

        if ($companyId > 0 && !$this->companyMemberRepo->exists($companyId, $wpUserId)) {
            $this->companyMemberRepo->create([
                'company_id' => $companyId,
                'user_id' => $wpUserId,
                'company_role' => $roleInput,
            ]);
        }

        return [
            'company_id' => $companyId,
            'company_name' => $companyName,
            'tax_code' => $taxCode,
            'address' => $address,
            'meta' => $metaCompany,
        ];
    }

    private function copyCloudinaryMeta(int $oldWpUserId, int $newWpUserId): void
    {
        if ($oldWpUserId <= 0) {
            return;
        }

        $cloudinaryKeys = [
            'cloudinary_config',
            'cloudinary_url',
            'cloudinary_api_key',
            'cloudinary_api_secret',
            'cloudinary_cloud_name',
            'seller_cloudinary_settings',
        ];

        foreach ($cloudinaryKeys as $key) {
            $oldMetaVal = $this->wpUserService->getMeta($oldWpUserId, $key, true);
            if (!empty($oldMetaVal)) {
                $this->wpUserService->updateMeta($newWpUserId, $key, $oldMetaVal);
            }
        }
    }

    private function ensureSellerRequest($wpUserId, array $companyContext, $name, $email): void
    {
        $companyId = (int) ($companyContext['company_id'] ?? 0);

        if ($companyId <= 0 || $this->adminRepo->sellerRequestExists($wpUserId, $companyId)) {
            return;
        }

        $metaCompany = $companyContext['meta'] ?? [];

        $this->adminRepo->createSellerRequest([
            'user_id' => $wpUserId,
            'company_id' => $companyId,
            'company_name' => $companyContext['company_name'] ?: 'Công ty thành viên',
            'tax_code' => $companyContext['tax_code'] ?: null,
            'address' => $companyContext['address'] ?: null,
            'representative_name' => $name,
            'company_email' => $email,
            'bank_name' => $metaCompany['bank_name'] ?? 'Chưa cấu hình',
            'bank_account' => $metaCompany['bank_account'] ?? '0000000000',
            'citizen_id_number' => $metaCompany['citizen_id_number'] ?? '000000000000',
            'documents' => $metaCompany['documents'] ?? json_encode([]),
            'status' => 'verified',
            'reviewed_by' => $this->wpUserService->currentId() ?: 1,
            'reviewed_at' => TimeHelper::mysql(),
            'note' => 'Tài khoản được khởi tạo trực tiếp và kế thừa cấu hình từ Công ty đã kích hoạt.',
            'created_at' => TimeHelper::mysql(),
        ]);
    }

    private function resolveWpUserId(int $userIdInput): int
    {
        if ($userIdInput <= 0) {
            throw new Exception('Thiếu ID tài khoản cần xử lý.', 400);
        }

        if ($this->wpUserService->findRawById($userIdInput)) {
            return $userIdInput;
        }

        return $this->adminRepo->wpUserIdByB2bUserId($userIdInput);
    }

    private function internalChatRole(array $roleIds, array $wpRoles): string
    {
        if (!empty($roleIds)) {
            if (in_array(4, $roleIds, true)) {
                return 'admin';
            }

            if (in_array(3, $roleIds, true)) {
                return 'support';
            }

            return '';
        }

        if (in_array('administrator', $wpRoles, true) || in_array('admin', $wpRoles, true)) {
            return 'admin';
        }

        if (in_array('support', $wpRoles, true)) {
            return 'support';
        }

        return '';
    }

    private function messagesWithSenderName($userA, $userB, string $type, string $fallbackName): array
    {
        $conversation = $this->adminRepo->findConversationBetweenUsers($userA, $userB, $type);

        if (!$conversation) {
            return [];
        }

        $messages = $this->adminRepo->getConversationMessages($conversation->id);

        foreach ($messages as &$msg) {
            $msg->sender_name = $this->senderName((int) $msg->sender_id, $fallbackName);
        }

        return $messages;
    }

    private function senderName(int $senderId, string $fallbackName): string
    {
        $fullname = $this->adminRepo->fullnameByWpId($senderId);

        if ($fullname !== '') {
            return $fullname;
        }

        $wpUser = $this->wpUserService->findRawById($senderId);

        if ($wpUser) {
            return !empty($wpUser->display_name) ? $wpUser->display_name : $wpUser->user_login;
        }

        return $fallbackName;
    }

    private function conversationId($senderId, $receiverId, string $type, string $isSystem, string $createError): int
    {
        $conversation = $this->adminRepo->findConversationBetweenUsers($senderId, $receiverId, $type);

        if ($conversation) {
            return (int) $conversation->id;
        }

        $conversationId = $this->adminRepo->createConversation($type, $isSystem);

        if (!$conversationId) {
            throw new Exception($createError);
        }

        return (int) $conversationId;
    }
}
