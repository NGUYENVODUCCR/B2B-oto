<?php

class SupportController
{
    private $service;

    public function __construct()
    {
        $this->service = new SupportService();
    }

    public function createTicket($request)
    {
        try {
            $data = $this->withAuthContext($this->params($request));
            $data['user_id'] = $data['user_id'] ?? SupportValidator::userId($data);

            return ResponseHelper::success($this->service->createTicket($data));
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function sendMessage($request)
    {
        try {
            $data = $this->params($request);
            $data['sender_id'] = $data['sender_id'] ?? SupportValidator::senderId($data);

            return ResponseHelper::success($this->service->sendMessage($data));
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function close($request)
    {
        try {
            $data = $this->params($request);

            return ResponseHelper::success(
                $this->service->close(SupportValidator::ticketId($data), $data['status'] ?? 'closed')
            );
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function list($request)
    {
        try {
            $data = $this->params($request);
            $context = $this->withAuthContext($data);
            $userId = $context['user_id'] ?? SupportValidator::userId($data);
            $all = !empty($data['all']) && PermissionHelper::hasAdminOrSupportRole($context['roles'] ?? []);
            $includeMessages = !empty($data['include_messages']);

            return ResponseHelper::success(
                $this->service->list((int) $userId, $all, $includeMessages, $context)
            );
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function supportAgents($request)
    {
        try {
            return ResponseHelper::success($this->service->supportAgents());
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function detail($request)
    {
        try {
            $data = $this->params($request);

            return ResponseHelper::success(
                $this->service->detail(SupportValidator::ticketId($data))
            );
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function ticketContext($request)
    {
        try {
            $data = $this->params($request);

            return ResponseHelper::success(
                $this->service->ticketContext(SupportValidator::ticketId($data))
            );
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function startBulkPurchase($request)
    {
        try {
            return ResponseHelper::success(
                $this->service->startBulkPurchase($this->withAuthContext($this->params($request)))
            );
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function bulkPurchases($request)
    {
        try {
            return ResponseHelper::success(
                $this->service->bulkPurchases($this->withAuthContext($this->params($request)))
            );
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function requestBulkForm($request)
    {
        try {
            return ResponseHelper::success(
                $this->service->requestBulkForm($this->withAuthContext($this->params($request)))
            );
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function submitBulkProductForm($request)
    {
        try {
            return ResponseHelper::success(
                $this->service->submitBulkProductForm($this->withAuthContext($this->params($request)))
            );
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function sendBulkRFQ($request)
    {
        try {
            return ResponseHelper::success(
                $this->service->sendBulkRFQ($this->withAuthContext($this->params($request)))
            );
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function joinBulkRFQ($request)
    {
        try {
            return ResponseHelper::success(
                $this->service->joinBulkRFQ($this->withAuthContext($this->params($request)))
            );
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function sendBulkToBuyer($request)
    {
        try {
            return ResponseHelper::success(
                $this->service->sendBulkToBuyer($this->withAuthContext($this->params($request)))
            );
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function acceptBulkQuotation($request)
    {
        try {
            return ResponseHelper::success(
                $this->service->acceptBulkQuotation($this->withAuthContext($this->params($request)))
            );
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function sendBulkMessage($request)
    {
        try {
            return ResponseHelper::success(
                $this->service->sendBulkMessage($this->withAuthContext($this->params($request)))
            );
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function sellerChannel($request)
    {
        try {
            return ResponseHelper::success(
                $this->service->sellerChannel($this->withAuthContext($this->params($request)))
            );
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function sendSellerChannelMessage($request)
    {
        try {
            return ResponseHelper::success(
                $this->service->sendSellerChannelMessage($this->withAuthContext($this->params($request)))
            );
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    private function params($request)
    {
        return RequestHelper::params($request);
    }

    private function userId($data)
    {
        return RequestHelper::userId($data, ['auth_user_id']);
    }

    private function withAuthContext($data)
    {
        $data['user_id'] = $data['user_id'] ?? $this->userId($data);
        $data['company_id'] = $data['company_id'] ?? RequestHelper::companyId($data);
        $data['roles'] = $data['roles'] ?? PermissionHelper::currentRoles();

        return $data;
    }
}
