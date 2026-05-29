<?php

class WalletController
{
    private $service;

    public function __construct()
    {
        $this->service = new WalletService();
    }

    public function balance($request)
    {
        try {
            $data = $this->params($request);

            return ResponseHelper::success(
                $this->service->summaryForViewer(
                    WalletValidator::optionalCompanyId($data),
                    PermissionHelper::currentRoles()
                )
            );
        } catch (Throwable $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function deposit($request)
    {
        try {
            return ResponseHelper::success(
                $this->service->deposit($this->companyId($request), $this->params($request))
            );
        } catch (Throwable $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function withdraw($request)
    {
        try {
            return ResponseHelper::success(
                $this->service->withdraw($this->companyId($request), $this->params($request))
            );
        } catch (Throwable $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function cassoWebhook($request)
    {
        try {
            return ResponseHelper::success(
                $this->service->handleCassoWebhook($request)
            );
        } catch (Throwable $e) {
            return ResponseHelper::error($e->getMessage(), 401);
        }
    }

    private function params($request)
    {
        return RequestHelper::params($request);
    }

    private function companyId($request)
    {
        return WalletValidator::companyId($this->params($request));
    }
}
