<?php

class QuotationService {
    private $quotationRepository;
    private $quotationItemRepository;
    private $rfqRepository;
    private $rfqItemRepository;
    private $productRepository;
    private $contractService;
    private $transactionRepository;

    public function __construct() {
        $this->quotationRepository = new QuotationRepository();
        $this->quotationItemRepository = new QuotationItemRepository();
        $this->rfqRepository = new RFQRepository();
        $this->rfqItemRepository = new RFQItemRepository();
        $this->productRepository = new ProductRepository();
        $this->contractService = new ContractService();
        $this->transactionRepository = new TransactionRepository();
    }

    public function submit($data) {
        $rfqId = $this->positiveInt($data, 'rfq_id');
        $sellerCompanyId = $this->positiveInt($data, 'seller_company_id');
        $rfq = $this->rfqRepository->findById($rfqId);

        if (!$rfq) {
            throw new Exception('RFQ not found');
        }

        if ($rfq->status === 'closed') {
            throw new Exception('RFQ is closed');
        }

        if ($this->quotationRepository->findByRFQAndSeller($rfqId, $sellerCompanyId)) {
            throw new Exception('Seller already submitted quotation for this RFQ');
        }

        $items = $this->normalizeQuotationItems($data, $rfqId, $sellerCompanyId);

        $this->transactionRepository->start();

        try {
            $quotationId = $this->quotationRepository->create([
                'rfq_id' => $rfqId,
                'seller_company_id' => $sellerCompanyId,
                'status' => 'pending',
                'valid_until' => $this->nullableDate($data['valid_until'] ?? null),
                'is_selected' => 0,
                'created_at' => current_time('mysql')
            ]);

            $this->quotationItemRepository->createMany($quotationId, $items);
            $this->savePricing($quotationId, $items);
            $this->rfqRepository->updateStatus($rfqId, 'quoted');

            $this->transactionRepository->commit();

            return $this->detail($quotationId);

        } catch (Exception $e) {
            $this->transactionRepository->rollback();
            throw $e;
        }
    }

    public function update($quotationId, $data) {
        $quotation = $this->quotationRepository->findById((int) $quotationId);

        if (!$quotation) {
            throw new Exception('Quotation not found');
        }

        if (in_array($quotation->status, ['accepted', 'rejected', 'expired'], true)) {
            throw new Exception('Quotation cannot be updated');
        }

        $items = $this->normalizeQuotationItems(
            $data,
            (int) $quotation->rfq_id,
            (int) $quotation->seller_company_id
        );

        $this->transactionRepository->start();

        try {
            $this->quotationRepository->update($quotationId, [
                'status' => 'pending',
                'valid_until' => $this->nullableDate($data['valid_until'] ?? $quotation->valid_until),
                'is_selected' => 0
            ]);

            $this->quotationItemRepository->deleteByQuotationId($quotationId);
            $this->quotationItemRepository->createMany($quotationId, $items);
            $this->savePricing($quotationId, $items);

            $this->transactionRepository->commit();

            return $this->detail($quotationId);
        } catch (Exception $e) {
            $this->transactionRepository->rollback();
            throw $e;
        }
    }

    public function accept($quotationId) {
        $this->transactionRepository->start();

        try {
            $quotation = $this->quotationRepository->findById($quotationId);

            if (!$quotation) {
                throw new Exception('Quotation not found');
            }

            if ($quotation->status !== 'pending') {
                throw new Exception('Only pending quotation can be accepted');
            }

            $this->quotationRepository->update($quotationId, [
                'status' => 'accepted',
                'is_selected' => 1
            ]);

            $this->quotationRepository->rejectOthers(
                $quotation->rfq_id,
                $quotationId
            );

            $this->rfqRepository->update(
                $quotation->rfq_id,
                ['status' => 'closed']
            );

            $contractId = $this->contractService
                ->createFromQuotation($quotationId);

            $this->transactionRepository->commit();

            return [
                'quotation_id' => $quotationId,
                'contract_id' => $contractId,
                'quotation' => $this->detail($quotationId)
            ];

        } catch (Exception $e) {
            $this->transactionRepository->rollback();
            throw $e;
        }
    }

    public function reject($quotationId) {
        $quotation = $this->quotationRepository->findById((int) $quotationId);

        if (!$quotation) {
            throw new Exception('Quotation not found');
        }

        if ($quotation->status === 'accepted') {
            throw new Exception('Accepted quotation cannot be rejected');
        }

        $this->quotationRepository->update($quotationId, [
            'status' => 'rejected',
            'is_selected' => 0
        ]);

        if (!$this->quotationRepository->hasOpenByRFQ($quotation->rfq_id)) {
            $this->rfqRepository->updateStatus($quotation->rfq_id, 'closed');
        }

        return $this->detail($quotationId);
    }

    public function byRFQ($rfqId) {
        $rows = $this->quotationRepository->getByRFQ((int) $rfqId);
        $result = [];

        foreach ($rows as $row) {
            $result[] = $this->detail($row->id);
        }

        return $result;
    }

    public function detail($quotationId) {
        $quotation = $this->quotationRepository->findById((int) $quotationId);

        if (!$quotation) {
            throw new Exception('Quotation not found');
        }

        $items = $this->quotationItemRepository->findByQuotationId($quotation->id);
        $pricing = $this->pricingForDetail($quotation->id, $items);

        return [
            'quotation' => $quotation,
            'items' => $items,
            'pricing' => $pricing,
            'subtotal_amount' => $pricing['subtotal_amount'],
            'discount_total' => $pricing['discount_total'],
            'total_amount' => $pricing['total_amount']
        ];
    }

    private function normalizeQuotationItems($data, $rfqId, $sellerCompanyId) {
        $inputItems = [];

        if (!empty($data['items']) && is_array($data['items'])) {
            $inputItems = $data['items'];
        } elseif (!empty($data['product_id'])) {
            $inputItems = [[
                'product_id' => $data['product_id'],
                'quantity' => $data['quantity'] ?? 1,
                'price' => $data['price'] ?? ($data['unit_price'] ?? ($data['total_amount'] ?? null))
            ]];
        } else {
            $rfqItems = $this->rfqItemRepository->findByRFQId($rfqId);
            $inputItems = $this->itemsFromRFQ($rfqItems, $data);
        }

        if (empty($inputItems)) {
            throw new Exception('Quotation must have items');
        }

        $items = [];

        foreach ($inputItems as $item) {
            $productId = $this->positiveInt($item, 'product_id');
            $quantity = $this->positiveInt($item, 'quantity', 1);
            $product = $this->productRepository->findById($productId);

            if (!$product) {
                throw new Exception('Product not found');
            }

            if ((int) $product->company_id !== (int) $sellerCompanyId) {
                throw new Exception('Quotation item does not belong to seller company');
            }

            $unitPrice = $this->unitPriceForItem($item, $quantity);
            $discountPercent = $this->discountPercent($item['discount_percent'] ?? ($item['discount'] ?? 0));
            $price = round($unitPrice * (1 - ($discountPercent / 100)), 2);

            if ($price < 0) {
                throw new Exception('Invalid quotation price');
            }

            $items[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'price' => $price,
                'unit_price' => $unitPrice,
                'discount_percent' => $discountPercent,
                'discount_amount' => round(($unitPrice - $price) * $quantity, 2),
                'line_total' => round($price * $quantity, 2)
            ];
        }

        return $items;
    }

    private function itemsFromRFQ($rfqItems, $data) {
        $items = [];

        foreach ($rfqItems as $index => $rfqItem) {
            $price = $data['price'] ?? ($data['unit_price'] ?? null);

            if ($price === null && isset($data['total_amount']) && $index === 0) {
                $price = ((float) $data['total_amount']) / max(1, (int) $rfqItem->quantity);
            }

            if ($price === null && isset($rfqItem->price_from)) {
                $price = $rfqItem->price_from;
            }

            $items[] = [
                'product_id' => $rfqItem->product_id,
                'quantity' => $rfqItem->quantity,
                'price' => $price ?? 0
            ];
        }

        return $items;
    }

    private function unitPriceForItem($item, $quantity) {
        if (isset($item['original_price'])) {
            return (float) $item['original_price'];
        }

        if (isset($item['unit_price'])) {
            return (float) $item['unit_price'];
        }

        if (isset($item['price'])) {
            return (float) $item['price'];
        }

        if (isset($item['total_amount'])) {
            return ((float) $item['total_amount']) / max(1, (int) $quantity);
        }

        return 0;
    }

    private function discountPercent($value) {
        $value = (float) $value;

        if ($value < 0) {
            return 0;
        }

        if ($value > 100) {
            return 100;
        }

        return $value;
    }

    private function savePricing($quotationId, $items) {
        $pricing = $this->pricingFromItems($items);

        $this->quotationRepository->update((int) $quotationId, [
            'subtotal_amount' => $pricing['subtotal_amount'],
            'discount_total' => $pricing['discount_total'],
            'total_amount' => $pricing['total_amount'],
            'pricing_json' => function_exists('wp_json_encode')
                ? wp_json_encode($pricing)
                : json_encode($pricing)
        ]);
    }

    private function pricingForDetail($quotationId, $items) {
        $quotation = $this->quotationRepository->findById((int) $quotationId);

        if ($quotation) {
            $pricing = $this->decodePricing($quotation->pricing_json ?? '');

            if (is_array($pricing)) {
                return $this->normalizePricingPayload($pricing, $items);
            }

            $hasStoredTotals = isset($quotation->subtotal_amount)
                || isset($quotation->discount_total)
                || isset($quotation->total_amount);

            if ($hasStoredTotals) {
                return $this->normalizePricingPayload([
                    'lines' => [],
                    'subtotal_amount' => round((float) ($quotation->subtotal_amount ?? 0), 2),
                    'discount_total' => round((float) ($quotation->discount_total ?? 0), 2),
                    'total_amount' => round((float) ($quotation->total_amount ?? 0), 2),
                ], $items);
            }
        }

        return $this->pricingFromItemModels($items);
    }

    private function pricingFromItems($items) {
        $subtotal = 0;
        $discount = 0;
        $total = 0;
        $lines = [];

        foreach ($items as $item) {
            $quantity = (int) ($item['quantity'] ?? 0);
            $unitPrice = (float) ($item['unit_price'] ?? $item['price'] ?? 0);
            $finalPrice = (float) ($item['price'] ?? 0);
            $lineSubtotal = round($unitPrice * $quantity, 2);
            $lineTotal = round($finalPrice * $quantity, 2);
            $lineDiscount = round($lineSubtotal - $lineTotal, 2);

            $subtotal += $lineSubtotal;
            $discount += $lineDiscount;
            $total += $lineTotal;

            $lines[] = [
                'product_id' => (int) ($item['product_id'] ?? 0),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_percent' => (float) ($item['discount_percent'] ?? 0),
                'discount_amount' => $lineDiscount,
                'final_unit_price' => $finalPrice,
                'line_total' => $lineTotal
            ];
        }

        return [
            'lines' => $lines,
            'subtotal_amount' => round($subtotal, 2),
            'discount_total' => round($discount, 2),
            'total_amount' => round($total, 2)
        ];
    }

    private function pricingFromItemModels($items) {
        $total = 0;
        $lines = [];

        foreach ($items as $item) {
            $lineTotal = ((int) $item->quantity) * ((float) $item->price);
            $total += $lineTotal;
            $lines[] = [
                'product_id' => (int) $item->product_id,
                'quantity' => (int) $item->quantity,
                'unit_price' => (float) $item->price,
                'discount_percent' => 0,
                'discount_amount' => 0,
                'final_unit_price' => (float) $item->price,
                'line_total' => round($lineTotal, 2)
            ];
        }

        return [
            'lines' => $lines,
            'subtotal_amount' => round($total, 2),
            'discount_total' => 0,
            'total_amount' => round($total, 2)
        ];
    }

    private function decodePricing($payload) {
        if (!is_string($payload) || trim($payload) === '') {
            return null;
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function normalizePricingPayload($pricing, $items) {
        if (!is_array($pricing)) {
            return $this->pricingFromItemModels($items);
        }

        $fallback = $this->pricingFromItemModels($items);

        $lines = $pricing['lines'] ?? [];
        if (!is_array($lines) || empty($lines)) {
            $lines = $fallback['lines'];
        }

        $subtotal = isset($pricing['subtotal_amount'])
            ? round((float) $pricing['subtotal_amount'], 2)
            : round((float) ($fallback['subtotal_amount'] ?? 0), 2);

        $discount = isset($pricing['discount_total'])
            ? round((float) $pricing['discount_total'], 2)
            : round((float) ($fallback['discount_total'] ?? 0), 2);

        $total = isset($pricing['total_amount'])
            ? round((float) $pricing['total_amount'], 2)
            : round((float) ($fallback['total_amount'] ?? 0), 2);

        return [
            'lines' => array_values($lines),
            'subtotal_amount' => $subtotal,
            'discount_total' => $discount,
            'total_amount' => $total
        ];
    }

    private function positiveInt($data, $key, $default = null) {
        $value = $data[$key] ?? $default;
        $value = (int) $value;

        if ($value <= 0) {
            throw new Exception('Missing or invalid ' . $key);
        }

        return $value;
    }

    private function nullableDate($value) {
        if (empty($value)) {
            return null;
        }

        return function_exists('sanitize_text_field')
            ? sanitize_text_field((string) $value)
            : trim((string) $value);
    }
}
