<?php

class NotificationRepository
{
    private $table;

    public function __construct()
    {
        global $wpdb;

        $this->table = $wpdb->prefix . 'b2b_system_notifications';
    }

    public function create(array $data)
    {
        global $wpdb;

        $payload = [
            'user_id' => (int) ($data['user_id'] ?? 0),
            'title' => (string) ($data['title'] ?? ''),
            'content' => (string) ($data['content'] ?? ''),
            'is_read' => (int) ($data['is_read'] ?? 0),
            'created_at' => $data['created_at'] ?? current_time('mysql'),
        ];

        if ($payload['user_id'] <= 0 || trim($payload['title']) === '' || trim($payload['content']) === '') {
            return 0;
        }

        $wpdb->insert($this->table, $payload);

        if ($wpdb->last_error) {
            throw new Exception($wpdb->last_error);
        }

        return (int) $wpdb->insert_id;
    }

    public function listByUserId($userId, $limit = 20)
    {
        global $wpdb;

        $limit = max(1, min(50, (int) $limit));

        return $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT id, user_id, title, content, is_read, created_at
                FROM {$this->table}
                WHERE user_id = %d
                ORDER BY created_at DESC, id DESC
                LIMIT %d
                ",
                (int) $userId,
                $limit
            )
        ) ?: [];
    }

    public function unreadCount($userId)
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "
                SELECT COUNT(*)
                FROM {$this->table}
                WHERE user_id = %d
                  AND is_read = 0
                ",
                (int) $userId
            )
        );
    }

    public function markRead($id, $userId)
    {
        global $wpdb;

        $result = $wpdb->update(
            $this->table,
            ['is_read' => 1],
            [
                'id' => (int) $id,
                'user_id' => (int) $userId,
            ]
        );

        if ($wpdb->last_error) {
            throw new Exception($wpdb->last_error);
        }

        return $result !== false;
    }

    public function markAllRead($userId)
    {
        global $wpdb;

        $result = $wpdb->update(
            $this->table,
            ['is_read' => 1],
            [
                'user_id' => (int) $userId,
                'is_read' => 0,
            ]
        );

        if ($wpdb->last_error) {
            throw new Exception($wpdb->last_error);
        }

        return $result !== false;
    }

    public function userIdsByCompanyId($companyId)
    {
        global $wpdb;

        $companyId = (int) $companyId;

        if ($companyId <= 0) {
            return [];
        }

        $companyMembers = $wpdb->prefix . 'b2b_company_members';
        $sellerRequests = $wpdb->prefix . 'b2b_seller_requests';

        $rows = $wpdb->get_col(
            $wpdb->prepare(
                "
                SELECT DISTINCT user_id
                FROM (
                    SELECT user_id
                    FROM {$companyMembers}
                    WHERE company_id = %d

                    UNION

                    SELECT user_id
                    FROM {$sellerRequests}
                    WHERE company_id = %d
                ) company_users
                WHERE user_id IS NOT NULL
                  AND user_id > 0
                ",
                $companyId,
                $companyId
            )
        );

        return array_values(array_unique(array_filter(array_map('intval', (array) $rows))));
    }

    public function orderProductSummary($orderId)
    {
        global $wpdb;

        $orderItems = $wpdb->prefix . 'b2b_order_items';
        $products = $wpdb->prefix . 'b2b_products';

        return $wpdb->get_row(
            $wpdb->prepare(
                "
                SELECT
                    GROUP_CONCAT(DISTINCT COALESCE(p.name, CONCAT('Sản phẩm #', oi.product_id)) ORDER BY p.name SEPARATOR ', ') AS product_names,
                    COUNT(DISTINCT oi.product_id) AS product_count
                FROM {$orderItems} oi
                LEFT JOIN {$products} p ON p.id = oi.product_id
                WHERE oi.order_id = %d
                ",
                (int) $orderId
            )
        );
    }

    public function productReviewStatsForOrder($orderId)
    {
        global $wpdb;

        $orderItems = $wpdb->prefix . 'b2b_order_items';
        $products = $wpdb->prefix . 'b2b_products';
        $reviews = $wpdb->prefix . 'b2b_reviews';

        return $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT
                    base.product_id,
                    COALESCE(p.name, CONCAT('Sản phẩm #', base.product_id)) AS product_name,
                    COUNT(r.id) AS review_count,
                    COALESCE(AVG(r.rating), 0) AS avg_rating
                FROM (
                    SELECT DISTINCT product_id
                    FROM {$orderItems}
                    WHERE order_id = %d
                ) base
                LEFT JOIN {$products} p ON p.id = base.product_id
                LEFT JOIN {$orderItems} oi2 ON oi2.product_id = base.product_id
                LEFT JOIN {$reviews} r ON r.order_id = oi2.order_id
                GROUP BY base.product_id, p.name
                ORDER BY p.name ASC
                ",
                (int) $orderId
            )
        ) ?: [];
    }
}