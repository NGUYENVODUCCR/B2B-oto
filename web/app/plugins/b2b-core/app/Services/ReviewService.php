<?php

class ReviewService
{
    private $repository;
    private $orderRepository;
    private $paymentRepository;
    private $notificationService;

    public function __construct()
    {
        $this->repository = new ReviewRepository();
        $this->orderRepository = new OrderRepository();
        $this->paymentRepository = new PaymentRepository();
        $this->notificationService = new NotificationService();
    }

    public function create($data)
    {
        $orderId = (int) ($data['order_id'] ?? 0);
        $rating = (int) ($data['rating'] ?? 0);

        if ($orderId <= 0) {
            throw new Exception('Missing order_id');
        }

        if ($rating < 1 || $rating > 5) {
            throw new Exception('Rating must be between 1 and 5');
        }

        $order = $this->orderRepository->findById($orderId);

        if (!$order || $order->status !== 'completed') {
            throw new Exception('Review is only allowed for completed order');
        }

        $currentCompanyId = 0;
        $currentUserId = 0;

        try {
            $currentCompanyId = (int) AuthHelper::companyId();
            $currentUserId = (int) AuthHelper::userId();
        } catch (Throwable $e) {
            $currentCompanyId = 0;
            $currentUserId = 0;
        }

        if ($currentCompanyId > 0 && (int) $order->buyer_company_id !== $currentCompanyId) {
            throw new Exception('Chỉ bên mua của giao dịch này mới được đánh giá.');
        }

        $payment = $this->paymentRepository->findByOrderId($orderId);

        if (!$payment || $payment->payment_status !== 'released') {
            throw new Exception('Review is only allowed after seller payment release');
        }

        $existing = $this->repository->findByOrderId($orderId);

        if ($existing) {
            return $existing;
        }

        $comment = $data['comment'] ?? '';
        $cleanComment = function_exists('sanitize_textarea_field')
            ? sanitize_textarea_field((string) $comment)
            : trim((string) $comment);

        $reviewId = $this->repository->create([
            'order_id' => $orderId,
            'rating' => $rating,
            'comment' => $cleanComment,
            'created_at' => current_time('mysql')
        ]);

        $review = $this->repository->findByOrderId($orderId);

        $notificationStatus = $this->notificationService->notifyReviewCreated(
            $order,
            $review ?: (object) [
                'id' => $reviewId,
                'order_id' => $orderId,
                'rating' => $rating,
                'comment' => $cleanComment,
                'created_at' => current_time('mysql'),
            ],
            $currentUserId
        );

        if ($review) {
            $review->notification_status = $notificationStatus;
            return $review;
        }

        return [
            'id' => $reviewId,
            'notification_status' => $notificationStatus,
        ];
    }

    public function byOrder($orderId)
    {
        return $this->repository->findByOrderId((int) $orderId);
    }
}