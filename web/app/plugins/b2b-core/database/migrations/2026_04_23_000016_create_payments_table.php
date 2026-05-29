<?php

namespace B2B\Database\Migrations;

use B2B\Database\Migration;

if (!defined('ABSPATH')) {
    exit;
}

class CreatePaymentsTable extends Migration
{
    public function up(): void
    {
        global $wpdb;
        $prefix = $this->prefix();
        $charset_collate = $this->charsetCollate();

        $sql = "CREATE TABLE {$prefix}payments (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                order_id BIGINT UNSIGNED NOT NULL,
                payment_method VARCHAR(50) NOT NULL DEFAULT 'vnpay',
                payment_status VARCHAR(30) NOT NULL DEFAULT 'pending',
                amount DECIMAL(18,2) NOT NULL DEFAULT 0,
                gateway_ref VARCHAR(100) NULL,
                paid_at DATETIME NULL,
                released_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT NULL,
                gross_release_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
                platform_fee_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
                platform_fee_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
                seller_payout_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
                settlement_order_id BIGINT UNSIGNED NULL,
                settlement_buyer_company_id BIGINT UNSIGNED NULL,
                settlement_seller_company_id BIGINT UNSIGNED NULL,
                settlement_payment_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
                settlement_release_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
                settlement_refund_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
                settlement_reason VARCHAR(100) NULL,
                settlement_meta LONGTEXT NULL,
                settlement_settled_at DATETIME NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uq_payments_order_id (order_id),
                KEY idx_payments_status (payment_status),
                KEY idx_payments_gateway_ref (gateway_ref),
                KEY idx_payments_settlement_settled_at (settlement_settled_at)
        ) {$charset_collate};";

        $wpdb->query($sql);
    }

    public function down(): void
    {
        global $wpdb;
        $prefix = $this->prefix();
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}payments;");
    }
}

return new CreatePaymentsTable();
