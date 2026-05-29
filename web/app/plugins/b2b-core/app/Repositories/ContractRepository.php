<?php

class ContractRepository
{
    protected $table;

    public function __construct()
    {
        global $wpdb;

        $this->table = $wpdb->prefix . 'b2b_contracts';
    }

    public function create($data)
    {
        global $wpdb;

        $result = $wpdb->insert(
            $this->table,
            $data
        );

        if (!$result) {
            return false;
        }

        return $wpdb->insert_id;
    }

    public function findByQuotationId($quotationId)
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "
                SELECT *
                FROM {$this->table}
                WHERE quotation_id = %d
                ",
                $quotationId
            )
        );
    }

    public function findById($id)
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "
                SELECT *
                FROM {$this->table}
                WHERE id = %d
                ",
                $id
            )
        );
    }

    public function update($id, $data)
    {
        global $wpdb;

        $data['updated_at'] = current_time('mysql');

        $result = $wpdb->update(
            $this->table,
            $data,
            [
                'id' => (int) $id
            ]
        );

        if ($wpdb->last_error) {
            throw new Exception($wpdb->last_error);
        }

        return $result !== false;
    }

    public function updateStatus($id, $status)
    {
        return $this->update($id, ['status' => $status]);
    }


    public function companyProfileData($companyId): array
    {
        global $wpdb;

        $member = $wpdb->get_row(
            $wpdb->prepare(
                "
                SELECT cm.user_id,
                       cm.company_role,
                       bu.fullname,
                       bu.phone,
                       wu.user_email,
                       wu.display_name
                FROM {$wpdb->prefix}b2b_company_members cm
                LEFT JOIN {$wpdb->prefix}b2b_users bu ON bu.wp_user_id = cm.user_id
                LEFT JOIN {$wpdb->users} wu ON wu.ID = cm.user_id
                WHERE cm.company_id = %d
                ORDER BY
                    CASE cm.company_role
                        WHEN 'owner' THEN 0
                        WHEN 'admin' THEN 1
                        ELSE 2
                    END,
                    cm.id ASC
                LIMIT 1
                ",
                (int) $companyId
            ),
            ARRAY_A
        );

        $sellerRequest = $wpdb->get_row(
            $wpdb->prepare(
                "
                SELECT representative_name,
                       citizen_id_number,
                       company_name,
                       tax_code,
                       address,
                       company_email
                FROM {$wpdb->prefix}b2b_seller_requests
                WHERE company_id = %d
                ORDER BY id DESC
                LIMIT 1
                ",
                (int) $companyId
            ),
            ARRAY_A
        );

        return [
            'member' => is_array($member) ? $member : [],
            'seller_request' => is_array($sellerRequest) ? $sellerRequest : [],
        ];
    }

    public function companyEmails($companyId): array
    {
        global $wpdb;

        $emails = $wpdb->get_col(
            $wpdb->prepare(
                "
                SELECT u.user_email
                FROM {$wpdb->prefix}b2b_company_members cm
                INNER JOIN {$wpdb->users} u ON u.ID = cm.user_id
                WHERE cm.company_id = %d
                AND u.user_email <> ''
                ",
                (int) $companyId
            )
        );

        return array_values(array_unique(array_filter($emails ?: [])));
    }

    public function userEmailsByIds(array $userIds): array
    {
        global $wpdb;

        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));

        if (empty($userIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($userIds), '%d'));

        return $wpdb->get_col(
            $wpdb->prepare(
                "SELECT user_email FROM {$wpdb->users} WHERE ID IN ({$placeholders}) AND user_email <> ''",
                ...$userIds
            )
        ) ?: [];
    }
}
