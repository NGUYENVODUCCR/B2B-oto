<?php

class SellerRequestController
{
    private $service;

    public function __construct()
    {
        $this->service = new SellerRequestService();
    }

    public function create($request)
    {
        try {
            $userId = RequestHelper::currentUserId();
            $data = RequestHelper::params($request);
            $files = RequestHelper::fileParams($request);

            $result = $this->service->create(
                $userId,
                $data,
                $files
            );

            return ResponseHelper::success($result);

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 400);
        }
    }

    public function approve($request)
    {
        try {
            $reviewerId = RequestHelper::currentUserId();

            $result = $this->service->approve(
                $reviewerId,
                RequestHelper::jsonParams($request)
            );

            return ResponseHelper::success($result);

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 400);
        }
    }

    public function reject($request)
    {
        try {
            $reviewerId = RequestHelper::currentUserId();

            $result = $this->service->reject(
                $reviewerId,
                RequestHelper::jsonParams($request)
            );

            return ResponseHelper::success($result);

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 400);
        }
    }

    public function myRequest()
    {
        try {
            $userId = RequestHelper::currentUserId();

            $result = $this->service->myRequest($userId);

            return ResponseHelper::success($result);

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 400);
        }
    }

    public function list()
    {
        try {
            $userId = RequestHelper::currentUserId();

            $result = $this->service->list($userId);

            return ResponseHelper::success($result);

        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 400);
        }
    }
}
