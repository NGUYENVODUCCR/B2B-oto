<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use B2B\Helpers\RoleHelper;

require_once __DIR__ . '/../Helpers/RoleHelper.php';
require_once __DIR__ . '/WpUserService.php';
require_once __DIR__ . '/../Repositories/PasswordResetRepository.php';
require_once __DIR__ . '/../Repositories/PhoneVerificationRepository.php';
require_once __DIR__ . '/../Support/Mailer.php';
require_once __DIR__ . '/../Repositories/CompanyRepository.php';
require_once __DIR__ . '/../Repositories/CompanyMemberRepository.php';
require_once __DIR__ . '/../Repositories/SellerRequestRepository.php';
require_once __DIR__ . '/../Repositories/UserRoleRepository.php';
require_once __DIR__ . '/../Repositories/RoleRepository.php';

class AuthService {

    private $companyRepo;
    private $companyMemberRepo;
    private $sellerRequestRepo;
    private $userRepo;
    private $refreshRepo;
    private $phoneVerifyRepo;
    private $wpUserService;
    private $passwordResetRepo;
    private $userRoleRepo;
    private $roleRepo;

    private static function secret() {
        $candidates = [];

        if (defined('AUTH_KEY')) {
            $candidates[] = AUTH_KEY;
        }

        if (defined('SECURE_AUTH_KEY')) {
            $candidates[] = SECURE_AUTH_KEY;
        }

        if (function_exists('env')) {
            $candidates[] = env('AUTH_KEY');
            $candidates[] = env('SECURE_AUTH_KEY');
        }

        $candidates[] = getenv('AUTH_KEY');
        $candidates[] = getenv('SECURE_AUTH_KEY');

        if (function_exists('wp_salt')) {
            $candidates[] = wp_salt('auth');
        }

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return $candidate;
            }
        }

        return 'b2b-marketplace-local-dev-secret';
    }

    public function __construct() {
        $this->userRepo = new UserRepository();
        $this->refreshRepo = new RefreshTokenRepository();
        $this->phoneVerifyRepo = new PhoneVerificationRepository();
        $this->wpUserService = new WpUserService();
        $this->passwordResetRepo = new PasswordResetRepository(); 
        $this->companyRepo = new CompanyRepository();
        $this->companyMemberRepo = new CompanyMemberRepository();
        $this->sellerRequestRepo = new SellerRequestRepository();
        $this->userRoleRepo = new UserRoleRepository();
        $this->roleRepo = new RoleRepository();
        
    }

    private function normalizePhoneForDb($phone) {
        $phone = preg_replace('/\D/', '', $phone);
    
        if (strpos($phone, '84') === 0) {
            $phone = '0' . substr($phone, 2);
        }
    
        if (!preg_match('/^0\d{9}$/', $phone)) {
            throw new Exception("SĐT không hợp lệ");
        }
    
        return $phone;
    }

    private function normalizePhoneForSms($phone) {
        $phone = preg_replace('/\D/', '', $phone);
        if (strpos($phone, '0') === 0) {
            return $phone;
        }
        return $phone; 
    }

    public function register($data) {

        $name = sanitize_text_field($data['name'] ?? '');
        $email = sanitize_email($data['email'] ?? '');
        $phoneRaw = sanitize_text_field($data['phone'] ?? '');
        $password = $data['password'] ?? '';
        $companyName = sanitize_text_field($data['company_name'] ?? '');
        $taxCode = sanitize_text_field($data['tax_code'] ?? '');
        $address = sanitize_textarea_field($data['address'] ?? '');

        if (!$email || !$name || !$password || !$phoneRaw || !$companyName || !$address) {
            throw new Exception("Thiếu dữ liệu");
        }

        $phoneDb = $this->normalizePhoneForDb($phoneRaw);
        $phoneSms = $this->normalizePhoneForSms($phoneRaw);

        $existingUser = $this->userRepo->findByPhone($phoneDb);

        if ($existingUser && $existingUser->status === 'active') {
		throw new Exception('Số điện thoại này đã được sử dụng. Vui lòng đăng nhập.');
	} else if($existingUser && $existingUser->status === 'pending'){
		throw new Exception('Tài khoản đang trong giai đoạn xác minh mã OTP được gửi về sdt của quý khách trước đó, vui lòng xác minh ngay hoặc chờ 15 phút sau để làm mới <đăng ký lại và nhận otp mới>');	
	}


        $wpUserByEmail = $this->wpUserService->findByEmail($email);
	
        if ($wpUserByEmail) {
		if (!$existingUser || $existingUser->wp_user_id != $wpUserByEmail->ID){
			throw new Exception('Email này đã được sử dụng bởi một tài khoản khác với số điện thoại khác.');
		}
        }
	
	$wpUserId = $this->wpUserService->create([
		'user_login' => $phoneDb,
		'user_pass' => $password,
		'user_email' => $email,
		'display_name' => $name
	]);

	if (is_wp_error($wpUserId)) {
		throw new Exception($wpUserId->get_error_message());
	}
	
	
        $this->wpUserService->updateMeta($wpUserId, 'phone', $phoneDb);

        $userId = $this->userRepo->create([
            'wp_user_id' => $wpUserId,
            'fullname'   => $name,
            'phone'      => $phoneDb,
            'status'     => 'pending'
        ]);

        RoleHelper::assignRole(
            $wpUserId,  
            'ROLE_BUYER'
        );

        $companyId = $this->companyRepo->create([
            'company_name' => $companyName,
            'tax_code' => $taxCode ?: null,
            'address' => $address,
            'verification_status' => 'unverified'
        ]);

        $this->companyMemberRepo->create([
            'company_id' => $companyId,
            'user_id' => $wpUserId,
            'company_role' => 'buyer'
        ]);


        $this->phoneVerifyRepo->deleteOtp($phoneDb);

        $otp = random_int(100000, 999999);
        $expired = date('Y-m-d H:i:s',current_time('timestamp') + 600);

        $this->phoneVerifyRepo->create($phoneDb, $otp, $expired);
        Sms::sendOtpByPhone($phoneSms, $otp);

        return $userId;
    }


    public function verifyAccount($phoneRaw, $otp) {

        $phoneDb = $this->normalizePhoneForDb($phoneRaw);

        $record = $this->phoneVerifyRepo->findLatestByPhone($phoneDb);

        if (!$record || !password_verify($otp, $record->otp)) {
            throw new Exception("OTP không hợp lệ");
        }

        if (strtotime($record->expired_at) < time()) {
            throw new Exception("OTP hết hạn");
        }

        $this->userRepo->updateVerified($phoneDb);
        $this->phoneVerifyRepo->deleteOtp($phoneDb);

        return true;
    }



    public function login($data) {

        $phone = $this->normalizePhoneForDb(sanitize_text_field($data['phone'] ?? ''));
        $password = $data['password'] ?? '';

        if (!$phone || !$password) {
            throw new Exception("Thiếu phone hoặc password!");
        }

        $wpUser = $this->wpUserService->findByPhone($phone);

        if (!$wpUser) {
            throw new Exception("Số điện thoại sai. Người dùng không tồn tại");
        }

        if (!$this->wpUserService->checkPassword($password, $wpUser)) {
            throw new Exception("Sai mật khẩu");
        }

        $user = $this->userRepo->findByWpUserId($wpUser->ID);

        if (!$user || $user->status !== 'active') {
            throw new Exception("Tài khoản chưa xác thực");
        }

        $roleId = $this->userRoleRepo->getIdRole($wpUser->ID);
        $roleName = $this->roleRepo->findRoleById($roleId);

        $accessToken = $this->generateAccessToken($user);
        $refreshToken = bin2hex(random_bytes(64));

        $this->refreshRepo->create(
            $user->id,
            $refreshToken,
            date('Y-m-d H:i:s', time() + 604800)
        );

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
		    'user' => $wpUser->display_name,
            'role' =>$roleName
        ];
    }

    private function generateAccessToken($user) {

        $roleId = $this->userRoleRepo->getIdRole($user->wp_user_id);
        $roleData = $this->roleRepo->findRoleById($roleId);

        $roles = [];

        if (is_array($roleData)) {
            foreach ($roleData as $item) {
                if (is_array($item) && !empty($item['role_name'])) {
                    $roles[] = $item['role_name'];
                } elseif (is_object($item) && !empty($item->role_name)) {
                    $roles[] = $item->role_name;
                }
            }
        } elseif (is_object($roleData) && !empty($roleData->role_name)) {
            $roles[] = $roleData->role_name;
        } elseif (is_string($roleData)) {
            $roles[] = $roleData;
        }

        $payload = [
            'iat' => time(),
            'exp' => time() + 86400,
            'user_id' => $user->wp_user_id,
            'roles' => $roles,
            'role' => $roles[0] ?? ''
        ];

        return JWT::encode($payload, self::secret(), 'HS256');
    }

    public static function verifyToken($token) {
        return JWT::decode($token, new Key(self::secret(), 'HS256'));
    }



   public function forgotPasswordByEmail($email) {

        $email = sanitize_email($email);

        $user = $this->wpUserService->findByEmail($email);

        if (!$user) {
            throw new Exception("Email không tồn tại!");
        }

        $otp = random_int(100000, 999999);
        $expired = date('Y-m-d H:i:s', current_time('timestamp') + 900);

        $this->passwordResetRepo->create($email, $otp, $expired);

        Mailer::sendResetPassword($email, $otp);

        return [
            'message' => 'OTP đã gửi'
        ];
    }

    public function resetPasswordByEmail($email, $otp, $newPassword) {

        $email = sanitize_email($email);

        $record = $this->passwordResetRepo->findLatestOtp($email);

        if (!$record || !password_verify($otp, $record->otp)) {
            throw new Exception("OTP không hợp lệ");
        }

        if (strtotime($record->expired_at) < time()) {
            throw new Exception("OTP hết hạn");
        }

        $user = $this->wpUserService->findByEmail($email);

        if (!$user) {
            throw new Exception("User không tồn tại");
        }

        $this->wpUserService->updatePassword($user->ID, $newPassword);

        $this->passwordResetRepo->deleteOtp($email);

        return true;
    }
}
