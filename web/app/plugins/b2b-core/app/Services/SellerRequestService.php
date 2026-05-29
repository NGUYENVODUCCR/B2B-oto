<?php

require_once __DIR__ . '/../Repositories/SellerRequestRepository.php';
require_once __DIR__ . '/../Repositories/CompanyRepository.php';
require_once __DIR__ . '/../Repositories/CompanyMemberRepository.php';
require_once __DIR__ . '/../Helpers/RoleHelper.php';
require_once __DIR__ . '/../Validators/SellerRequestValidator.php';
require_once __DIR__ . '/WpUserService.php';

use B2B\Helpers\RoleHelper;


class SellerRequestService
{
    private $sellerRequestRepo;
    private $companyRepo;
    private $companyMemberRepo;


    public function __construct()
    {
        $this->sellerRequestRepo = new SellerRequestRepository();
        $this->companyRepo = new CompanyRepository();
        $this->companyMemberRepo = new CompanyMemberRepository();
    }

public function create($userId, $data, $files)
{
    if (!$userId) {

        throw new Exception(
            'User không hợp lệ'
        );
    }

    $isBuyer =
        RoleHelper::hasRole(
            $userId,
            'ROLE_BUYER'
        );

    $isAdmin =
        RoleHelper::hasRole(
            $userId,
            'ROLE_ADMIN'
        );

    if (!$isBuyer && !$isAdmin) {

        throw new Exception(
            'Chỉ tài khoản Buyer hoặc Admin mới được phép đăng ký seller'
        );
    }

    if (
        RoleHelper::hasRole(
            $userId,
            'ROLE_SELLER'
        )
    ) {

        throw new Exception(
            'Tài khoản này đã đăng ký seller xin vui lòng trờ phê duyệt'
        );
    }

    SellerRequestValidator::validate(
        $data,
        $files
    );

    $pendingRequest =
        $this->sellerRequestRepo
            ->findPendingByUserId(
                $userId
            );

    if ($pendingRequest) {

        throw new Exception(
            'Tài khoản của bạn đã đăng ký seller, vui lòng đợi duyệt'
        );
    }


    $request =
        $this->sellerRequestRepo
            ->findLatestByUserId(
                $userId
            );

    if (
        $request &&
        $request->status === 'verified'
    ) {

        throw new Exception(
            'Tài khoản đã là seller'
        );
    }

    $documents = [];

    if (!empty($files['citizen_front'])) {

        $documents[] =
            UploadHelper::uploadDocument(
                $files['citizen_front'],
                $userId,
                'citizen_front'
            );
    }

    if (!empty($files['citizen_back'])) {

        $documents[] =
            UploadHelper::uploadDocument(
                $files['citizen_back'],
                $userId,
                'citizen_back'
            );
    }

    if (!empty($files['business_license'])) {

        $documents[] =
            UploadHelper::uploadDocument(
                $files['business_license'],
                $userId,
                'business_license'
            );
    }

    if (!empty($files['company_logo'])) {

        $documents[] =
            UploadHelper::uploadDocument(
                $files['company_logo'],
                $userId,
                'company_logo'
            );
    }

    if (!empty($files['inspection_certificate'])) {

        $documents[] =
            UploadHelper::uploadDocument(
                $files['inspection_certificate'],
                $userId,
                'inspection_certificate'
            );
    }

    $companyMember =
        $this->companyMemberRepo
            ->findByUserId($userId);

    if (!$companyMember) {

        throw new Exception(
            'Không tìm thấy thông tin doanh nghiệp/công ty liên kết với tài khoản này'
        );
    }

    $companyId =
        $companyMember->company_id;

    $this->companyRepo->update(
        $companyId,
        [
            'verification_status' =>
                'pending'
        ]
    );

    $payload = [

        'user_id' => $userId,


        'representative_name' =>
            sanitize_text_field(
                $data['representative_name']
            ),

        'citizen_id_number' =>
            sanitize_text_field(
                $data['citizen_id_number']
            ),

        'company_name' =>
            sanitize_text_field(
                $data['company_name']
            ),

        'tax_code' =>
            sanitize_text_field(
                $data['tax_code']
            ),

        'address' =>
            sanitize_textarea_field(
                $data['address']
            ),

        'company_email' =>
            sanitize_email(
                $data['company_email']
                    ?? ''
            ),
            'bank_name' =>
        sanitize_text_field(
            $data['bank_name']
                ?? ''
        ),

        'bank_account' =>
            sanitize_text_field(
                $data['bank_account']
                    ?? ''
            ),
        'documents' =>
            json_encode($documents),

        'company_id' =>
            $companyId,

        'status' => 'pending'
    ];

    $pendingRequest =
        $this->sellerRequestRepo
            ->findPendingByUserId(
                $userId
            );

    if ($pendingRequest) {

        throw new Exception(
            'Tài khoản của bạn đã đăng ký seller, vui lòng đợi duyệt'
        );
    }

    $requestId =
        $this->sellerRequestRepo
            ->create($payload);

    return [

        'message' =>
            'Đăng ký seller thành công',

        'request_id' =>
            $requestId,

        'status' => 'pending'
    ];
}

    public function approve($reviewerId, $data)
    {
        if (!$this->isAdminOrSupport($reviewerId)) {
            throw new Exception('Không có quyền duyệt');
        }

        $requestId = (int)($data['request_id'] ?? 0);

        if (!$requestId) {
            throw new Exception('Request ID không hợp lệ');
        }

        $request = $this->sellerRequestRepo->findById($requestId);

        if (!$request) {
            throw new Exception('Request không tồn tại');
        }

        if ($request->status !== 'pending') {
            throw new Exception('Request đã xử lý');
        }

        $companyId = $request->company_id;

        if (!$companyId) {
            throw new Exception('Không tìm thấy company');
        }

        $this->companyRepo->update($companyId, [

            'company_name' => $request->company_name,

            'tax_code' => $request->tax_code,

            'address' => $request->address,

            'bank_name' => $request->bank_name ?? '',

            'bank_account' => $request->bank_account ?? '',

            'verification_status' => 'verified'
        ]);


        RoleHelper::assignRole(
            $request->user_id,
            'ROLE_SELLER'
        );


        $member = $this->companyMemberRepo
            ->findByCompanyAndUser(
                $companyId,
                $request->user_id
            );

        if ($member) {

            $roles = explode(',', $member->company_role);

            if (!in_array('buyer', $roles)) {
                $roles[] = 'buyer';
            }

            if (!in_array('seller', $roles)) {
                $roles[] = 'seller';
            }

            $roles = array_unique($roles);

            $this->companyMemberRepo->update(
                $member->id,
                [
                    'company_role' => implode(',', $roles)
                ]
            );

        } else {

            $this->companyMemberRepo->create([
                'company_id' => $companyId,
                'user_id' => $request->user_id,
                'company_role' => 'buyer,seller',
            ]);
        }


        $this->sellerRequestRepo->approve(
            $requestId,
            [
                'company_id' => $companyId,
                'reviewed_by' => $reviewerId,
                'reviewed_at' => current_time('mysql'),
                'note' => sanitize_textarea_field(
                    $data['note'] ?? ''
                )
            ]
        );

        return [
            'message' => 'Duyệt seller thành công',
            'company_id' => $companyId
        ];
    }

    public function reject($reviewerId, $data)
    {
        if (!$this->isAdminOrSupport($reviewerId)) {
            throw new Exception('Không có quyền từ chối');
        }

        $requestId = (int)($data['request_id'] ?? 0);

        if (!$requestId) {
            throw new Exception('Request ID không hợp lệ');
        }

        $request = $this->sellerRequestRepo->findById($requestId);

        if (!$request) {
            throw new Exception('Request không tồn tại');
        }

        $this->sellerRequestRepo->reject($requestId, [
            'reviewed_by' => $reviewerId,
            'reviewed_at' => current_time('mysql'),
            'note'        => sanitize_textarea_field($data['note'] ?? '')
        ]);

        return [
            'message' => 'Đã từ chối seller request'
        ];
    }

    public function myRequest($userId)
    {
        $requests =
            $this->sellerRequestRepo
                ->findByUserId($userId);

        if (!$requests) {
            return [];
        }

        foreach ($requests as $request) {

            $request->documents =
                $this->sellerRequestRepo
                    ->findLinkFile(
                        $request->documents
                    );
        }

        return $requests;
}

    public function list($userId)
    {
        if (
            !$this->isAdminOrSupport($userId)
        ) {
            throw new Exception(
                'Không có quyền'
            );
        }

        $requests =
            $this->sellerRequestRepo->all();

        foreach ($requests as $request) {

            $request->documents =
                $this->sellerRequestRepo
                    ->findLinkFile(
                        $request->documents
                    );
        }

        return $requests;
    }

    private function isAdminOrSupport($userId)

    {
        $user = WpUserService::instance()->findRawById($userId);

        if (!$user) {
            error_log('USER NOT FOUND: ' . $userId);
            return false;
        }

        error_log('========== ADMIN CHECK ==========');
        error_log('USER ID: ' . $userId);

        error_log(
            'WP ROLES: ' . print_r($user->roles, true)
        );

        error_log(
            'HAS ROLE_ADMIN: ' . (
                RoleHelper::hasRole($userId, 'ROLE_ADMIN')
                    ? 'YES'
                    : 'NO'
            )
        );

        error_log(
            'HAS ROLE_SUPPORT: ' . (
                RoleHelper::hasRole($userId, 'ROLE_SUPPORT')
                    ? 'YES'
                    : 'NO'
            )
        );

        error_log('=================================');

        return RoleHelper::hasRole($userId, 'ROLE_ADMIN')
            || RoleHelper::hasRole($userId, 'ROLE_SUPPORT');
    }
}