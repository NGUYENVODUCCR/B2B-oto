<?php

class RFQService {
    private $rfqRepository;
    private $rfqItemRepository;
    private $quotationRepository;
    private $productRepository;
    private $transactionRepository;

    public function __construct() {
        $this->rfqRepository = new RFQRepository();
        $this->rfqItemRepository = new RFQItemRepository();
        $this->quotationRepository = new QuotationRepository();
        $this->productRepository = new ProductRepository();
        $this->transactionRepository = new TransactionRepository();
    }

    public function create($data) {
        $buyerCompanyId = $this->positiveInt($data, 'buyer_company_id');
        $items = $this->normalizeItems($data);

        if (empty($items)) {
            throw new Exception('RFQ must have at least one item');
        }

        $this->transactionRepository->start();

        try {
            $rfqId = $this->rfqRepository->create([
                'buyer_company_id' => $buyerCompanyId,
                'message' => $this->text($data['message'] ?? ($data['description'] ?? '')),
                'rfq_type' => $this->rfqType($data['rfq_type'] ?? 'direct'),
                'created_by' => $this->createdBy($data['created_by'] ?? 'buyer'),
                'status' => 'pending',
                'created_at' => current_time('mysql')
            ]);

            try {
                $bulkId = (int) ($data['bulk_id'] ?? 0);

                if ($bulkId > 0) {
                    $this->rfqRepository->update($rfqId, [
                        'bulk_id' => $bulkId,
                    ]);
                }
            } catch (Exception $e) {
                
            }

            foreach ($items as $item) {
                $this->createItem($rfqId, $item);
            }

            $this->transactionRepository->commit();

            return $this->detail($rfqId);
        } catch (Exception $e) {
            $this->transactionRepository->rollback();
            throw $e;
        }
    }

    public function addItem($data) {
        $rfqId = $this->positiveInt($data, 'rfq_id');
        $rfq = $this->rfqRepository->findById($rfqId);

        if (!$rfq) {
            throw new Exception('RFQ not found');
        }

        if ($rfq->status === 'closed') {
            throw new Exception('RFQ is closed');
        }

        $itemId = $this->createItem($rfqId, $data);

        return [
            'item_id' => $itemId,
            'rfq' => $this->detail($rfqId)
        ];
    }

    public function send($data) {
        $rfqId = $this->positiveInt($data, 'rfq_id');
        $rfq = $this->rfqRepository->findById($rfqId);

        if (!$rfq) {
            throw new Exception('RFQ not found');
        }

        if ($this->rfqItemRepository->countByRFQId($rfqId) <= 0) {
            throw new Exception('RFQ has no item');
        }

        $this->rfqRepository->updateStatus($rfqId, 'pending');

        return $this->detail($rfqId);
    }

    public function list($companyId) {
        if ((int) $companyId <= 0) {
            throw new Exception('Missing company_id');
        }

        return $this->rfqRepository->listForCompany((int) $companyId);
    }

    public function detail($rfqId) {
        $rfq = $this->rfqRepository->findById((int) $rfqId);

        if (!$rfq) {
            throw new Exception('RFQ not found');
        }

        return [
            'rfq' => $rfq,
            'items' => $this->rfqItemRepository->findByRFQId($rfq->id),
            'seller_company_ids' => $this->rfqRepository->sellerCompanyIds($rfq->id),
            'quotations' => $this->quotationRepository->getByRFQ($rfq->id)
        ];
    }

    private function createItem($rfqId, $data) {
        $productId = $this->positiveInt($data, 'product_id');
        $quantity = $this->positiveInt($data, 'quantity', 1);
        $product = $this->productRepository->findById($productId);

        if (!$product) {
            throw new Exception('Product not found');
        }

        return $this->rfqItemRepository->create([
            'rfq_id' => (int) $rfqId,
            'product_id' => $productId,
            'quantity' => $quantity,
            'note' => $this->text($data['note'] ?? ''),
            'created_at' => current_time('mysql')
        ]);
    }

    private function normalizeItems($data) {
        if (!empty($data['items']) && is_array($data['items'])) {
            return $data['items'];
        }

        if (!empty($data['product_id'])) {
            return [[
                'product_id' => $data['product_id'],
                'quantity' => $data['quantity'] ?? 1,
                'note' => $data['note'] ?? ''
            ]];
        }

        return [];
    }

    private function positiveInt($data, $key, $default = null) {
        $value = $data[$key] ?? $default;
        $value = (int) $value;

        if ($value <= 0) {
            throw new Exception('Missing or invalid ' . $key);
        }

        return $value;
    }

    private function text($value) {
        return function_exists('sanitize_textarea_field')
            ? sanitize_textarea_field((string) $value)
            : trim((string) $value);
    }

    private function rfqType($value) {
        return in_array($value, ['direct', 'assisted'], true) ? $value : 'direct';
    }

    private function createdBy($value) {
        return in_array($value, ['buyer', 'support'], true) ? $value : 'buyer';
    }
}
