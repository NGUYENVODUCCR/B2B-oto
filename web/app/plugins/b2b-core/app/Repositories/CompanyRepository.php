<?php

class CompanyRepository {

    private $table;

    public function __construct() {
        global $wpdb;

        $this->table = $wpdb->prefix . 'b2b_companies';
    }

    public function create($data) {
        global $wpdb;

        $data['created_at'] = current_time('mysql');
        $data['updated_at'] = current_time('mysql');

        $wpdb->insert($this->table, $data);

        if ($wpdb->last_error) {
            throw new Exception($wpdb->last_error);
        }

        return $wpdb->insert_id;
    }

    public function findByTaxCode($taxCode)
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                FROM {$this->table}
                WHERE tax_code = %s
                LIMIT 1",
                $taxCode
            )
        );
    }

    public function firstOrCreate($data)
    {
        if (!empty($data['tax_code'])) {

            $company = $this->findByTaxCode(
                $data['tax_code']
            );

            if ($company) {
                return $company->id;
            }
        }

        return $this->create($data);
    }

    public function update($id, $data)
    {
        global $wpdb;

        $data['updated_at'] = current_time('mysql');

        $result = $wpdb->update(
            $this->table,
            $data,
            ['id' => $id]
        );

        if ($wpdb->last_error) {
            throw new Exception($wpdb->last_error);
        }

        return $result !== false;
    }
    public function findById($id)
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table}
                WHERE id = %d
                LIMIT 1",
                $id
            )
        );
    }
    public function findPrimaryMemberEmail($companyId)
{
    global $wpdb;

    $companyId = (int) $companyId;

    if ($companyId <= 0) {
        return null;
    }

    return $wpdb->get_var(
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
}

public function findLatestSellerRequestEmail($companyId)
{
    global $wpdb;

    $companyId = (int) $companyId;

    if ($companyId <= 0) {
        return null;
    }

    $table = $wpdb->prefix . 'b2b_seller_requests';

    return $wpdb->get_var(
        $wpdb->prepare(
            "
            SELECT company_email
            FROM {$table}
            WHERE company_id = %d
              AND company_email IS NOT NULL
              AND company_email <> ''
            ORDER BY id DESC
            LIMIT 1
            ",
            $companyId
        )
    );
}
}