<?php

class NegotiationService {
    private $repository;
    private $rfqRepository;

    public function __construct() {
        $this->repository = new NegotiationRepository();
        $this->rfqRepository = new RFQRepository();
    }

    public function send($data) {
        if (empty($data['message'])) {
            throw new Exception('Negotiation message is required');
        }

        if (empty($data['sender_id']) || (int) $data['sender_id'] <= 0) {
            throw new Exception('Missing sender_id');
        }

        $rfq = $this->rfqRepository->findById((int) $data['rfq_id']);

        if (!$rfq) {
            throw new Exception('RFQ not found');
        }

        if ($rfq->status === 'closed') {
            throw new Exception('RFQ is closed');
        }

        $messageId = $this->repository->create([
            'rfq_id' => (int) $data['rfq_id'],
            'sender_id' => (int) $data['sender_id'],
            'sender_type' => $data['sender_type'] ?? 'buyer',
            'message' => function_exists('sanitize_textarea_field')
                ? sanitize_textarea_field((string) $data['message'])
                : trim((string) $data['message']),
            'created_at' => current_time('mysql')
        ]);

        $this->rfqRepository->updateStatus((int) $data['rfq_id'], 'negotiating');

        return $messageId;
    }

    public function list($rfqId) {
        return $this->repository->getByRFQ((int) $rfqId);
    }
}
