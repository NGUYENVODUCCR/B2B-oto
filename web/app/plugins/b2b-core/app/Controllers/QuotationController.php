<?php

class QuotationController {
    private $service;

    public function __construct() {
        $this->service = new QuotationService();
    }

    public function submit($request) {
        try {
            $data = $this->params($request);
            $data['seller_company_id'] = $data['seller_company_id'] ?? $this->companyId($data);
            QuotationValidator::rfqId($data);
            QuotationValidator::sellerCompanyId($data);

            return ResponseHelper::success(
                $this->service->submit($data)
            );
        } catch (Throwable $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function update($request) {
        try {
            $data = $this->params($request);
            $quotationId = QuotationValidator::quotationId($data);

            return ResponseHelper::success(
                $this->service->update($quotationId, $data)
            );
        } catch (Throwable $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function accept($request) {
        try {
            $data = $this->params($request);
            return ResponseHelper::success(
                $this->service->accept(QuotationValidator::quotationId($data))
            );
        } catch (Throwable $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function reject($request) {
        try {
            $data = $this->params($request);
            return ResponseHelper::success(
                $this->service->reject(QuotationValidator::quotationId($data))
            );
        } catch (Throwable $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function byRFQ($request) {
        try {
            $data = $this->params($request);
            $rfqId = QuotationValidator::rfqId($data);

            return ResponseHelper::success(
                $this->service->byRFQ($rfqId)
            );
        } catch (Throwable $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    private function params($request) {
        return RequestHelper::params($request);
    }

    private function companyId($data) {
        return QuotationValidator::sellerCompanyId($data);
    }
}
