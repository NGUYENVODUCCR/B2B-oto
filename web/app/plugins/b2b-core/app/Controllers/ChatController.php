<?php

class ChatController
{
    private $service;

    public function __construct()
    {
        $this->service = new ChatService();
    }

    public function conversations($request)
    {
        try {
            $data = $this->params($request);

            return ResponseHelper::success(
                $this->service->conversationsForViewer(
                    ChatValidator::optionalCompanyId($data),
                    PermissionHelper::currentRoles()
                )
            );
        } catch (Throwable $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function detail($request)
    {
        try {
            $data = $this->params($request);

            return ResponseHelper::success(
                $this->service->detail($this->rfqId($data), $this->companyId($data))
            );
        } catch (Throwable $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function messages($request)
    {
        try {
            $data = $this->params($request);

            return ResponseHelper::success(
                $this->service->messages($this->rfqId($data), $this->companyId($data))
            );
        } catch (Throwable $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function thread($request)
    {
        try {
            $data = $this->params($request);

            return ResponseHelper::success(
                $this->service->thread($this->rfqId($data), $this->companyId($data))
            );
        } catch (Throwable $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function sendMessage($request)
    {
        try {
            $data = $this->params($request);
            ChatValidator::message($data);

            return ResponseHelper::success(
                $this->service->sendMessage($data, $this->companyId($data), $this->userId($data))
            );
        } catch (Throwable $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    private function params($request)
    {
        return RequestHelper::params($request);
    }

    private function rfqId($data)
    {
        return ChatValidator::rfqId($data);
    }

    private function userId($data)
    {
        return ChatValidator::userId($data);
    }

    private function companyId($data)
    {
        return ChatValidator::companyId($data);
    }
}
