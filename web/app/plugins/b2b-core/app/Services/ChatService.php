<?php

class ChatService
{
    private $rfqService;
    private $negotiationService;

    public function __construct()
    {
        $this->rfqService = new RFQService();
        $this->negotiationService = new NegotiationService();
    }

    public function conversationsForViewer($companyId, array $roles = [])
    {
        $companyId = (int) $companyId;

        if ($companyId <= 0) {
            if (PermissionHelper::hasAdminOrSupportRole($roles)) {
                return [];
            }

            throw new Exception('Thiếu company_id');
        }

        return $this->conversations($companyId);
    }


    public function conversations($companyId)
    {
        return $this->rfqService->list((int) $companyId);
    }

    public function detail($rfqId, $companyId)
    {
        $detail = $this->rfqService->detail((int) $rfqId);
        $this->assertParticipant($detail, (int) $companyId);

        return $detail;
    }

    public function messages($rfqId, $companyId)
    {
        $this->detail((int) $rfqId, (int) $companyId);

        return $this->negotiationService->list((int) $rfqId);
    }

    public function thread($rfqId, $companyId)
    {
        return [
            'detail' => $this->detail((int) $rfqId, (int) $companyId),
            'messages' => $this->messages((int) $rfqId, (int) $companyId)
        ];
    }

    public function sendMessage($data, $companyId, $userId)
    {
        $rfqId = (int) ($data['rfq_id'] ?? ($data['id'] ?? 0));
        $detail = $this->detail($rfqId, (int) $companyId);
        $senderType = $this->participantRole($detail, (int) $companyId);

        return $this->negotiationService->send([
            'rfq_id' => $rfqId,
            'sender_id' => (int) $userId,
            'company_id' => (int) $companyId,
            'sender_type' => $senderType,
            'message' => $data['message'] ?? ''
        ]);
    }

    private function assertParticipant($detail, $companyId)
    {
        if (!$this->participantRole($detail, $companyId)) {
            throw new Exception('Company does not belong to this chat');
        }
    }

    private function participantRole($detail, $companyId)
    {
        $rfq = $detail['rfq'] ?? null;

        if (!$rfq) {
            return null;
        }

        if ((int) $rfq->buyer_company_id === (int) $companyId) {
            return 'buyer';
        }

        $sellerIds = array_map('intval', $detail['seller_company_ids'] ?? []);

        return in_array((int) $companyId, $sellerIds, true) ? 'seller' : null;
    }
}
