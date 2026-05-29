<?php

class PaymentService {
    private $repository;
    private $orderRepository;
    private $walletService;
    private $invoiceService;
    private $transactionRepository;

    public function __construct() {
        $this->repository = new PaymentRepository();
        $this->orderRepository = new OrderRepository();
        $this->walletService = new WalletService();
        $this->invoiceService = new InvoiceService();
        $this->transactionRepository = new TransactionRepository();
    }

    public function create($orderId, $method = 'vnpay') {
        $existing = $this->repository->findByOrderId((int) $orderId);

        if ($existing) {
            return (int) $existing->id;
        }

        $order = $this->orderRepository->findById((int) $orderId);

        if (!$order) {
            throw new Exception('Order not found');
        }

        return $this->repository->create([
            'order_id' => $orderId,
            'payment_status' => 'pending',
            'payment_method' => $method,
            'amount' => (float) $order->total_amount,
            'created_at' => current_time('mysql')
        ]);
    }

    public function pay($paymentId, $data = []) {
        $payment = $this->repository->findById((int) $paymentId);

        if (!$payment) {
            throw new Exception('Payment not found');
        }

        if ($payment->payment_status === 'released') {
            throw new Exception('Payment already released');
        }

        $order = $this->orderRepository->findById((int) $payment->order_id);

        if (!$order) {
            throw new Exception('Order not found');
        }

        if ($order->status !== 'pending') {
            throw new Exception('Only pending order can be paid');
        }

        $payerCompanyId = (int) ($data['company_id'] ?? 0);

        if ($payerCompanyId > 0 && $payerCompanyId !== (int) $order->buyer_company_id) {
            throw new Exception('Only buyer can pay this order');
        }

        $gatewayRef = $data['gateway_ref'] ?? ('manual-' . $payment->id . '-' . time());

        $this->transactionRepository->start();

        try {
            $this->walletService->chargeEscrow(
                $order->buyer_company_id,
                (float) $order->total_amount,
                $payment->id,
                $order->id
            );

            $this->repository->update($payment->id, [
                'payment_status' => 'escrow',
                'gateway_ref' => $gatewayRef,
                'amount' => (float) $order->total_amount,
                'paid_at' => current_time('mysql')
            ]);

            $this->orderRepository->update($order->id, [
                'status' => 'paid'
            ]);

            $this->transactionRepository->commit();

            $paidPayment = $this->repository->findById($payment->id);

            try {
                $paidPayment->invoice = $this->invoiceService->issueForOrder($order->id, $paidPayment);
                $paidPayment->invoice_email_sent = true;
            } catch (Throwable $e) {
                error_log('[B2B INVOICE ERROR] ' . $e->getMessage());
                $paidPayment->invoice_error = $e->getMessage();
            }

            return $paidPayment;
        } catch (Exception $e) {
            $this->transactionRepository->rollback();
            throw $e;
        }
    }

    public function release($paymentId, $force = false) {
        $payment = $this->repository->findById((int) $paymentId);

        if (!$payment) {
            throw new Exception('Payment not found');
        }

        if ($payment->payment_status === 'released') {
            return $payment;
        }

        if (!in_array($payment->payment_status, ['escrow', 'paid'], true)) {
            throw new Exception('Only escrow payment can be released');
        }

        $order = $this->orderRepository->findById((int) $payment->order_id);

        if (!$order) {
            throw new Exception('Order not found');
        }

        if (!$force && $order->status !== 'completed') {
            throw new Exception('Order must be completed before payment release');
        }

        $settlement = $this->settlementBreakdown((float) $payment->amount);

        $this->transactionRepository->start();

        try {
            $this->walletService->releaseEscrow(
                $payment,
                $order,
                $settlement['seller_payout_amount'],
                0,
                $force ? 'auto_or_forced_release' : 'buyer_confirmed',
                $settlement
            );

            $this->repository->update($payment->id, [
                'payment_status' => 'released',
                'gross_release_amount' => $settlement['gross_release_amount'],
                'platform_fee_rate' => $settlement['platform_fee_rate'],
                'platform_fee_amount' => $settlement['platform_fee_amount'],
                'seller_payout_amount' => $settlement['seller_payout_amount'],
                'released_at' => current_time('mysql')
            ]);

            $this->transactionRepository->commit();

            return $this->repository->findById($payment->id);
        } catch (Exception $e) {
            $this->transactionRepository->rollback();
            throw $e;
        }
    }

    public function releaseByOrder($orderId, $force = false) {
        $payment = $this->repository->findByOrderId((int) $orderId);

        if (!$payment) {
            throw new Exception('Payment not found');
        }

        return $this->release($payment->id, $force);
    }

    public function failByOrder($orderId) {
        $payment = $this->repository->findByOrderId((int) $orderId);

        if (!$payment) {
            return null;
        }

        if ($payment->payment_status === 'released') {
            throw new Exception('Released payment cannot be failed');
        }

        $order = $this->orderRepository->findById((int) $orderId);

        if (!$order) {
            throw new Exception('Order not found');
        }

        $this->transactionRepository->start();

        try {
            if (in_array($payment->payment_status, ['escrow', 'paid'], true)) {
                $this->walletService->releaseEscrow(
                    $payment,
                    $order,
                    0,
                    (float) $payment->amount,
                    'order_cancelled_refund'
                );
            }

            $this->repository->update($payment->id, [
                'payment_status' => 'failed'
            ]);

            $this->transactionRepository->commit();
        } catch (Exception $e) {
            $this->transactionRepository->rollback();
            throw $e;
        }

        return $this->repository->findById($payment->id);
    }

    public function byOrder($orderId) {
        $payment = $this->repository->findByOrderId((int) $orderId);

        if (!$payment) {
            return null;
        }

        $payment->settlement = $this->walletService->settlement($payment->id);

        return $payment;
    }

    public function pendingReleases($limit = 200) {
        $rows = $this->repository->pendingReleases((int) $limit);

        return array_map(function ($row) {
            return [
                'payment_id' => (int) ($row['payment_id'] ?? 0),
                'payment_status' => (string) ($row['payment_status'] ?? 'pending'),
                'payment_amount' => (float) ($row['payment_amount'] ?? 0),
                'payment_created_at' => $row['payment_created_at'] ?? null,
                'paid_at' => $row['paid_at'] ?? null,
                'order_id' => (int) ($row['order_id'] ?? 0),
                'contract_id' => (int) ($row['contract_id'] ?? 0),
                'rfq_id' => (int) ($row['rfq_id'] ?? 0),
                'buyer_company_id' => (int) ($row['buyer_company_id'] ?? 0),
                'seller_company_id' => (int) ($row['seller_company_id'] ?? 0),
                'buyer_company_name' => (string) ($row['buyer_company_name'] ?? ''),
                'seller_company_name' => (string) ($row['seller_company_name'] ?? ''),
                'order_total_amount' => (float) ($row['order_total_amount'] ?? 0),
                'order_status' => (string) ($row['order_status'] ?? ''),
                'order_created_at' => $row['order_created_at'] ?? null,
                'order_updated_at' => $row['order_updated_at'] ?? null,
            ];
        }, $rows);
    }

    public function supportSettle($data) {
        $paymentId = (int) ($data['payment_id'] ?? 0);
        $orderId = (int) ($data['order_id'] ?? 0);
        $refundAmount = round((float) ($data['refund_amount'] ?? 0), 2);
        $reason = $data['reason'] ?? 'support_settlement';

        $payment = $paymentId > 0
            ? $this->repository->findById($paymentId)
            : $this->repository->findByOrderId($orderId);

        if (!$payment) {
            throw new Exception('Payment not found');
        }

        if (!in_array($payment->payment_status, ['escrow', 'paid'], true)) {
            throw new Exception('Only escrow payment can be settled by support');
        }
        if ($orderId > 0 && $orderId !== (int) $payment->order_id) {
            throw new Exception('Order does not match payment');
        }

        $order = $this->orderRepository->findById((int) $payment->order_id);

        if (!$order) {
            throw new Exception('Order not found');
        }

        if ((int) ($order->buyer_company_id ?? 0) <= 0 || (int) ($order->seller_company_id ?? 0) <= 0) {
            throw new Exception('Order parties are missing');
        }

        if ($refundAmount < 0 || $refundAmount > (float) $payment->amount) {
            throw new Exception('Invalid refund amount');
        }

        $releaseAmount = round((float) $payment->amount - $refundAmount, 2);
        $breakdown = $this->settlementBreakdown($releaseAmount);

        $this->transactionRepository->start();

        try {
            $settlement = $this->walletService->releaseEscrow(
                $payment,
                $order,
                $breakdown['seller_payout_amount'],
                $refundAmount,
                $reason,
                $breakdown
            );

            if ($releaseAmount <= 0) {
                $this->repository->update($payment->id, [
                    'payment_status' => 'failed'
                ]);

                $this->orderRepository->update($order->id, [
                    'status' => 'cancelled'
                ]);
            } else {
                $this->repository->update($payment->id, [
                    'payment_status' => 'released',
                    'gross_release_amount' => $breakdown['gross_release_amount'],
                    'platform_fee_rate' => $breakdown['platform_fee_rate'],
                    'platform_fee_amount' => $breakdown['platform_fee_amount'],
                    'seller_payout_amount' => $breakdown['seller_payout_amount'],
                    'released_at' => current_time('mysql')
                ]);

                $this->orderRepository->update($order->id, [
                    'status' => 'completed'
                ]);
            }

            $this->transactionRepository->commit();

            return [
                'payment' => $this->byOrder($payment->order_id),
                'settlement' => $settlement
            ];
        } catch (Exception $e) {
            $this->transactionRepository->rollback();
            throw $e;
        }
    }

    public function callback($data) {
        $this->transactionRepository->start();

        try {
            $payment = $this->repository
                ->findByTransaction($data['txn_ref']);

            if (!$payment) {
                throw new Exception('Payment not found');
            }

            $this->repository->update($payment->id, [
                'payment_status' => 'escrow',
                'paid_at' => current_time('mysql')
            ]);

            $this->orderRepository->update(
                $payment->order_id,
                ['status' => 'paid']
            );

            $this->transactionRepository->commit();

        } catch (Exception $e) {
            $this->transactionRepository->rollback();
            throw $e;
        }
    }

    public function autoReleaseOverdueEscrow($days = 7) {
        $payments = $this->repository->overdueEscrow((int) $days);
        $released = [];

        foreach ($payments as $payment) {
            $this->orderRepository->update($payment->order_id, [
                'status' => 'completed'
            ]);

            $released[] = $this->release($payment->id, true);
        }

        return $released;
    }

    public function manualCheckout($orderId)
    {
        $paymentId = $this->create($orderId, 'bank_transfer');

        $payment = $this->repository->findById($paymentId);
        $order = $this->orderRepository->findById((int) $payment->order_id);

        if (!$payment || !$order) {
            throw new Exception('Payment or order not found');
        }

        if ($payment->payment_status === 'escrow') {
            throw new Exception('Order already paid');
        }

        $transferCode = 'B2B' . $order->id . 'P' . $payment->id;
        $bankBin = '970422';
        $bankName = 'MB Bank';
        $bankAccountNumber = '0207729018888';
        $bankAccountName = 'HUYNH DUC HIEU';

        $this->repository->update($payment->id, [
            'payment_method' => 'bank_transfer',
            'gateway_ref' => $transferCode
        ]);

        return [
            'payment_id' => (int) $payment->id,
            'order_id' => (int) $order->id,
            'amount' => (float) $payment->amount,

            'bank_name' => $bankName,
            'bank_bin' => $bankBin,
            'bank_account_number' => $bankAccountNumber,
            'bank_account_name' => $bankAccountName,
            'transfer_content' => $transferCode,
            'qr_image' => home_url('/app/themes/my-theme/resources/images/b2b-payment-qr.png'),
            'vietqr_url' =>
                'https://img.vietqr.io/image/' . $bankBin . '-' . $bankAccountNumber . '-compact2.png'
                . '?amount=' . (int)$payment->amount
                . '&addInfo=' . rawurlencode($transferCode)
                . '&accountName=' . rawurlencode($bankAccountName),

            'note' => 'Buyer quét QR hoặc chuyển khoản đúng nội dung để admin xác nhận escrow.'
        ];
    }

    public function manualConfirm($data)
    {
        $paymentId = (int) ($data['payment_id'] ?? 0);
        $roleFromBody = $data['auth_role'] ?? null;

        $roles = [];

        if (!empty($roleFromBody)) {
            $roles = is_array($roleFromBody) ? $roleFromBody : [$roleFromBody];
        } else {
            $roleFromToken = AuthHelper::role();
            $roles = is_array($roleFromToken) ? $roleFromToken : [$roleFromToken];
        }

            $roles = array_map('strtoupper', array_filter($roles));

            $allowedRoles = [
                'ROLE_SUPPORT',
                'SUPPORT',
                'ROLE_ADMIN',
                'ADMIN',
                'ADMINISTRATOR'
            ];

        if (empty(array_intersect($roles, $allowedRoles))) {
            throw new Exception(
                'Only admin/support can confirm manual payment. Current role: ' . implode(',', $roles)
            );
        }
            $payment = $this->repository->findById($paymentId);

            if (!$payment) {
                throw new Exception('Payment not found');
            }

            if ($payment->payment_status === 'escrow') {
                return $payment;
            }

            if ($payment->payment_status === 'released') {
                throw new Exception('Payment already released');
            }

            $order = $this->orderRepository->findById((int) $payment->order_id);

            if (!$order) {
                throw new Exception('Order not found');
            }

            if ($order->status !== 'pending') {
                throw new Exception('Only pending order can be confirmed as paid');
            }

            $this->transactionRepository->start();

            try {
                $this->walletService->chargeEscrow(
                    $order->buyer_company_id,
                    (float) $payment->amount,
                    $payment->id,
                    $order->id
                );

                $this->repository->update($payment->id, [
                    'payment_status' => 'escrow',
                    'paid_at' => current_time('mysql')
                ]);

                $this->orderRepository->update($order->id, [
                    'status' => 'paid'
                ]);

                $this->transactionRepository->commit();

                $paidPayment = $this->repository->findById($payment->id);

                try {
                    $paidPayment->invoice = $this->invoiceService->issueForOrder($order->id, $paidPayment);
                    $paidPayment->invoice_email_sent = true;
                } catch (Throwable $e) {
                    error_log('[B2B INVOICE ERROR] ' . $e->getMessage());
                    $paidPayment->invoice_error = $e->getMessage();
                }

                return $paidPayment;
            } catch (Exception $e) {
                $this->transactionRepository->rollback();
                throw $e;
            }
        }

    private function settlementBreakdown($grossReleaseAmount)
    {
        $grossReleaseAmount = round(max(0, (float) $grossReleaseAmount), 2);
        $feeRate = $this->platformFeeRate();
        $platformFeeAmount = round($grossReleaseAmount * $feeRate, 2);
        $sellerPayoutAmount = round(max(0, $grossReleaseAmount - $platformFeeAmount), 2);

        return [
            'gross_release_amount' => $grossReleaseAmount,
            'platform_fee_rate' => $feeRate,
            'platform_fee_amount' => $platformFeeAmount,
            'seller_payout_amount' => $sellerPayoutAmount,
        ];
    }

    private function platformFeeRate()
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
            return 0.03;
        }

        return round($rate, 4);
    }
}
