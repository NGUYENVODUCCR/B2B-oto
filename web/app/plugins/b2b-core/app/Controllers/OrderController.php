<?php

class OrderController
{
    private $service;

    public function __construct()
    {
        $this->service = new OrderService();
    }

    public function createFromContract($request)
    {
        try {
            $data = $this->params($request);

            return ResponseHelper::success([
                'order_id' => $this->service->createFromContract(OrderValidator::contractId($data)),
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function detail($request)
    {
        try {
            $data = $this->params($request);
            $orderId = OrderValidator::orderId($data);

            return ResponseHelper::success($this->service->detail($orderId));
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function list($request)
    {
        try {
            $data = $this->params($request);
            $companyId = OrderValidator::optionalCompanyId($data);
            $search = $data['search'] ?? ($data['q'] ?? '');
            $roles = $this->roles();

            return ResponseHelper::success($this->service->list((int) $companyId, [
                'roles' => $roles,
                'search' => $search,
            ]));
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function sellerHistory($request)
    {
        try {
            $data = $this->params($request);
            $companyId = $this->companyId($data);

            return ResponseHelper::success($this->service->sellerHistory($companyId, $data));
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function cancel($request)
    {
        try {
            $data = $this->params($request);
            return ResponseHelper::success(
                $this->service->cancel(OrderValidator::orderId($data), $data['reason'] ?? '')
            );
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function markDelivering($request)
    {
        try {
            $data = $this->params($request);
            return ResponseHelper::success(
                $this->service->markDelivering(OrderValidator::orderId($data), $this->companyId($data))
            );
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function complete($request)
    {
        try {
            $data = $this->params($request);
            return ResponseHelper::success(
                $this->service->complete(OrderValidator::orderId($data), $this->companyId($data))
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
        return OrderValidator::companyId($data);
    }

    private function roles()
    {
        return PermissionHelper::currentRoles();
    }
}
