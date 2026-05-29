<?php

namespace B2B\Database\Migrations;

use B2B\Database\Migration;

if (!defined('ABSPATH')) {
    exit;
}

class CreateForeignKeysTable extends Migration
{
    public function up(): void
    {
        global $wpdb;

        $prefix = $this->prefix();
        $wp_users_table = $wpdb->users;

        $queries = [
            // user_roles
            "ALTER TABLE {$prefix}user_roles
                ADD CONSTRAINT fk_user_roles_user_id
                FOREIGN KEY (user_id) REFERENCES {$wp_users_table}(ID)",
            "ALTER TABLE {$prefix}user_roles
                ADD CONSTRAINT fk_user_roles_role_id
                FOREIGN KEY (role_id) REFERENCES {$prefix}roles(id)",

            // company_members
            "ALTER TABLE {$prefix}company_members
                ADD CONSTRAINT fk_company_members_company_id
                FOREIGN KEY (company_id) REFERENCES {$prefix}companies(id)",
            "ALTER TABLE {$prefix}company_members
                ADD CONSTRAINT fk_company_members_user_id
                FOREIGN KEY (user_id) REFERENCES {$wp_users_table}(ID)",

            // products / images
            "ALTER TABLE {$prefix}products
                ADD CONSTRAINT fk_products_company_id
                FOREIGN KEY (company_id) REFERENCES {$prefix}companies(id)",
            "ALTER TABLE {$prefix}product_images
                ADD CONSTRAINT fk_product_images_product_id
                FOREIGN KEY (product_id) REFERENCES {$prefix}products(id)",

            // rfqs / rfq_items
            "ALTER TABLE {$prefix}rfqs
                ADD CONSTRAINT fk_rfqs_buyer_company_id
                FOREIGN KEY (buyer_company_id) REFERENCES {$prefix}companies(id)",
            "ALTER TABLE {$prefix}rfq_items
                ADD CONSTRAINT fk_rfq_items_rfq_id
                FOREIGN KEY (rfq_id) REFERENCES {$prefix}rfqs(id)",
            "ALTER TABLE {$prefix}rfq_items
                ADD CONSTRAINT fk_rfq_items_product_id
                FOREIGN KEY (product_id) REFERENCES {$prefix}products(id)",

            // quotations / quotation_items
            "ALTER TABLE {$prefix}quotations
                ADD CONSTRAINT fk_quotations_rfq_id
                FOREIGN KEY (rfq_id) REFERENCES {$prefix}rfqs(id)",
            "ALTER TABLE {$prefix}quotations
                ADD CONSTRAINT fk_quotations_seller_company_id
                FOREIGN KEY (seller_company_id) REFERENCES {$prefix}companies(id)",
            "ALTER TABLE {$prefix}quotation_items
                ADD CONSTRAINT fk_quotation_items_quotation_id
                FOREIGN KEY (quotation_id) REFERENCES {$prefix}quotations(id)",
            "ALTER TABLE {$prefix}quotation_items
                ADD CONSTRAINT fk_quotation_items_product_id
                FOREIGN KEY (product_id) REFERENCES {$prefix}products(id)",

            // negotiations
            "ALTER TABLE {$prefix}negotiations
                ADD CONSTRAINT fk_negotiations_rfq_id
                FOREIGN KEY (rfq_id) REFERENCES {$prefix}rfqs(id)",
            "ALTER TABLE {$prefix}negotiations
                ADD CONSTRAINT fk_negotiations_sender_id
                FOREIGN KEY (sender_id) REFERENCES {$wp_users_table}(ID)",

            // contracts / orders
            "ALTER TABLE {$prefix}contracts
                ADD CONSTRAINT fk_contracts_quotation_id
                FOREIGN KEY (quotation_id) REFERENCES {$prefix}quotations(id)",
            "ALTER TABLE {$prefix}orders
                ADD CONSTRAINT fk_orders_contract_id
                FOREIGN KEY (contract_id) REFERENCES {$prefix}contracts(id)",
            "ALTER TABLE {$prefix}orders
                ADD CONSTRAINT fk_orders_buyer_company_id
                FOREIGN KEY (buyer_company_id) REFERENCES {$prefix}companies(id)",
            "ALTER TABLE {$prefix}orders
                ADD CONSTRAINT fk_orders_seller_company_id
                FOREIGN KEY (seller_company_id) REFERENCES {$prefix}companies(id)",

            // order_items
            "ALTER TABLE {$prefix}order_items
                ADD CONSTRAINT fk_order_items_order_id
                FOREIGN KEY (order_id) REFERENCES {$prefix}orders(id)",
            "ALTER TABLE {$prefix}order_items
                ADD CONSTRAINT fk_order_items_product_id
                FOREIGN KEY (product_id) REFERENCES {$prefix}products(id)",

            // payments / invoices / reviews
            "ALTER TABLE {$prefix}payments
                ADD CONSTRAINT fk_payments_order_id
                FOREIGN KEY (order_id) REFERENCES {$prefix}orders(id)",
            "ALTER TABLE {$prefix}invoices
                ADD CONSTRAINT fk_invoices_order_id
                FOREIGN KEY (order_id) REFERENCES {$prefix}orders(id)",
            "ALTER TABLE {$prefix}reviews
                ADD CONSTRAINT fk_reviews_order_id
                FOREIGN KEY (order_id) REFERENCES {$prefix}orders(id)",

            // support
            "ALTER TABLE {$prefix}support_tickets
                ADD CONSTRAINT fk_support_tickets_user_id
                FOREIGN KEY (user_id) REFERENCES {$wp_users_table}(ID)",
            "ALTER TABLE {$prefix}support_tickets
                ADD CONSTRAINT fk_support_tickets_order_id
                FOREIGN KEY (order_id) REFERENCES {$prefix}orders(id)",
            "ALTER TABLE {$prefix}support_messages
                ADD CONSTRAINT fk_support_messages_ticket_id
                FOREIGN KEY (ticket_id) REFERENCES {$prefix}support_tickets(id)",
            "ALTER TABLE {$prefix}support_messages
                ADD CONSTRAINT fk_support_messages_sender_id
                FOREIGN KEY (sender_id) REFERENCES {$wp_users_table}(ID)",

            // system notifications / admin logs
            "ALTER TABLE {$prefix}system_notifications
                ADD CONSTRAINT fk_system_notifications_user_id
                FOREIGN KEY (user_id) REFERENCES {$wp_users_table}(ID)",
            "ALTER TABLE {$prefix}admin_logs
                ADD CONSTRAINT fk_admin_logs_admin_id
                FOREIGN KEY (admin_id) REFERENCES {$wp_users_table}(ID)",

            // chat
            "ALTER TABLE {$prefix}messages
                ADD CONSTRAINT fk_messages_conversation_id
                FOREIGN KEY (conversation_id) REFERENCES {$prefix}conversations(id)",
            "ALTER TABLE {$prefix}messages
                ADD CONSTRAINT fk_messages_sender_id
                FOREIGN KEY (sender_id) REFERENCES {$wp_users_table}(ID)",

            // seller requests
            "ALTER TABLE {$prefix}seller_requests
                ADD CONSTRAINT fk_seller_requests_user_id
                FOREIGN KEY (user_id) REFERENCES {$wp_users_table}(ID)",
            "ALTER TABLE {$prefix}seller_requests
                ADD CONSTRAINT fk_seller_requests_company_id
                FOREIGN KEY (company_id) REFERENCES {$prefix}companies(id)",
            "ALTER TABLE {$prefix}seller_requests
                ADD CONSTRAINT fk_seller_requests_reviewed_by
                FOREIGN KEY (reviewed_by) REFERENCES {$wp_users_table}(ID)",

            // refresh tokens
            "ALTER TABLE {$prefix}refresh_tokens
                ADD CONSTRAINT fk_refresh_tokens_user_id
                FOREIGN KEY (user_id) REFERENCES {$prefix}users(id)
                ON DELETE CASCADE",

            // rfqs support references
            "ALTER TABLE {$prefix}rfqs
                ADD CONSTRAINT fk_rfqs_support_user_id
                FOREIGN KEY (support_user_id) REFERENCES {$wp_users_table}(ID)
                ON DELETE SET NULL",

            "ALTER TABLE {$prefix}rfqs
                ADD CONSTRAINT fk_rfqs_support_ticket_id
                FOREIGN KEY (support_ticket_id) REFERENCES {$prefix}support_tickets(id)
                ON DELETE SET NULL",

            // payments settlement references
            "ALTER TABLE {$prefix}payments
                ADD CONSTRAINT fk_payments_settlement_order_id
                FOREIGN KEY (settlement_order_id) REFERENCES {$prefix}orders(id)
                ON DELETE SET NULL",

            "ALTER TABLE {$prefix}payments
                ADD CONSTRAINT fk_payments_settlement_buyer_company_id
                FOREIGN KEY (settlement_buyer_company_id) REFERENCES {$prefix}companies(id)
                ON DELETE SET NULL",

            "ALTER TABLE {$prefix}payments
                ADD CONSTRAINT fk_payments_settlement_seller_company_id
                FOREIGN KEY (settlement_seller_company_id) REFERENCES {$prefix}companies(id)
                ON DELETE SET NULL",

            // support tickets extended references
            "ALTER TABLE {$prefix}support_tickets
                ADD CONSTRAINT fk_support_tickets_support_user_id
                FOREIGN KEY (support_user_id) REFERENCES {$wp_users_table}(ID)
                ON DELETE SET NULL",

            "ALTER TABLE {$prefix}support_tickets
                ADD CONSTRAINT fk_support_tickets_rfq_id
                FOREIGN KEY (rfq_id) REFERENCES {$prefix}rfqs(id)
                ON DELETE SET NULL",

            // chat receiver
            "ALTER TABLE {$prefix}messages
                ADD CONSTRAINT fk_messages_receiver_id
                FOREIGN KEY (receiver_id) REFERENCES {$wp_users_table}(ID)
                ON DELETE SET NULL",
                    ];

        foreach ($queries as $sql) {
            $wpdb->query($sql);
        }
    }

    public function down(): void
    {
        global $wpdb;

        $prefix = $this->prefix();

        $queries = [
            "ALTER TABLE {$prefix}seller_requests DROP FOREIGN KEY fk_seller_requests_reviewed_by",
            "ALTER TABLE {$prefix}seller_requests DROP FOREIGN KEY fk_seller_requests_company_id",
            "ALTER TABLE {$prefix}seller_requests DROP FOREIGN KEY fk_seller_requests_user_id",

            "ALTER TABLE {$prefix}messages DROP FOREIGN KEY fk_messages_sender_id",
            "ALTER TABLE {$prefix}messages DROP FOREIGN KEY fk_messages_conversation_id",

            "ALTER TABLE {$prefix}admin_logs DROP FOREIGN KEY fk_admin_logs_admin_id",
            "ALTER TABLE {$prefix}system_notifications DROP FOREIGN KEY fk_system_notifications_user_id",

            "ALTER TABLE {$prefix}support_messages DROP FOREIGN KEY fk_support_messages_sender_id",
            "ALTER TABLE {$prefix}support_messages DROP FOREIGN KEY fk_support_messages_ticket_id",
            "ALTER TABLE {$prefix}support_tickets DROP FOREIGN KEY fk_support_tickets_order_id",
            "ALTER TABLE {$prefix}support_tickets DROP FOREIGN KEY fk_support_tickets_user_id",

            "ALTER TABLE {$prefix}reviews DROP FOREIGN KEY fk_reviews_order_id",
            "ALTER TABLE {$prefix}invoices DROP FOREIGN KEY fk_invoices_order_id",
            "ALTER TABLE {$prefix}payments DROP FOREIGN KEY fk_payments_order_id",

            "ALTER TABLE {$prefix}order_items DROP FOREIGN KEY fk_order_items_product_id",
            "ALTER TABLE {$prefix}order_items DROP FOREIGN KEY fk_order_items_order_id",

            "ALTER TABLE {$prefix}orders DROP FOREIGN KEY fk_orders_seller_company_id",
            "ALTER TABLE {$prefix}orders DROP FOREIGN KEY fk_orders_buyer_company_id",
            "ALTER TABLE {$prefix}orders DROP FOREIGN KEY fk_orders_contract_id",

            "ALTER TABLE {$prefix}contracts DROP FOREIGN KEY fk_contracts_quotation_id",

            "ALTER TABLE {$prefix}negotiations DROP FOREIGN KEY fk_negotiations_sender_id",
            "ALTER TABLE {$prefix}negotiations DROP FOREIGN KEY fk_negotiations_rfq_id",

            "ALTER TABLE {$prefix}quotation_items DROP FOREIGN KEY fk_quotation_items_product_id",
            "ALTER TABLE {$prefix}quotation_items DROP FOREIGN KEY fk_quotation_items_quotation_id",
            "ALTER TABLE {$prefix}quotations DROP FOREIGN KEY fk_quotations_seller_company_id",
            "ALTER TABLE {$prefix}quotations DROP FOREIGN KEY fk_quotations_rfq_id",

            "ALTER TABLE {$prefix}rfq_items DROP FOREIGN KEY fk_rfq_items_product_id",
            "ALTER TABLE {$prefix}rfq_items DROP FOREIGN KEY fk_rfq_items_rfq_id",
            "ALTER TABLE {$prefix}rfqs DROP FOREIGN KEY fk_rfqs_buyer_company_id",

            "ALTER TABLE {$prefix}product_images DROP FOREIGN KEY fk_product_images_product_id",
            "ALTER TABLE {$prefix}products DROP FOREIGN KEY fk_products_company_id",

            "ALTER TABLE {$prefix}company_members DROP FOREIGN KEY fk_company_members_user_id",
            "ALTER TABLE {$prefix}company_members DROP FOREIGN KEY fk_company_members_company_id",

            "ALTER TABLE {$prefix}user_roles DROP FOREIGN KEY fk_user_roles_role_id",
            "ALTER TABLE {$prefix}user_roles DROP FOREIGN KEY fk_user_roles_user_id",

            "ALTER TABLE {$prefix}messages DROP FOREIGN KEY fk_messages_receiver_id",

            "ALTER TABLE {$prefix}support_tickets DROP FOREIGN KEY fk_support_tickets_rfq_id",
            "ALTER TABLE {$prefix}support_tickets DROP FOREIGN KEY fk_support_tickets_support_user_id",

            "ALTER TABLE {$prefix}payments DROP FOREIGN KEY fk_payments_settlement_seller_company_id",
            "ALTER TABLE {$prefix}payments DROP FOREIGN KEY fk_payments_settlement_buyer_company_id",
            "ALTER TABLE {$prefix}payments DROP FOREIGN KEY fk_payments_settlement_order_id",

            "ALTER TABLE {$prefix}rfqs DROP FOREIGN KEY fk_rfqs_support_ticket_id",
            "ALTER TABLE {$prefix}rfqs DROP FOREIGN KEY fk_rfqs_support_user_id",

            "ALTER TABLE {$prefix}refresh_tokens DROP FOREIGN KEY fk_refresh_tokens_user_id",
        ];

        foreach ($queries as $sql) {
            $wpdb->query($sql);
        }
    }
}

return new CreateForeignKeysTable();