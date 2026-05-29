<?php

class ProductController {
    private $service;

    public function __construct() {
        $this->service = new ProductService();
    }

    public function create($request) {
        try {
            $rawParams = RequestHelper::formParams($request);
            $userId = ProductValidator::userId($rawParams);
            $data = $rawParams;
            $data['status'] = ProductValidator::status($rawParams['status'] ?? 'active');

            $files = RequestHelper::fileParams($request);

            $result = $this->service->create(
                $userId,
                $data,
                $files
            );

            return ResponseHelper::success($result);

        } catch (Exception $e) {
            return ResponseHelper::error(
                'Lỗi xử lý đăng bài: ' . $e->getMessage(),
                400
            );
        }
    }


    public function myProducts($request) {
        try {
            $data = RequestHelper::params($request);
            $userId = ProductValidator::currentUserId();

            $result = $this->service->myProducts($userId);

            return ResponseHelper::success($result);

        } catch (Exception $e) {
            return ResponseHelper::success([]);
        }
    }

    public function detail($request) {
        try {
            $id = (int) RequestHelper::header('X-Product-Id');
            $id = ProductValidator::productId($id > 0 ? $id : RequestHelper::getParam($request, 'id', 0));

            $result = $this->service->detail($id);
            return ResponseHelper::success($result);

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 400);
        }
    }

    public function list() {
        try {
            $result = $this->service->allProducts();
            return ResponseHelper::success($result);
        } catch (Exception $e) {
            return ResponseHelper::error('Lỗi thực thi PHP: ' . $e->getMessage(), 400);
        }
    }

    public function delete($request) {
        try {
            $userId = RequestHelper::currentUserId();
            $productId = ProductValidator::productId(RequestHelper::getParam($request, 'id', 0));

            $currentProduct = $this->service->detail($productId);
            
            if (!empty($currentProduct['product']) && strtolower($currentProduct['product']->status) === 'draft') {
                return ResponseHelper::error('Hệ thống bảo mật: Bài viết ở trạng thái Bản nháp không được phép xóa!', 403);
            }

            $result = $this->service->delete($userId, $productId);
            return ResponseHelper::success($result);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 400);
        }
    }

    public function update($request) {
        try {
            $dataForUser = RequestHelper::jsonOrFormParams($request);
            $userId = RequestHelper::userId($dataForUser, ['auth_user_id']);

            $productId = ProductValidator::productId(RequestHelper::getParam($request, 'id', 0));
            if ($productId <= 0 && isset($_POST['id'])) {
                $productId = (int) $_POST['id'];
            }

            $updateData = RequestHelper::jsonOrFormParams($request);
            $files = RequestHelper::fileParams($request);

            if (!empty($files)) {
                $updateData['files'] = $files;
            }

            $result = $this->service->update($userId, $productId, $updateData);
            return ResponseHelper::success($result);

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 400);
        }
    }

    public function search($request)
    {
        try {
            $keyword = ProductValidator::keyword(RequestHelper::getParam($request, 'keyword', ''));

            $result = $this->service->searchProducts($keyword);

            return ResponseHelper::success($result);

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 400);
        }
    }

    public function filter($request)
    {
        try {
            $filters = ProductValidator::filters(RequestHelper::params($request));

            $result = $this->service->filterProducts($filters);

            return ResponseHelper::success($result);

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 400);
        }
    }
}
