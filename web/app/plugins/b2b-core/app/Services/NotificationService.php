<?php

class NotificationService
{
    private $repository;
    private $companyRepository;

    public function __construct()
    {
        $this->repository = new NotificationRepository();
        $this->companyRepository = new CompanyRepository();
    }

    public function items($userId, $limit = 20)
    {
        $userId = (int) $userId;

        if ($userId <= 0) {
            throw new Exception('Missing user_id');
        }

        return [
            'items' => $this->repository->listByUserId($userId, $limit),
            'unread_count' => $this->repository->unreadCount($userId),
        ];
    }

    public function unreadCount($userId)
    {
        $userId = (int) $userId;

        if ($userId <= 0) {
            throw new Exception('Missing user_id');
        }

        return [
            'unread_count' => $this->repository->unreadCount($userId),
        ];
    }

    public function markRead($notificationId, $userId)
    {
        $notificationId = (int) $notificationId;
        $userId = (int) $userId;

        if ($notificationId <= 0) {
            throw new Exception('Missing notification_id');
        }

        if ($userId <= 0) {
            throw new Exception('Missing user_id');
        }

        $this->repository->markRead($notificationId, $userId);

        return $this->unreadCount($userId);
    }

    public function markAllRead($userId)
    {
        $userId = (int) $userId;

        if ($userId <= 0) {
            throw new Exception('Missing user_id');
        }

        $this->repository->markAllRead($userId);

        return $this->unreadCount($userId);
    }

    public function createForUser($userId, $title, $content)
    {
        return $this->repository->create([
            'user_id' => (int) $userId,
            'title' => $this->cleanOneLine($title),
            'content' => $this->cleanContent($content),
            'is_read' => 0,
            'created_at' => current_time('mysql'),
        ]);
    }

    public function notifyReviewCreated($order, $review, $actorUserId = 0)
    {
        if (!$order || !$review) {
            return [
                'seller_notifications' => 0,
                'buyer_notifications' => 0,
            ];
        }

        $buyerCompanyId = (int) ($order->buyer_company_id ?? 0);
        $sellerCompanyId = (int) ($order->seller_company_id ?? 0);
        $rating = (int) ($review->rating ?? 0);
        $comment = $this->cleanContent($review->comment ?? '');
        $productNames = $this->productNames((int) ($order->id ?? 0));
        $buyerName = $this->companyName($buyerCompanyId, 'Buyer');
        $sellerName = $this->companyName($sellerCompanyId, 'Seller');
        $statsText = $this->reviewStatsText((int) ($order->id ?? 0));

        $sellerContent = sprintf(
            '%s vừa đánh giá sản phẩm %s của bạn %d sao%s%s',
            $buyerName,
            $productNames,
            $rating,
            $comment !== '' ? '. Nội dung: ' . $comment : '.',
            $statsText !== '' ? ' ' . $statsText : ''
        );

        $buyerContent = sprintf(
            'Bạn vừa đánh giá sản phẩm %s của seller %s %d sao%s',
            $productNames,
            $sellerName,
            $rating,
            $comment !== '' ? '. Nội dung: ' . $comment : '.'
        );

        $sellerUserIds = $this->repository->userIdsByCompanyId($sellerCompanyId);
        $buyerUserIds = $this->repository->userIdsByCompanyId($buyerCompanyId);

        $actorUserId = (int) $actorUserId;

        if ($actorUserId > 0 && !in_array($actorUserId, $buyerUserIds, true)) {
            $buyerUserIds[] = $actorUserId;
        }

        return [
            'seller_notifications' => $this->notifyUsers($sellerUserIds, 'Bạn có đánh giá mới', $sellerContent),
            'buyer_notifications' => $this->notifyUsers($buyerUserIds, 'Đã gửi đánh giá sản phẩm', $buyerContent),
        ];
    }

    private function notifyUsers(array $userIds, $title, $content)
    {
        $created = 0;

        foreach (array_values(array_unique(array_filter(array_map('intval', $userIds)))) as $userId) {
            if ($userId <= 0) {
                continue;
            }

            $createdId = $this->createForUser($userId, $title, $content);

            if ($createdId > 0) {
                $created++;
            }
        }

        return $created;
    }

    private function productNames($orderId)
    {
        $summary = $this->repository->orderProductSummary((int) $orderId);
        $names = trim((string) ($summary->product_names ?? ''));

        return $names !== '' ? $names : 'sản phẩm trong đơn #' . (int) $orderId;
    }

    private function reviewStatsText($orderId)
    {
        $rows = $this->repository->productReviewStatsForOrder((int) $orderId);

        if (empty($rows)) {
            return '';
        }

        $parts = [];

        foreach ($rows as $row) {
            $count = (int) ($row->review_count ?? 0);
            $avg = round((float) ($row->avg_rating ?? 0), 1);
            $name = $this->cleanOneLine($row->product_name ?? 'Sản phẩm');

            $parts[] = $name . ': ' . $count . ' đánh giá, TB ' . $avg . ' sao';
        }

        return 'Kết quả hiện tại: ' . implode('; ', array_slice($parts, 0, 3)) . '.';
    }

    private function companyName($companyId, $fallbackPrefix)
    {
        $company = $this->companyRepository->findById((int) $companyId);
        $name = trim((string) ($company->company_name ?? ''));

        return $name !== '' ? $name : $fallbackPrefix . ' #' . (int) $companyId;
    }

    private function cleanOneLine($value)
    {
        $value = $this->cleanContent($value);
        $value = preg_replace('/\s+/', ' ', $value);

        return trim((string) $value);
    }

    private function cleanContent($value)
    {
        $value = (string) $value;

        if (function_exists('sanitize_textarea_field')) {
            return sanitize_textarea_field($value);
        }

        return trim(strip_tags($value));
    }
}