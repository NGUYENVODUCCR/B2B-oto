<?php

class StatisticsController
{
    private $service;

    public function __construct()
    {
        $this->service = new StatisticsService();
    }

    public function revenue($request)
    {
        try {
            return ResponseHelper::success($this->service->revenue(StatisticsValidator::revenueFilters(RequestHelper::params($request))));
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 400);
        }
    }

    public function productReviewCounts($request)
    {
        try {
            return ResponseHelper::success($this->service->productReviewCounts());
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 400);
        }
    }
}
