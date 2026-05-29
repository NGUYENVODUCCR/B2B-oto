<?php

class UserController {

    public function me($request) {
        try {
            $data = RequestHelper::params($request);
            $userId = UserValidator::userId($data, $request);

            $service = new UserService();
            $user = $service->getUserById($userId);

            if (!$user) {
                return ResponseHelper::error('Thành viên không tồn tại trong hệ thống', 404);
            }

            return ResponseHelper::success($user);

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 400);
        }
    }

    public function updateProfile($request) {
        try {
            $data = RequestHelper::jsonOrFormParams($request);
            $userId = UserValidator::userId($data, $request);
            $displayName = UserValidator::displayName($data, $request);
            $updateData = UserValidator::updateData($data, $request);

            $files = RequestHelper::fileParams($request);
            if (!empty($files['avatar']) && isset($files['avatar']['name']) && (int) $files['avatar']['error'] === 0) {
                require_once __DIR__ . '/../Helpers/UploadHelper.php';

                $upload = UploadHelper::uploadImage($files['avatar']['tmp_name']);

                if (empty($upload) || empty($upload['url'])) {
                    return ResponseHelper::error('Upload ảnh Cloudinary thất bại', 400);
                }

                $avatarUrl = $upload['url'];
                $updateData['user_avatar'] = $avatarUrl;
            }

            if (!empty($updateData)) {
                $service = new UserService();
                $profileResult = $service->updateProfile($userId, $updateData); 
                
                if ($profileResult) {
                    return ResponseHelper::success([
                        'message' => 'Cập nhật tài khoản thành công.',
                        'user_avatar' => isset($profileResult['user_avatar']) ? $profileResult['user_avatar'] : (isset($avatarUrl) ? $avatarUrl : null),
                        'display_name' => $displayName,
                        'details' => $profileResult
                    ]);
                }
            }

            return ResponseHelper::error('Yêu cầu cập nhật bị từ chối do gói tin truyền lên trống hoặc không đổi dữ liệu.', 400);

        } catch (Exception $e) {
            return ResponseHelper::error('Lỗi xử lý hệ thống ngầm: ' . $e->getMessage(), 500);
        }
    }

    public function capabilities($request) {
        try {
            $data = RequestHelper::params($request);
            $userId = UserValidator::userId($data, $request);

            $service = new UserService();
            $capabilities = $service->getCapabilitiesByUserId($userId);

            return ResponseHelper::success($capabilities);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 400);
        }
    }
}
