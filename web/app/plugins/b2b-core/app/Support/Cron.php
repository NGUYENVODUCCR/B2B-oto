<?php

class Cron {

    private static function log($message) {
        error_log('[' . current_time('mysql') . '] ' . $message);
    }

    public static function run() {
        self::log("=== B2B CRON RUN ===");

        echo "=== B2B CRON RUN ===\n";

        self::log("MYSQL TIME: " . current_time('mysql'));
        self::log("TIMESTAMP: " . current_time('timestamp'));

        try {
            self::cleanupUsers();
            self::autoReleaseEscrow();
        } catch (Exception $e) {
            self::log("CRON ERROR: " . $e->getMessage());
            echo "CRON ERROR: " . $e->getMessage() . "\n";
        }

        echo "=== CRON DONE ===\n";
    }

    private static function cleanupUsers() {

        $userRepoPath = __DIR__ . '/../Repositories/UserRepository.php';
        if (!file_exists($userRepoPath)) {
            throw new Exception("Không tìm thấy UserRepository");
        }
        require_once $userRepoPath;
        $userRepo = new UserRepository();
        self::log("=== CLEANUP START ===");
        echo "Running cleanupPendingUsers...\n";
        self::log("Start cleanup pending users");
        $userRepo->cleanupPendingUsers();
        self::log("Done cleanup pending users");
        self::log("=== CLEANUP DONE ===");
        echo "Cleanup done\n";
    }

    private static function autoReleaseEscrow() {

        $paymentServicePath = __DIR__ . '/../Services/PaymentService.php';
        $paymentRepoPath = __DIR__ . '/../Repositories/PaymentRepository.php';
        $orderRepoPath = __DIR__ . '/../Repositories/OrderRepository.php';

        require_once $paymentRepoPath;
        require_once $orderRepoPath;
        require_once $paymentServicePath;

        $paymentService = new PaymentService();
        $released = $paymentService->autoReleaseOverdueEscrow(7);

        self::log("Auto released escrow payments: " . count($released));
    }
}
