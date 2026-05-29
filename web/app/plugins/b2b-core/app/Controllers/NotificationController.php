<?php

class NotificationController
{
    private $service;

    public function __construct()
    {
        $this->service = new NotificationService();
    }

    public function items($request)
    {
        try {
            $params = RequestHelper::params($request);
            $limit = (int) ($params['limit'] ?? 20);

            return ResponseHelper::success(
                $this->service->items(AuthHelper::userId(), $limit)
            );
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 400);
        }
    }

    public function unreadCount($request)
    {
        try {
            return ResponseHelper::success(
                $this->service->unreadCount(AuthHelper::userId())
            );
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 400);
        }
    }

    public function markRead($request)
    {
        try {
            $params = RequestHelper::params($request);
            $notificationId = (int) ($params['notification_id'] ?? $params['id'] ?? 0);

            return ResponseHelper::success(
                $this->service->markRead($notificationId, AuthHelper::userId())
            );
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 400);
        }
    }

    public function markAllRead($request)
    {
        try {
            return ResponseHelper::success(
                $this->service->markAllRead(AuthHelper::userId())
            );
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 400);
        }
    }
}