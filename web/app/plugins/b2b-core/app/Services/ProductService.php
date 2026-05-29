<?php

require_once __DIR__ . '/../Repositories/ProductRepository.php';
require_once __DIR__ . '/../Repositories/ProductImageRepository.php';
require_once __DIR__ . '/../Repositories/CompanyMemberRepository.php';
require_once __DIR__ . '/../Validators/ProductValidator.php';
require_once __DIR__ . '/../Helpers/RoleHelper.php';
require_once __DIR__ . '/../Helpers/TextHelper.php';
require_once __DIR__ . '/../Helpers/TimeHelper.php';
require_once __DIR__ . '/../Repositories/SellerRequestRepository.php';
require_once __DIR__ . '/../Repositories/CompanyRepository.php';
require_once __DIR__ . '/WpUserService.php';

use B2B\Helpers\RoleHelper;

class ProductService
{
    private $productRepo;
    private $productImageRepo;
    private $companyMemberRepo;

    public function __construct()
    {
        $this->productRepo = new ProductRepository();
        $this->productImageRepo = new ProductImageRepository();
        $this->companyMemberRepo = new CompanyMemberRepository();
    }

    public function create($userId, $data, $files)
    {
        if (!RoleHelper::hasRole($userId, 'ROLE_SELLER')) {
            throw new Exception('Chỉ seller mới được đăng sản phẩm');
        }

        ProductValidator::validateCreate($data);

        $member = $this->companyMemberRepo->findByUserId($userId);

        if (!$member) {
            throw new Exception('Seller chưa thuộc công ty nào');
        }

        $status = !empty($data['status']) ? TextHelper::clean($data['status']) : 'active';

        if (!in_array($status, ['draft', 'active', 'inactive'], true)) {
            throw new Exception('Status không hợp lệ');
        }

        $productId = $this->productRepo->create([
            'company_id'   => $member->company_id,
            'name'         => TextHelper::clean($data['name']),
            'brand'        => isset($data['brand']) ? TextHelper::clean($data['brand']) : null,
            'color'        => isset($data['color']) ? TextHelper::clean($data['color']) : null,
            'description'  => TextHelper::textarea($data['description']),
            'price_from'   => $data['price_from'],
            'years'        => isset($data['years']) ? (int) $data['years'] : null,
            'quantity'     => isset($data['quantity']) ? (int) $data['quantity'] : 0,
            'status'       => $status,
        ]);

        $this->storeUploadedImages($productId, $files);

        return [
            'message' => 'Đăng sản phẩm thành công',
            'product_id' => $productId,
        ];
    }

    public function detail($id)
    {
        $product = $this->productRepo->findById($id);

        if (!$product) {
            throw new Exception('Sản phẩm không tồn tại');
        }

        $userId = AuthHelper::userId();
        $isAdmin = !empty($userId) && RoleHelper::hasRole($userId, 'ROLE_ADMIN');
        $isSupport = !empty($userId) && RoleHelper::hasRole($userId, 'ROLE_SUPPORT');

        if (
            isset($product->status) &&
            strtolower($product->status) === 'deleted' &&
            !$isAdmin &&
            !$isSupport
        ) {
            throw new Exception('Sản phẩm đã bị xóa');
        }

        $companyInfo = $this->defaultCompanyInfo();
        $companyId = isset($product->company_id) ? (int) $product->company_id : 0;

        if ($companyId > 0) {
            $companyInfo = $this->companyInfoFromSellerRequest(
                $this->productRepo->sellerRequestByCompanyId($companyId, true),
                $companyInfo
            );
        }

        if ($companyInfo['company_name'] === 'Doanh nghiệp đối tác') {
            $userIdOfCar = $this->productOwnerId($product);

            if ($userIdOfCar > 0) {
                $companyInfo = $this->companyInfoFromSellerRequest(
                    $this->productRepo->sellerRequestByUserId($userIdOfCar, true),
                    $companyInfo
                );
            }
        }

        return [
            'product' => $product,
            'images'  => $this->productImageRepo->findByProductId($id),
            'company' => $this->companyInfoObject($companyInfo),
        ];
    }

    public function list()
    {
        $products = $this->productRepo->allActive();
        $result = [];

        foreach ($products as $product) {
            $result[] = [
                'id'          => $this->valueOf($product, 'id', 0),
                'name'        => $this->valueOf($product, 'name', ''),
                'description' => $this->valueOf($product, 'description', ''),
                'price_from'  => $this->valueOf($product, 'price_from', 0),
                'years'       => $this->valueOf($product, 'years', ''),
                'quantity'    => $this->valueOf($product, 'quantity', 0),
                'status'      => $this->valueOf($product, 'status', ''),
                'company_id'  => $this->valueOf($product, 'company_id', 0),
                'brand_name'  => $this->brandNameForProduct($product),
                'color_name'  => $this->colorNameForProduct($product),
                'images'      => $this->imagePayload($this->productImageRepo->findByProductId($this->valueOf($product, 'id', 0))),
            ];
        }

        return $result;
    }

    public function allProducts()
    {
        $products = $this->productRepo->allProducts();

        if (empty($products)) {
            return [];
        }

        $result = [];

        foreach ($products as $product) {
            $companyInfo = $this->defaultCompanyInfo();
            $companyId = (int) $this->valueOf($product, 'company_id', 0);

            if ($companyId > 0) {
                $companyInfo = $this->companyInfoFromSellerRequest(
                    $this->productRepo->sellerRequestByCompanyId($companyId, false),
                    $companyInfo
                );
            }

            $productId = (int) $this->valueOf($product, 'id', 0);

            $result[] = [
                'id'           => $productId,
                'name'         => $this->valueOf($product, 'name', ''),
                'description'  => $this->valueOf($product, 'description', ''),
                'price_from'   => $this->valueOf($product, 'price_from', 0),
                'years'        => $this->valueOf($product, 'years', ''),
                'quantity'     => $this->valueOf($product, 'quantity', 0),
                'status'       => $this->valueOf($product, 'status', ''),
                'company_id'   => $companyId,
                'brand_name'   => $this->brandNameForProduct($product),
                'color_name'   => $this->colorNameForProduct($product),
                'company_info' => $this->companyInfoArray($companyInfo),
                'images'       => $this->imagePayload($this->productImageRepo->findByProductId($productId)),
            ];
        }

        return $result;
    }

    public function myProducts($userId)
    {
        if (empty($userId)) {
            return [];
        }

        $companyId = 0;
        $companyInfo = $this->defaultCompanyInfo();
        $requestRow = $this->productRepo->sellerRequestByUserId((int) $userId, false);

        if ($requestRow && strtolower(trim((string) ($requestRow->status ?? ''))) === 'verified') {
            $companyId = (int) ($requestRow->company_id ?? 0);
            $companyInfo = $this->companyInfoFromSellerRequest($requestRow, $companyInfo);
        }

        if ($companyId <= 0) {
            $member = $this->companyMemberRepo->findByUserId($userId);
            $companyId = ($member && !empty($member->company_id)) ? (int) $member->company_id : 0;
        }

        if ($companyId <= 0) {
            return [];
        }

        $products = $this->productRepo->findByCompanyId($companyId);

        if (empty($products)) {
            return [];
        }

        $result = [];
        $companyPayload = $this->companyInfoArray($companyInfo);

        foreach ($products as $product) {
            $productId = (int) $this->valueOf($product, 'id', 0);

            $result[] = [
                'id'           => $productId,
                'name'         => $this->valueOf($product, 'name', ''),
                'description'  => $this->valueOf($product, 'description', ''),
                'price_from'   => $this->valueOf($product, 'price_from', 0),
                'years'        => $this->valueOf($product, 'years', ''),
                'quantity'     => $this->valueOf($product, 'quantity', 0),
                'status'       => $this->valueOf($product, 'status', ''),
                'company_id'   => $companyId,
                'brand_name'   => $this->brandNameForProduct($product),
                'color_name'   => $this->colorNameForProduct($product),
                'company_info' => $companyPayload,
                'images'       => $this->imagePayload($this->productImageRepo->findByProductId($productId)),
            ];
        }

        return $result;
    }

    public function delete($userId, $productId)
    {
        $member = $this->companyMemberRepo->findByUserId($userId);

        if (!$member) {
            throw new Exception('Không tìm thấy công ty');
        }

        $product = $this->productRepo->findById($productId);

        if (!$product) {
            throw new Exception('Sản phẩm không tồn tại');
        }

        if ($product->company_id != $member->company_id) {
            throw new Exception('Không có quyền');
        }

        $this->productRepo->softDelete($productId);

        return ['message' => 'Xóa sản phẩm thành công'];
    }

    public function update($userId, $productId, $data)
    {
        $product = $this->productRepo->findById($productId);

        if (!$product) {
            throw new Exception('Sản phẩm không tồn tại');
        }

        $isAdmin = RoleHelper::hasRole($userId, 'ROLE_ADMIN');
        $isSupport = RoleHelper::hasRole($userId, 'ROLE_SUPPORT');
        $isSeller = RoleHelper::hasRole($userId, 'ROLE_SELLER');

        if (!$isAdmin && !$isSupport) {
            if (!$isSeller) {
                throw new Exception('Không có quyền sửa sản phẩm');
            }

            $member = $this->companyMemberRepo->findByUserId($userId);

            if (!$member) {
                throw new Exception('Không tìm thấy công ty');
            }

            if ($product->company_id != $member->company_id) {
                throw new Exception('Không có quyền sửa sản phẩm này');
            }

            if ($product->status === 'blocked') {
                throw new Exception('Sản phẩm đã bị khóa');
            }

            if ($product->status !== 'draft') {
                $cleanData = [];

                if (isset($data['status'])) {
                    if ($data['status'] !== 'inactive' && $data['status'] !== 'active') {
                        throw new Exception('Seller chỉ được chuyển đổi trạng thái giữa active và inactive');
                    }

                    $cleanData['status'] = $data['status'];
                }

                if (isset($data['quantity'])) {
                    $cleanData['quantity'] = $data['quantity'];
                }

                if (empty($cleanData)) {
                    throw new Exception('Seller chỉ được cập nhật số lượng xe hoặc thay đổi trạng thái ẩn/hiện đối với bài đã đăng.');
                }

                $data = $cleanData;
            }
        }

        $updateData = $this->buildProductUpdateData($data, $product, $isAdmin, $isSupport, $isSeller);

        if (empty($updateData)) {
            throw new Exception('Không có dữ liệu cập nhật');
        }

        $updateData['updated_at'] = TimeHelper::mysql();
        $this->productRepo->update($productId, $updateData);

        return ['message' => 'Cập nhật sản phẩm thành công'];
    }

    private function adminUpdateProduct($productId, $data)
    {
        $updateData = $this->buildProductUpdateData($data, (object) ['status' => 'draft'], true, true, false);

        if (empty($updateData)) {
            throw new Exception('Không có dữ liệu cập nhật');
        }

        $updateData['updated_at'] = TimeHelper::mysql();
        $this->productRepo->update($productId, $updateData);

        return ['message' => 'Cập nhật sản phẩm thành công'];
    }

    public function searchProducts($keyword)
    {
        $products = $this->productRepo->searchByKeyword($keyword);

        return $this->formatProducts($products);
    }

    public function filterProducts($filters = [])
    {
        $products = $this->productRepo->filterProducts($filters);

        return $this->formatProducts($products);
    }

    private function formatProducts($products)
    {
        $result = [];
        $products = is_array($products) ? $products : [];

        foreach ($products as $product) {
            $productId = (int) $this->valueOf($product, 'id', 0);

            if (!$productId) {
                continue;
            }

            $brand = $this->valueOf($product, 'brand', 'Chưa xác định');
            $color = $this->valueOf($product, 'color', 'Chưa rõ màu');
            $companyName = TextHelper::first(
                $this->valueOf($product, 'company_title', ''),
                $this->valueOf($product, 'company_name', ''),
                'Công ty ABC'
            );

            $result[] = [
                'id'           => $productId,
                'name'         => $this->valueOf($product, 'name', ''),
                'description'  => $this->valueOf($product, 'description', ''),
                'brand'        => TextHelper::clean($brand ?: 'Chưa xác định'),
                'color'        => TextHelper::clean($color ?: 'Chưa rõ màu'),
                'price_from'   => $this->valueOf($product, 'price_from', 0),
                'years'        => $this->valueOf($product, 'years', 'Chưa rõ năm'),
                'quantity'     => $this->valueOf($product, 'quantity', 0),
                'status'       => $this->valueOf($product, 'status', 'pending'),
                'company_id'   => $this->valueOf($product, 'company_id', 0),
                'company_name' => TextHelper::clean($companyName),
                'images'       => $this->imagePayload($this->productImageRepo->findByProductId($productId)),
            ];
        }

        return $result;
    }

    private function storeUploadedImages($productId, $files): void
    {
        if (empty($files['images'])) {
            return;
        }

        if (is_array($files['images']['name'])) {
            foreach ($files['images']['name'] as $index => $name) {
                $tmpFile = $files['images']['tmp_name'][$index];
                $upload = UploadHelper::uploadImage($tmpFile);
                $this->productImageRepo->create([
                    'product_id' => $productId,
                    'image_url'  => $upload['url'],
                ]);
            }

            return;
        }

        $upload = UploadHelper::uploadImage($files['images']['tmp_name']);
        $this->productImageRepo->create([
            'product_id' => $productId,
            'image_url'  => $upload['url'],
        ]);
    }

    private function buildProductUpdateData($data, $product, $isAdmin, $isSupport, $isSeller): array
    {
        $updateData = [];

        if (isset($data['name'])) {
            $name = trim((string) $data['name']);
            if ($name === '') {
                throw new Exception('Tên sản phẩm không được để trống');
            }
            $updateData['name'] = TextHelper::clean($name);
        }

        if (isset($data['brand'])) {
            $updateData['brand'] = TextHelper::clean($data['brand']);
        }

        if (isset($data['color'])) {
            $updateData['color'] = TextHelper::clean($data['color']);
        }

        if (isset($data['description'])) {
            $updateData['description'] = TextHelper::textarea($data['description']);
        }

        if (isset($data['price_from'])) {
            if (!is_numeric($data['price_from']) || $data['price_from'] < 0) {
                throw new Exception('Giá phải lớn hơn hoặc bằng 0');
            }
            $updateData['price_from'] = (float) $data['price_from'];
        }

        if (isset($data['years'])) {
            if (!is_numeric($data['years'])) {
                throw new Exception('Năm sản xuất không hợp lệ');
            }

            $years = (int) $data['years'];
            if ($years < 1900 || $years > (int) date('Y') + 1) {
                throw new Exception('Năm sản xuất không hợp lệ');
            }

            $updateData['years'] = $years;
        }

        if (isset($data['quantity'])) {
            if (!is_numeric($data['quantity'])) {
                throw new Exception('Số lượng không hợp lệ');
            }

            $quantity = (int) $data['quantity'];
            if ($quantity < 0) {
                throw new Exception('Số lượng không được âm');
            }

            $updateData['quantity'] = $quantity;
        }

        if (isset($data['status'])) {
            if (!in_array($data['status'], ['draft', 'active', 'inactive', 'blocked', 'deleted'], true)) {
                throw new Exception('Status không hợp lệ');
            }

            if ($data['status'] === 'blocked' && !$isAdmin && !$isSupport) {
                throw new Exception('Không có quyền khóa sản phẩm');
            }

            $updateData['status'] = $data['status'];
        } elseif ($this->valueOf($product, 'status', '') === 'draft' && $isSeller) {
            $updateData['status'] = 'active';
        }

        return $updateData;
    }

    private function productOwnerId($product): int
    {
        $userIdOfCar = (int) $this->valueOf($product, 'user_id', 0);

        if ($userIdOfCar <= 0) {
            $userIdOfCar = (int) $this->valueOf($product, 'seller_id', 0);
        }

        if ($userIdOfCar <= 0 && function_exists('getallheaders')) {
            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? ($headers['authorization'] ?? '');

            if (!empty($authHeader) && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
                $tokenParts = explode('.', $matches[1]);

                if (count($tokenParts) > 1) {
                    $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[1])));
                    $userIdOfCar = (int) ($payload->data->user->id ?? $payload->usr_id ?? $payload->sub ?? 0);
                }
            }
        }

        if ($userIdOfCar <= 0) {
            $userIdOfCar = (int) WpUserService::instance()->currentId();
        }

        return $userIdOfCar;
    }

    private function brandNameForProduct($product): string
    {
        $brandId = (int) $this->valueOf($product, 'brand_id', 0);

        if ($brandId > 0) {
            $name = $this->productRepo->brandNameById($brandId);
            return $name ?: 'Chưa rõ';
        }

        return TextHelper::first($this->valueOf($product, 'brand', ''), 'Chưa xác định');
    }

    private function colorNameForProduct($product): string
    {
        $colorId = (int) $this->valueOf($product, 'color_id', 0);

        if ($colorId > 0) {
            $name = $this->productRepo->colorNameById($colorId);
            return $name ?: 'Chưa rõ';
        }

        return TextHelper::first($this->valueOf($product, 'color', ''), 'Chưa xác định');
    }

    private function defaultCompanyInfo(): array
    {
        return [
            'company_name'        => 'Doanh nghiệp đối tác',
            'tax_code'            => 'Chưa cập nhật',
            'representative_name' => 'Chưa cập nhật',
            'address'             => 'Chưa cập nhật',
            'documents'           => '[]',
        ];
    }

    private function companyInfoFromSellerRequest($row, array $fallback): array
    {
        if (!$row) {
            return $fallback;
        }

        return [
            'company_name'        => TextHelper::first($row->company_name ?? '', $fallback['company_name']),
            'tax_code'            => TextHelper::first($row->tax_code ?? '', $fallback['tax_code']),
            'representative_name' => TextHelper::first($row->representative_name ?? '', $fallback['representative_name']),
            'address'             => TextHelper::first($row->address ?? '', $fallback['address']),
            'documents'           => TextHelper::first($row->documents ?? '', $fallback['documents']),
        ];
    }

    private function companyInfoObject(array $companyInfo): object
    {
        return (object) $this->companyInfoArray($companyInfo);
    }

    private function companyInfoArray(array $companyInfo): array
    {
        $documents = json_decode($companyInfo['documents']);

        return [
            'company_name'        => $companyInfo['company_name'],
            'tax_code'            => $companyInfo['tax_code'],
            'representative_name' => $companyInfo['representative_name'],
            'address'             => $companyInfo['address'],
            'documents'           => $documents ?: [],
        ];
    }

    private function imagePayload($images): array
    {
        return array_map(
            function ($img) {
                return [
                    'image_url' => is_object($img) ? ($img->image_url ?? '') : ($img['image_url'] ?? ''),
                ];
            },
            $images ?: []
        );
    }

    private function valueOf($row, string $field, $default = '')
    {
        if (is_object($row)) {
            return $row->{$field} ?? $default;
        }

        if (is_array($row)) {
            return $row[$field] ?? $default;
        }

        return $default;
    }
}
