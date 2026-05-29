<?php

class ProductRepository
{
    private $table;

    public function __construct()
    {
        global $wpdb;

        $this->table = $wpdb->prefix . 'b2b_products';
    }

    public function create($data)
    {
        global $wpdb;

        $data['created_at'] = current_time('mysql');

        $wpdb->insert($this->table, $data);

        if ($wpdb->last_error) {
            throw new Exception($wpdb->last_error);
        }

        return $wpdb->insert_id;
    }

    public function findById($id)
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table}
                WHERE id = %d",
                $id
            )
        );
    }

    public function findByCompanyId($companyId)
    {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table}
                WHERE company_id = %d
                AND status IN ('draft', 'active', 'inactive')
                ORDER BY id DESC",
                $companyId
            )
        );
    }

    public function update($id, $data)
    {
        global $wpdb;

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

    public function delete($id)
    {
        global $wpdb;

        return $wpdb->delete(
            $this->table,
            ['id' => $id]
        );
    }

    public function softDelete($id)
    {
        return $this->update($id, [
            'status' => 'deleted'
        ]);
    }

    public function allActive()
    {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT * FROM {$this->table}
            WHERE status = 'active'
            ORDER BY id DESC"
        );
    }

       public function allProducts()
    {
        global $wpdb;

        $results = $wpdb->get_results(
            "SELECT * FROM {$this->table}
            ORDER BY id DESC"
        );

        if (!$results || is_wp_error($results)) {
            return [];
        }

        return $results;
    }

    public function searchByKeyword($keyword)
    {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT *
            FROM {$this->table}
            WHERE status = 'active'
            AND name LIKE %s
            ORDER BY id DESC",
            '%' . $wpdb->esc_like($keyword) . '%'
        );

        return $wpdb->get_results($sql);
    }
    
    public function filterProducts($filters = [])
    {
        global $wpdb;
        
        $table_products = $wpdb->prefix . 'b2b_products';
        $table_companies = $wpdb->prefix . 'b2b_companies';

        $query = "
            SELECT p.*, c.company_name as company_title 
            FROM $table_products p
            LEFT JOIN $table_companies c ON p.company_id = c.id
            WHERE 1=1
        ";

        $params = [];

        if (!empty($filters['brand'])) {
            $query .= " AND p.brand = %s";
            $params[] = $filters['brand'];
        }

        if (!empty($filters['color'])) {
            $query .= " AND p.color = %s";
            $params[] = $filters['color'];
        }

        if (!empty($filters['years'])) {
            $query .= " AND p.years = %s";
            $params[] = $filters['years'];
        }

        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }

        $rows = $wpdb->get_results($query);

        if (!empty($wpdb->last_error)) {
            error_log('[B2B][ProductRepository::filterProducts] SQL error: ' . $wpdb->last_error);
            return [];
        }

        return is_array($rows) ? $rows : [];
    }

    public function updateBlock($companyId, $data)
    {
        global $wpdb;

        return $wpdb->update(
            $this->table,
            $data,
            [
                'company_id' => $companyId,
                'status'  => 'active'
            ]
        );
    }

    public function updateActive($companyId, $data)
    {
        global $wpdb;

        return $wpdb->update(
            $this->table,
            $data,
            [
                'company_id' => $companyId,
                'status'  => 'blocked'
            ]
        );
    }


    public function sellerRequestByCompanyId($companyId, $verifiedOnly = false)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'b2b_seller_requests';
        $sql = "SELECT company_id, user_id, company_name, tax_code, representative_name, address, documents, status
                FROM {$table}
                WHERE company_id = %d";
        $params = [(int) $companyId];

        if ($verifiedOnly) {
            $sql .= " AND status = %s";
            $params[] = 'verified';
        }

        $sql .= " LIMIT 1";

        return $wpdb->get_row($wpdb->prepare($sql, $params));
    }

    public function sellerRequestByUserId($userId, $verifiedOnly = false)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'b2b_seller_requests';
        $sql = "SELECT company_id, user_id, company_name, tax_code, representative_name, address, documents, status
                FROM {$table}
                WHERE user_id = %d";
        $params = [(int) $userId];

        if ($verifiedOnly) {
            $sql .= " AND status = %s";
            $params[] = 'verified';
        }

        $sql .= " LIMIT 1";

        return $wpdb->get_row($wpdb->prepare($sql, $params));
    }

    public function brandNameById($brandId)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'b2b_brands';

        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT name FROM {$table} WHERE id = %d LIMIT 1",
                (int) $brandId
            )
        );
    }

    public function colorNameById($colorId)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'b2b_colors';

        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT name FROM {$table} WHERE id = %d LIMIT 1",
                (int) $colorId
            )
        );
    }

}
