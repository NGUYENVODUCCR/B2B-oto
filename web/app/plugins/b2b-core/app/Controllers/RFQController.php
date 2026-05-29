<?php

class RFQController {
    private $service;

    public function __construct() {
        $this->service = new RFQService();
    }

    public function create($request) {
        try {
            $data = $this->params($request);
            $data['buyer_company_id'] = $data['buyer_company_id'] ?? $this->companyId($data);
            $data['created_by'] = $data['created_by'] ?? 'buyer';
            RFQValidator::validate($data);

            $result = $this->service->create($data);
            return ResponseHelper::success($result);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function addItem($request) {
        try {
            $data = $this->params($request);
            RFQValidator::rfqId($data);

            $result = $this->service->addItem($data);
            return ResponseHelper::success($result);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function send($request) {
        try {
            $data = $this->params($request);
            RFQValidator::rfqId($data);

            $result = $this->service->send($data);
            return ResponseHelper::success($result);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function list($request) {
        try {
            $data = $this->params($request);
            $result = $this->service->list($this->companyId($data));
            return ResponseHelper::success($result);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function detail($request) {
        try {
            $data = $this->params($request);
            $result = $this->service->detail(RFQValidator::rfqId($data));
            return ResponseHelper::success($result);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function sendNegotiation($request) {
        try {
            $data = $this->params($request);
            $data['sender_id'] = $data['sender_id'] ?? $this->userId($data);
            $data['company_id'] = $data['company_id'] ?? $this->companyId($data);
            $data['sender_type'] = $data['sender_type'] ?? $this->senderType($data);
            RFQValidator::rfqId($data);

            $service = new NegotiationService();

            return ResponseHelper::success($service->send($data));
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function negotiationList($request) {
        try {
            $data = $this->params($request);
            $rfqId = RFQValidator::rfqId($data);
            $service = new NegotiationService();

            return ResponseHelper::success($service->list($rfqId));
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    private function params($request) {
        return RequestHelper::params($request);
    }

    private function userId($data) {
        return RFQValidator::userId($data);
    }

    private function companyId($data) {
        return RFQValidator::companyId($data);
    }

    private function senderType($data) {
        $companyId = (int) ($data['company_id'] ?? 0);
        $buyerCompanyId = (int) ($data['buyer_company_id'] ?? 0);

        if ($buyerCompanyId <= 0 && !empty($data['rfq_id'])) {
            try {
                $detail = $this->service->detail((int) $data['rfq_id']);
                $buyerCompanyId = (int) $detail['rfq']->buyer_company_id;
            } catch (Exception $e) {
            }
        }

        return ($companyId > 0 && $companyId === $buyerCompanyId) ? 'buyer' : 'seller';
    }
}
