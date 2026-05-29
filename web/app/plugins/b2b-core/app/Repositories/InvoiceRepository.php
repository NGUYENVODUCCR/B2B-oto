<?php

class InvoiceRepository
{
    private $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'b2b_invoices';
    }

    public function create($data)
    {
        global $wpdb;

        $data['created_at'] = current_time('mysql');

        $result = $wpdb->insert($this->table, $data);

        if ($result === false) {
            throw new Exception('Tạo hóa đơn thất bại: ' . $wpdb->last_error);
        }

        return $wpdb->insert_id;
    }

    public function findByOrderId($orderId)
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE order_id = %d LIMIT 1",
                (int) $orderId
            )
        );
    }

    public function findById($id)
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE id = %d LIMIT 1",
                (int) $id
            )
        );
    }


    public function primaryCompanyEmail($companyId): ?string
    {
        global $wpdb;

        $companyId = (int) $companyId;

        if ($companyId <= 0) {
            return null;
        }

        $email = $wpdb->get_var(
            $wpdb->prepare(
                "
                SELECT wu.user_email
                FROM {$wpdb->prefix}b2b_company_members cm
                INNER JOIN {$wpdb->users} wu ON wu.ID = cm.user_id
                WHERE cm.company_id = %d
                  AND wu.user_email <> ''
                ORDER BY
                    CASE cm.company_role
                        WHEN 'owner' THEN 0
                        WHEN 'admin' THEN 1
                        ELSE 2
                    END,
                    cm.id ASC
                LIMIT 1
                ",
                $companyId
            )
        );

        return $email ? (string) $email : null;
    }

    public function fallbackCompanyEmail($companyId): ?string
    {
        global $wpdb;

        $email = $wpdb->get_var(
            $wpdb->prepare(
                "
                SELECT company_email
                FROM {$wpdb->prefix}b2b_seller_requests
                WHERE company_id = %d
                  AND company_email IS NOT NULL
                  AND company_email <> ''
                ORDER BY id DESC
                LIMIT 1
                ",
                (int) $companyId
            )
        );

        return $email ? (string) $email : null;
    }
}