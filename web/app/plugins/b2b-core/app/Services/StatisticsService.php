<?php

require_once __DIR__ . '/../Helpers/RoleHelper.php';
require_once __DIR__ . '/WpUserService.php';

use B2B\Helpers\RoleHelper;

class StatisticsService
{
    private $repo;

    public function __construct()
    {
        $this->repo = new StatisticsRepository();
    }

    public function revenue(array $filters = [])
    {
        $companyId = 0;
        $userId = (int) AuthHelper::userId();
        $filters = $this->normalizeFilters($filters);

        try {
            $companyId = (int) AuthHelper::companyId();
        } catch (Exception $e) {
            $companyId = 0;
        }

        $roles = $this->currentRoles($userId);
        $isAdmin = $this->hasRole($userId, 'ROLE_ADMIN', $roles) || in_array('ADMINISTRATOR', $roles, true);
        $isSupport = $this->hasRole($userId, 'ROLE_SUPPORT', $roles);
        $isSeller = $this->hasRole($userId, 'ROLE_SELLER', $roles);
        $selectedSellerCompanyId = (int) ($filters['seller_company_id'] ?? 0);
        $adminScopeCompanyId = ($isAdmin || $isSupport) ? $selectedSellerCompanyId : 0;

        return [
            'scope' => ($isAdmin || $isSupport) ? 'admin' : 'seller',
            'can_view_revenue' => $isAdmin || $isSupport || $isSeller,
            'admin' => ($isAdmin || $isSupport) ? $this->revenueBundle($adminScopeCompanyId, $filters) : null,
            'seller' => ($isSeller && $companyId > 0) ? $this->revenueBundle($companyId, $filters) : null,
        ];
    }

    public function productReviewCounts()
    {
        $rows = $this->repo->productReviewCounts();
        $map = [];

        if (!is_array($rows)) {
            return $map;
        }

        foreach ($rows as $row) {
            $map[(int) $row->product_id] = [
                'review_count' => (int) $row->review_count,
                'avg_rating' => round((float) $row->avg_rating, 1),
            ];
        }

        return $map;
    }

    private function revenueBundle($companyId, array $filters)
    {
        $summary = $this->repo->revenueSummary($companyId, $filters);

        return array_merge((array) $summary, [
            'summary' => $summary,
            'chart' => $this->repo->dailyRevenue($companyId, $filters),
            'orders' => $this->repo->successfulOrders($companyId, $filters),
            'brands' => $this->repo->revenueBrands($companyId, $filters),
        ]);
    }

    private function normalizeFilters(array $filters)
    {
        $sellerCompanyId = 0;

        foreach (['seller_company_id', 'company_id', 'seller_id'] as $key) {
            $value = isset($filters[$key]) ? (int) $filters[$key] : 0;

            if ($value > 0) {
                $sellerCompanyId = $value;
                break;
            }
        }

        return [
            'brand' => isset($filters['brand']) ? sanitize_text_field((string) $filters['brand']) : '',
            'date_from' => $this->validDate($filters['date_from'] ?? '') ? (string) $filters['date_from'] : '',
            'date_to' => $this->validDate($filters['date_to'] ?? '') ? (string) $filters['date_to'] : '',
            'seller_company_id' => $sellerCompanyId,
        ];
    }

    private function validDate($value)
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
    }

    private function currentRoles($userId)
    {
        $roles = [];

        try {
            $roles = array_merge($roles, RoleHelper::getRoles($userId));
        } catch (Throwable $e) {
            $roles = $roles;
        }

        try {
            $wpRoles = WpUserService::instance()->roles($userId);

            if (!empty($wpRoles)) {
                $roles = array_merge($roles, $wpRoles);
            }
        } catch (Throwable $e) {
            $roles = $roles;
        }

        try {
            $tokenRole = AuthHelper::role();
            $roles = array_merge($roles, is_array($tokenRole) ? $tokenRole : [$tokenRole]);
        } catch (Throwable $e) {
            $roles = $roles;
        }

        return array_values(array_unique(array_filter(array_map(function ($role) {
            return strtoupper(trim((string) $role));
        }, $roles))));
    }

    private function hasRole($userId, $roleCode, array $roles)
    {
        $shortRole = str_replace('ROLE_', '', $roleCode);

        return RoleHelper::hasRole($userId, $roleCode)
            || in_array($roleCode, $roles, true)
            || in_array($shortRole, $roles, true);
    }
}
