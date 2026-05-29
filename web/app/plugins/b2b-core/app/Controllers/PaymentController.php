<?php

class PaymentController
{
    private $service;

    public function __construct()
    {
        $this->service = new PaymentService();
    }

    public function create($request)
    {
        try {
            $data = $this->params($request);

            return ResponseHelper::success([
                'payment_id' => $this->service->create(
                    PaymentValidator::orderId($data),
                    $data['payment_method'] ?? 'vnpay'
                )
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function pay($request)
    {
        try {
            $data = $this->params($request);
            $data['company_id'] = $data['company_id'] ?? $this->companyId($data);

            return ResponseHelper::success(
                $this->service->pay(PaymentValidator::paymentId($data), $data)
            );
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function manualCheckout($request)
    {
        try {
            $data = $this->params($request);

            return ResponseHelper::success(
                $this->service->manualCheckout(PaymentValidator::orderId($data))
            );
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function manualConfirm($request)
    {
        try {
            $data = $this->params($request);

            return ResponseHelper::success(
                $this->service->manualConfirm($data)
            );
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function release($request)
    {
        try {
            $data = $this->params($request);

            PaymentValidator::assertAdminOrSupport('Chỉ admin/support mới được duyệt giải ngân.');

            return ResponseHelper::success(
                $this->service->release(PaymentValidator::paymentId($data), false)
            );
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function byOrder($request)
    {
        try {
            $data = $this->params($request);

            return ResponseHelper::success(
                $this->service->byOrder(PaymentValidator::orderId($data))
            );
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function pendingReleases($request)
    {
        try {
            $data = $this->params($request);

            PaymentValidator::assertAdminOrSupport('Chỉ admin/support mới được xem danh sách chờ giải ngân.');

            return ResponseHelper::success(
                $this->service->pendingReleases(PaymentValidator::limit($data))
            );
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function supportSettle($request)
    {
        try {
            $data = $this->params($request);

            PaymentValidator::assertAdminOrSupport('Chỉ admin/support mới được hoàn tiền hoặc xử lý escrow.');

            return ResponseHelper::success(
                $this->service->supportSettle($data)
            );
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    private function params($request)
    {
        return RequestHelper::params($request);
    }

    private function companyId(array $data = [])
    {
        return PaymentValidator::companyId($data);
    }
}
