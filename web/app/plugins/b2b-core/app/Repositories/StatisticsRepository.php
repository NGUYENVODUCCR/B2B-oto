<?php

class StatisticsRepository
{
    public function revenueSummary($companyId = 0, array $filters = [])
    {
        global $wpdb;

        $orders = $wpdb->prefix . 'b2b_orders';
        $payments = $wpdb->prefix . 'b2b_payments';
        $orderItems = $wpdb->prefix . 'b2b_order_items';
        $products = $wpdb->prefix . 'b2b_products';

        $where = $this->revenueWhere($companyId, $filters);
        $gross = $this->grossReleaseExpression();
        $fee = $this->platformFeeExpression();
        $payout = $this->sellerPayoutExpression();

        $sql = "
            SELECT
                COUNT(*) AS total_orders,
                COALESCE(SUM(gross_amount), 0) AS total_gmv,
                COALESCE(SUM(gross_amount), 0) AS total_revenue,
                COALESCE(SUM(platform_fee_amount), 0) AS platform_fee_amount,
                COALESCE(SUM(platform_fee_amount), 0) AS platform_commission,
                COALESCE(SUM(seller_payout_amount), 0) AS seller_payout_amount,
                COALESCE(SUM(cars_sold), 0) AS cars_sold
            FROM (
                SELECT
                    o.id,
                    MAX({$gross}) AS gross_amount,
                    MAX({$fee}) AS platform_fee_amount,
                    MAX({$payout}) AS seller_payout_amount,
                    COALESCE(SUM(oi.quantity), 0) AS cars_sold
                FROM {$payments} p
                INNER JOIN {$orders} o ON o.id = p.order_id
                LEFT JOIN {$orderItems} oi ON oi.order_id = o.id
                LEFT JOIN {$products} pr ON pr.id = oi.product_id
                {$where['sql']}
                GROUP BY o.id
            ) revenue_orders
        ";

        $row = $wpdb->get_row($this->prepare($sql, $where['params']));

        if (!$row) {
            $row = (object) [
                'total_orders' => 0,
                'total_revenue' => 0,
                'total_gmv' => 0,
                'platform_fee_amount' => 0,
                'platform_commission' => 0,
                'seller_payout_amount' => 0,
                'cars_sold' => 0,
            ];
        }

        return $row;
    }

    public function dailyRevenue($companyId = 0, array $filters = [])
    {
        global $wpdb;

        $orders = $wpdb->prefix . 'b2b_orders';
        $payments = $wpdb->prefix . 'b2b_payments';
        $orderItems = $wpdb->prefix . 'b2b_order_items';
        $products = $wpdb->prefix . 'b2b_products';
        $where = $this->revenueWhere($companyId, $filters);
        $gross = $this->grossReleaseExpression();
        $fee = $this->platformFeeExpression();
        $payout = $this->sellerPayoutExpression();

        $sql = "
            SELECT
                sale_day,
                COALESCE(SUM(gross_amount), 0) AS total_gmv,
                COALESCE(SUM(gross_amount), 0) AS total_revenue,
                COALESCE(SUM(platform_fee_amount), 0) AS platform_fee_amount,
                COALESCE(SUM(platform_fee_amount), 0) AS platform_commission,
                COALESCE(SUM(seller_payout_amount), 0) AS seller_payout_amount,
                COUNT(*) AS total_orders
            FROM (
                SELECT
                    o.id,
                    DATE(COALESCE(p.released_at, p.paid_at, p.created_at, o.created_at)) AS sale_day,
                    MAX({$gross}) AS gross_amount,
                    MAX({$fee}) AS platform_fee_amount,
                    MAX({$payout}) AS seller_payout_amount
                FROM {$payments} p
                INNER JOIN {$orders} o ON o.id = p.order_id
                LEFT JOIN {$orderItems} oi ON oi.order_id = o.id
                LEFT JOIN {$products} pr ON pr.id = oi.product_id
                {$where['sql']}
                GROUP BY o.id, sale_day
            ) revenue_days
            GROUP BY sale_day
            ORDER BY sale_day ASC
        ";

        $rows = $wpdb->get_results($this->prepare($sql, $where['params'])) ?: [];

        if (empty($filters['date_from']) && empty($filters['date_to']) && count($rows) > 30) {
            $rows = array_slice($rows, -30);
        }

        return $rows;
    }

    public function successfulOrders($companyId = 0, array $filters = [], $limit = 1000)
    {
        global $wpdb;

        $orders = $wpdb->prefix . 'b2b_orders';
        $payments = $wpdb->prefix . 'b2b_payments';
        $orderItems = $wpdb->prefix . 'b2b_order_items';
        $products = $wpdb->prefix . 'b2b_products';
        $productImages = $wpdb->prefix . 'b2b_product_images';
        $where = $this->revenueWhere($companyId, $filters);
        $params = array_merge($where['params'], [(int) $limit]);
        $gross = $this->grossReleaseExpression();
        $fee = $this->platformFeeExpression();
        $payout = $this->sellerPayoutExpression();

        $sql = "
            SELECT
                o.id,
                o.total_amount,
                o.status,
                o.created_at,
                {$gross} AS paid_amount,
                {$gross} AS gross_release_amount,
                {$fee} AS platform_fee_amount,
                {$payout} AS seller_payout_amount,
                COALESCE(p.released_at, p.paid_at, p.created_at, o.created_at) AS revenue_date,
                oi.product_id,
                oi.quantity,
                pr.name AS product_name,
                pr.brand,
                pr.years,
                pr.price_from,
                img.image_url
            FROM {$payments} p
            INNER JOIN {$orders} o ON o.id = p.order_id
            LEFT JOIN (
                SELECT
                    order_id,
                    MIN(product_id) AS product_id,
                    SUM(quantity) AS quantity
                FROM {$orderItems}
                GROUP BY order_id
            ) oi ON oi.order_id = o.id
            LEFT JOIN {$products} pr ON pr.id = oi.product_id
            LEFT JOIN (
                SELECT
                    product_id,
                    MIN(image_url) AS image_url
                FROM {$productImages}
                GROUP BY product_id
            ) img ON img.product_id = pr.id
            {$where['sql']}
            ORDER BY revenue_date DESC
            LIMIT %d
        ";

        return $wpdb->get_results($this->prepare($sql, $params)) ?: [];
    }

    public function revenueBrands($companyId = 0, array $filters = [])
    {
        global $wpdb;

        $orders = $wpdb->prefix . 'b2b_orders';
        $payments = $wpdb->prefix . 'b2b_payments';
        $orderItems = $wpdb->prefix . 'b2b_order_items';
        $products = $wpdb->prefix . 'b2b_products';
        $brandFilters = $filters;
        unset($brandFilters['brand']);
        $where = $this->revenueWhere($companyId, $brandFilters, false);

        $sql = "
            SELECT DISTINCT pr.brand
            FROM {$payments} p
            INNER JOIN {$orders} o ON o.id = p.order_id
            INNER JOIN {$orderItems} oi ON oi.order_id = o.id
            INNER JOIN {$products} pr ON pr.id = oi.product_id
            {$where['sql']}
            AND pr.brand IS NOT NULL
            AND pr.brand <> ''
            ORDER BY pr.brand ASC
        ";

        return $wpdb->get_col($this->prepare($sql, $where['params'])) ?: [];
    }

    public function productReviewCounts()
    {
        global $wpdb;

        $orderItems = $wpdb->prefix . 'b2b_order_items';
        $reviews = $wpdb->prefix . 'b2b_reviews';

        $rows = $wpdb->get_results("
            SELECT
                oi.product_id,
                COUNT(r.id) AS review_count,
                COALESCE(AVG(r.rating), 0) AS avg_rating
            FROM {$orderItems} oi
            LEFT JOIN {$reviews} r ON r.order_id = oi.order_id
            GROUP BY oi.product_id
        ");

        if (!empty($wpdb->last_error)) {
            error_log('[B2B][StatisticsRepository::productReviewCounts] SQL error: ' . $wpdb->last_error);
            return [];
        }

        return is_array($rows) ? $rows : [];
    }

    private function revenueWhere($companyId = 0, array $filters = [], $includeBrand = true)
    {
        $where = ["p.payment_status = 'released'"];
        $params = [];

        if ($companyId > 0) {
            $where[] = "o.seller_company_id = %d";
            $params[] = (int) $companyId;
        }

        if (!empty($filters['date_from'])) {
            $where[] = "DATE(COALESCE(p.released_at, p.paid_at, p.created_at, o.created_at)) >= %s";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "DATE(COALESCE(p.released_at, p.paid_at, p.created_at, o.created_at)) <= %s";
            $params[] = $filters['date_to'];
        }

        if ($includeBrand && !empty($filters['brand'])) {
            $where[] = "pr.brand = %s";
            $params[] = $filters['brand'];
        }

        return [
            'sql' => 'WHERE ' . implode(' AND ', $where),
            'params' => $params,
        ];
    }

    private function prepare($sql, array $params = [])
    {
        global $wpdb;

        return empty($params) ? $sql : $wpdb->prepare($sql, $params);
    }

    private function grossReleaseExpression()
    {
        if (!$this->paymentColumnExists('gross_release_amount')) {
            return "p.amount";
        }

        return "CASE WHEN COALESCE(p.gross_release_amount, 0) > 0 THEN p.gross_release_amount ELSE p.amount END";
    }

    private function platformFeeExpression()
    {
        $gross = $this->grossReleaseExpression();
        $rate = $this->feeRateSql();

        if (!$this->paymentColumnExists('platform_fee_rate') || !$this->paymentColumnExists('platform_fee_amount')) {
            return "ROUND(({$gross}) * {$rate}, 2)";
        }

        return "CASE WHEN p.platform_fee_rate IS NOT NULL THEN p.platform_fee_amount ELSE ROUND(({$gross}) * {$rate}, 2) END";
    }

    private function sellerPayoutExpression()
    {
        $gross = $this->grossReleaseExpression();
        $rate = $this->feeRateSql();

        if (!$this->paymentColumnExists('platform_fee_rate') || !$this->paymentColumnExists('seller_payout_amount')) {
            return "ROUND(({$gross}) - (({$gross}) * {$rate}), 2)";
        }

        return "CASE WHEN p.platform_fee_rate IS NOT NULL THEN p.seller_payout_amount ELSE ROUND(({$gross}) - (({$gross}) * {$rate}), 2) END";
    }

    private function feeRateSql()
    {
        $value = getenv('B2B_PLATFORM_FEE_RATE');

        if (function_exists('env')) {
            $value = env('B2B_PLATFORM_FEE_RATE') ?: $value;
        }

        $rate = is_numeric($value) ? (float) $value : 0.03;

        if ($rate > 1) {
            $rate = $rate / 100;
        }

        if ($rate < 0 || $rate > 1) {
            $rate = 0.03;
        }

        return number_format($rate, 4, '.', '');
    }

    private function paymentColumnExists($column)
    {
        static $columns = null;

        if ($columns === null) {
            global $wpdb;

            $payments = $wpdb->prefix . 'b2b_payments';
            $columns = [];

            foreach ($wpdb->get_results("SHOW COLUMNS FROM {$payments}") ?: [] as $row) {
                if (!empty($row->Field)) {
                    $columns[$row->Field] = true;
                }
            }
        }

        return !empty($columns[$column]);
    }
}
