<?php

class ReviewController
{
    private $service;

    public function __construct()
    {
        $this->service = new ReviewService();
    }

    public function create($request)
    {
        try {
            $data = $this->params($request);
            ReviewValidator::validateCreate($data);

            return ResponseHelper::success($this->service->create($data));
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function byOrder($request)
    {
        try {
            $data = $this->params($request);
            return ResponseHelper::success($this->service->byOrder(ReviewValidator::orderId($data)));
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    private function params($request)
    {
        return RequestHelper::params($request);
    }
}
