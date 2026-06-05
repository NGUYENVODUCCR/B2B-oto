<?php
/**
 * Plugin Name: B2B Marketplace Core
 * Description: B2B marketplace core plugin for WordPress/Bedrock.
 * Version: 0.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('B2B_CORE_PLUGIN_FILE', __FILE__);
define('B2B_PLUGIN_PATH', plugin_dir_path(__FILE__));

require_once __DIR__ . '/vendor/autoload.php';
// Helpers
require_once __DIR__ . '/app/Helpers/ResponseHelper.php';
require_once __DIR__ . '/app/Helpers/RoleHelper.php';
require_once __DIR__ . '/app/Helpers/AuthHelper.php';
require_once __DIR__ . '/app/Helpers/UploadHelper.php';
require_once __DIR__ . '/app/Helpers/RequestHelper.php';
require_once __DIR__ . '/app/Helpers/TextHelper.php';
require_once __DIR__ . '/app/Helpers/TimeHelper.php';
require_once __DIR__ . '/app/Helpers/PermissionHelper.php';


// Database
require_once __DIR__ . '/app/Database/Migration.php';
require_once __DIR__ . '/app/Database/Migrator.php';

// Database/Seeders
require_once __DIR__ . '/app/Database/Seeders/RoleSeeder.php';

// Controllers
require_once __DIR__ . '/app/Controllers/AuthController.php';
require_once __DIR__ . '/app/Controllers/UserController.php';
require_once __DIR__ . '/app/Controllers/SellerRequestController.php';
require_once __DIR__ . '/app/Controllers/ProductController.php';
require_once __DIR__ . '/app/Controllers/QuotationController.php';
require_once __DIR__ . '/app/Controllers/RFQController.php';
require_once __DIR__ . '/app/Controllers/ContractController.php';
require_once __DIR__ . '/app/Controllers/OrderController.php';
require_once __DIR__ . '/app/Controllers/PaymentController.php';
require_once __DIR__ . '/app/Controllers/SupportController.php';
require_once __DIR__ . '/app/Controllers/ReviewController.php';
require_once __DIR__ . '/app/Controllers/WalletController.php';
require_once __DIR__ . '/app/Controllers/ChatController.php';
require_once __DIR__ . '/app/Controllers/StatisticsController.php';
require_once __DIR__ . '/app/Controllers/AIController.php';
require_once __DIR__ . '/app/Controllers/AdminController.php';
require_once __DIR__ . '/app/Controllers/NotificationController.php';

// Middleware
require_once __DIR__ . '/app/Middleware/AuthMiddleware.php';

//Service
require_once __DIR__ . '/app/Services/WpUserService.php';
require_once __DIR__ . '/app/Services/AuthService.php';
require_once __DIR__ . '/app/Services/UserService.php';
require_once __DIR__ . '/app/Services/SellerRequestService.php';
require_once __DIR__ . '/app/Services/ProductService.php';
require_once __DIR__ . '/app/Services/QuotationService.php';
require_once __DIR__ . '/app/Services/ContractService.php';
require_once __DIR__ . '/app/Services/OrderService.php';
require_once __DIR__ . '/app/Services/PaymentService.php';
require_once __DIR__ . '/app/Services/SupportService.php';
require_once __DIR__ . '/app/Services/ReviewService.php';
require_once __DIR__ . '/app/Services/RFQService.php';
require_once __DIR__ . '/app/Services/NegotiationService.php';
require_once __DIR__ . '/app/Services/WalletService.php';
require_once __DIR__ . '/app/Services/ChatService.php';
require_once __DIR__ . '/app/Services/StatisticsService.php';
require_once __DIR__ . '/app/Services/AIService.php';
require_once __DIR__ . '/app/Services/InvoiceService.php';
require_once __DIR__ . '/app/Services/AdminService.php';
require_once __DIR__ . '/app/Services/NotificationService.php';

// Repositories
require_once __DIR__ . '/app/Repositories/BaseRepository.php';
require_once __DIR__ . '/app/Repositories/WpUserRepository.php';
require_once __DIR__ . '/app/Repositories/UserRepository.php';
require_once __DIR__ . '/app/Repositories/RefreshTokenRepository.php';
require_once __DIR__ . '/app/Repositories/PhoneVerificationRepository.php';
require_once __DIR__ . '/app/Repositories/ProductRepository.php';
require_once __DIR__ . '/app/Repositories/ProductImageRepository.php';
require_once __DIR__ . '/app/Repositories/CompanyMemberRepository.php';
require_once __DIR__ . '/app/Repositories/CompanyRepository.php';
require_once __DIR__ . '/app/Repositories/QuotationRepository.php';
require_once __DIR__ . '/app/Repositories/QuotationItemRepository.php';
require_once __DIR__ . '/app/Repositories/RFQRepository.php';
require_once __DIR__ . '/app/Repositories/RFQItemRepository.php';
require_once __DIR__ . '/app/Repositories/ContractRepository.php';
require_once __DIR__ . '/app/Repositories/OrderRepository.php';
require_once __DIR__ . '/app/Repositories/OrderItemRepository.php';
require_once __DIR__ . '/app/Repositories/PaymentRepository.php';
require_once __DIR__ . '/app/Repositories/SupportTicketRepository.php';
require_once __DIR__ . '/app/Repositories/SupportMessageRepository.php';
require_once __DIR__ . '/app/Repositories/ReviewRepository.php';
require_once __DIR__ . '/app/Repositories/NegotiationRepository.php';
require_once __DIR__ . '/app/Repositories/WalletRepository.php';
require_once __DIR__ . '/app/Repositories/StatisticsRepository.php';
require_once __DIR__ . '/app/Repositories/InvoiceRepository.php';
require_once __DIR__ . '/app/Repositories/AdminRepository.php';
require_once __DIR__ . '/app/Repositories/AdminLogRepository.php';
require_once __DIR__ . '/app/Repositories/TransactionRepository.php';
require_once __DIR__ . '/app/Repositories/NotificationRepository.php';

// Validators
require_once __DIR__ . '/app/Validators/AuthValidator.php';
require_once __DIR__ . '/app/Validators/AIValidator.php';
require_once __DIR__ . '/app/Validators/AdminValidator.php';
require_once __DIR__ . '/app/Validators/BuyerRegisterValidator.php';
require_once __DIR__ . '/app/Validators/ChatValidator.php';
require_once __DIR__ . '/app/Validators/ContractValidator.php';
require_once __DIR__ . '/app/Validators/OrderValidator.php';
require_once __DIR__ . '/app/Validators/PaymentValidator.php';
require_once __DIR__ . '/app/Validators/ProductValidator.php';
require_once __DIR__ . '/app/Validators/QuotationValidator.php';
require_once __DIR__ . '/app/Validators/ReviewValidator.php';
require_once __DIR__ . '/app/Validators/RFQValidator.php';
require_once __DIR__ . '/app/Validators/SellerRequestValidator.php';
require_once __DIR__ . '/app/Validators/StatisticsValidator.php';
require_once __DIR__ . '/app/Validators/SupportValidator.php';
require_once __DIR__ . '/app/Validators/UserValidator.php';
require_once __DIR__ . '/app/Validators/WalletValidator.php';

//Support
require_once __DIR__ . '/app/Support/Sms.php';
require_once __DIR__ . '/app/Support/Cron.php';

// AI
require_once __DIR__ . '/app/AI/KnowledgeLoader.php';
require_once __DIR__ . '/app/AI/IntentDetector.php';
require_once __DIR__ . '/app/AI/BusinessTranslator.php';


add_action('plugins_loaded', function () {
    $lastChecked = (int) get_option('b2b_core_bootstrap_checked_at', 0);

    if ($lastChecked > 0 && $lastChecked > (time() - 300)) {
        return;
    }

    $migrator = new B2B\Database\Migrator(
        plugin_dir_path(__FILE__) . 'database/migrations',
        'b2b_marketplace_migrations_applied'
    );

    $migrator->maybeRun();

    (new B2B\Database\Seeders\RoleSeeder())->run();

    update_option('b2b_core_bootstrap_checked_at', time(), false);
});

add_filter('cron_schedules', function ($schedules) {
    $schedules['every_minute'] = [
        'interval' => 120,
        'display'  => 'Every 2 Minutes'
    ];
    return $schedules;
});

register_activation_hook(__FILE__, function () {

    error_log('B2B plugin activated');

    add_role('buyer', 'Người mua hàng', [
        'read' => true,
    ]);

    add_role('seller', 'Người bán hàng', [
        'read' => true,
    ]);

    add_role('support', 'Người hỗ trợ', [
        'read' => true,
    ]);

    add_role('admin', 'Người quản trị', [
        'read' => true,
        'manage_options' => true,
    ]);

    $migrator = new B2B\Database\Migrator(
        plugin_dir_path(__FILE__) . 'database/migrations',
        'b2b_marketplace_migrations_applied'
    );

    $migrator->migrate();
});

add_action('init', function () {

    if (!wp_next_scheduled('b2b_cleanup_users_event')) {

        error_log('Scheduling B2B cron...');

        wp_schedule_event(time(), 'every_minute', 'b2b_cleanup_users_event');
    }
});

add_action('b2b_cleanup_users_event', function () {

    error_log('>>> B2B CRON TRIGGERED');

    Cron::run();

});

add_action('b2b_send_signed_contract_email', function ($contractId) {
    try {
        $service = new ContractService();
        $service->sendSignedContractEmail((int) $contractId);
    } catch (Throwable $e) {
        error_log('B2B SEND CONTRACT EMAIL ERROR: ' . $e->getMessage());
    }
});

register_activation_hook(__FILE__, function () {
    error_log('B2B plugin activated');

    $migrator = new B2B\Database\Migrator(
        plugin_dir_path(__FILE__) . 'database/migrations',
        'b2b_marketplace_migrations_applied'
    );

    $migrator->migrate();
});

register_deactivation_hook(__FILE__, function () {
    error_log('B2B plugin deactivated');
});


add_action('rest_api_init', function () {

    $routes = require __DIR__ . '/config/routes.php';

    foreach ($routes as $group) {
        foreach ($group as $r) {

            register_rest_route('b2b/v1', $r['route'], [
                'methods'  => $r['method'],

                'callback' => function ($request) use ($r) {
                    try {
                        $controllerName = $r['action'][0];
                        $method = $r['action'][1];
                        $controller = new $controllerName();

                        return call_user_func([$controller, $method], $request);
                    } catch (Throwable $e) {
                        error_log('[B2B REST] Callback error on ' . ($r['route'] ?? 'unknown') . ': ' . $e->getMessage());

                        return ResponseHelper::error($e->getMessage(), 400);
                    }
                },

                'permission_callback' => function ($request) use ($r) {
                    try {

                        if (!empty($r['public']) && $r['public'] === true) {
                            return true;
                        }

                        return AuthMiddleware::handle($request);
                    } catch (Throwable $e) {
                        error_log('[B2B REST] Permission error on ' . ($r['route'] ?? 'unknown') . ': ' . $e->getMessage());

                        return new WP_Error('unauthorized', 'Token không hợp lệ', ['status' => 401]);
                    }
                }
            ]);
        }
    }
});
