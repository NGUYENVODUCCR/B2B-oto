<?php

class ContractController
{
    private $service;

    public function __construct()
    {
        $this->service = new ContractService();
    }

    public function createFromQuotation($request)
    {
        try {
            $data = $this->params($request);
            $quotationId = ContractValidator::quotationId($data);

            return ResponseHelper::success([
                'contract_id' => $this->service->createFromQuotation($quotationId),
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function byQuotation($request)
    {
        try {
            $data = $this->params($request);
            $quotationId = ContractValidator::quotationId($data);

            return ResponseHelper::success(
                $this->service->detailByQuotation($quotationId)
            );
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function sign($request)
    {
        try {
            $data = $this->params($request);
            $data['user_id'] = $data['user_id'] ?? $this->userId($data);
            $data['company_id'] = $data['company_id'] ?? $this->companyId($data);
            $contractId = ContractValidator::contractId($data);

            return ResponseHelper::success(
                $this->service->sign($contractId, $data)
            );
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function cancel($request)
    {
        try {
            $data = $this->params($request);
            $contractId = ContractValidator::contractId($data);

            return ResponseHelper::success(
                $this->service->cancel($contractId, $data)
            );
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function detail($request)
    {
        try {
            $data = $this->params($request);
            $contractId = ContractValidator::contractId($data);

            return ResponseHelper::success(
                $this->service->detail($contractId)
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

    private function companyId($data)
    {
        return RequestHelper::companyId($data, ['company_id']);
    }
}
