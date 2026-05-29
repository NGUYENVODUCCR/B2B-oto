<?php

class AdminController {
    protected $adminService;
    protected $userRepo;
    protected $productRepo;
    protected $companyMemberRepo;

    public function __construct() {
        $this->adminService = new AdminService();
        $this->userRepo = new UserRepository();
        $this->productRepo = new ProductRepository();
        $this->companyMemberRepo = new CompanyMemberRepository();
    }

    private function validateAdmin() {
        return AdminValidator::assertAdmin();
    }

    private function validateInternalChat() {
        return AdminValidator::assertInternalChat();
    }

    public function getUsers($request) {
        try {
            $this->validateAdmin();
            $params = $this->params($request);
            $queryParams = RequestHelper::queryParams($request);

            foreach (['role', 'page', 'per_page'] as $key) {
                if (isset($queryParams[$key]) && $queryParams[$key] !== '') {
                    $params[$key] = $queryParams[$key];
                }
            }

            $result = $this->adminService->getUsers($params);

            return ResponseHelper::success([
                'users'      => isset($result['users']) ? $result['users'] : [],
                'pagination' => [
                    'total'        => isset($result['pagination']['total']) ? (int)$result['pagination']['total'] : 0,
                    'current_page' => isset($result['pagination']['current_page']) ? (int)$result['pagination']['current_page'] : 1,
                    'per_page'     => isset($result['pagination']['per_page']) ? (int)$result['pagination']['per_page'] : 10,
                    'pages'        => isset($result['pagination']['pages']) ? (int)$result['pagination']['pages'] : 1
                ]
            ], 'Lấy danh sách tài khoản thành công.');

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function createUser($request) {
        try {
            $this->validateAdmin();
            $data = $this->params($request);

            $result = $this->adminService->createUser($data);
            return ResponseHelper::success($result, 'Tạo tài khoản mới thành công.');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function getCompanies($request) {
        try {
            $this->validateAdmin();
            $result = $this->adminService->getCompanies();
            return ResponseHelper::success($result, 'Lấy danh sách công ty thành công.');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function updateUser($request) {
        try {
            $this->validateAdmin();
            $data = $this->params($request);

            $result = $this->adminService->updateUser($data);
            return ResponseHelper::success($result, 'Cập nhật tài khoản thành công.');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function deleteUser($request) {
        try {
            $this->validateAdmin();
            $data = array_merge($this->params($request), RequestHelper::jsonParams($request));
            $userId = (int) ($data['user_id'] ?? RequestHelper::getParam($request, 'user_id', 0));
            AdminValidator::userId(['user_id' => $userId]);

            $this->adminService->deleteUser($userId);

            return new WP_REST_Response([
                'success' => true,
                'message' => 'Tài khoản đã được chuyển sang trạng thái xóa (deleted).'
            ], 200);

        } catch (Exception $e) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }

    public function toggleUserStatus($request)
    {
        try {
            $this->validateAdmin();
            [$userId, $status] = AdminValidator::toggleStatus($this->params($request));

            $companyId = (int) $this->companyMemberRepo->findCompanyId($userId);
            $this->userRepo->updateStatus($userId, $status);

            if ($status === 'blocked' && $companyId > 0) {
                $this->productRepo->updateBlock($companyId, [
                    'status' => 'blocked'
                ]);
            }

            if ($status === 'active' && $companyId > 0) {
                $this->productRepo->updateActive($companyId, [
                    'status' => 'active'
                ]);
            }

            return ResponseHelper::success([
                'message' => 'Cập nhật user và sản phẩm thành công'
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function getSupportTeamList($request) {
        try {
            $this->validateInternalChat();
            $list = $this->adminService->getSupportTeamList();

            return ResponseHelper::success(
                $list,
                'Lấy danh sách chat nội bộ thành công.'
            );
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function getSupportMessages($request) {
        try {
            $adminId = $this->validateInternalChat();
            $data = $this->params($request);
            $supportId = AdminValidator::chatTarget($data, 'support_id', 'Thiếu tham số support_id.');

            $rawMessages = $this->adminService->getSupportMessages($adminId, $supportId);
            $formattedMessages = $this->formatInternalMessages($rawMessages, 'Thành viên');

            return ResponseHelper::success($formattedMessages, 'Lấy cuộc hội thoại chat thành công.');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function sendToSupport($request)
    {
        try {
            $adminId = $this->validateInternalChat();
            $data = $this->params($request);
            $supportId = AdminValidator::chatTarget($data, 'support_id', 'Thiếu support_id.');
            $message = AdminValidator::chatMessage($data);

            $result = $this->adminService->sendToSupport(
                $adminId,
                $supportId,
                $message
            );

            return ResponseHelper::success($result, 'Gửi tin nhắn thành công.');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function sendToAdmin($request) {
        try {
            $senderId = $this->validateInternalChat();
            $data = $this->params($request);
            $receiverId = AdminValidator::chatTarget($data, 'receiver_id', 'Thiếu receiver_id.');
            $message = AdminValidator::chatMessage($data);

            $result = $this->adminService->sendToAdmin(
                $senderId,
                $receiverId,
                $message
            );

            return ResponseHelper::success(
                $result,
                'Gửi tin nhắn admin thành công.'
            );
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function getAdminMessages($request) {
        try {
            $currentAdminId = $this->validateInternalChat();
            $data = $this->params($request);
            $targetAdminId = AdminValidator::chatTarget($data, 'admin_id', 'Thiếu admin_id.');

            $rawMessages = $this->adminService->getAdminMessages(
                $currentAdminId,
                $targetAdminId
            );

            $formattedMessages = $this->formatInternalMessages($rawMessages, 'Quản trị viên');

            return ResponseHelper::success(
                $formattedMessages,
                'Lấy hội thoại admin thành công.'
            );
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    private function params($request) {
        return RequestHelper::params($request);
    }

    private function formatInternalMessages($rawMessages, string $fallbackName): array
    {
        $formattedMessages = [];

        if (!empty($rawMessages)) {
            foreach ($rawMessages as $msg) {
                $formattedMessages[] = [
                    'id'              => (int)$msg->id,
                    'conversation_id' => (int)$msg->conversation_id,
                    'sender_id'       => (int)$msg->sender_id,
                    'receiver_id'     => (int)$msg->receiver_id,
                    'sender_name'     => isset($msg->sender_name) ? $msg->sender_name : $fallbackName,
                    'message'         => $msg->message,
                    'created_at'      => $msg->created_at
                ];
            }
        }

        return $formattedMessages;
    }
}
