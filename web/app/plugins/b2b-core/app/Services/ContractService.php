<?php

require_once __DIR__ . '/../Support/Mailer.php';

class ContractService {
    private $repository;
    private $quotationRepository;
    private $quotationItemRepository;
    private $rfqRepository;
    private $orderRepository;
    private $orderService;
    private $companyRepository;
    private $transactionRepository;

    public function __construct() {
        $this->repository = new ContractRepository();
        $this->quotationRepository = new QuotationRepository();
        $this->quotationItemRepository = new QuotationItemRepository();
        $this->rfqRepository = new RFQRepository();
        $this->orderRepository = new OrderRepository();
        $this->orderService = new OrderService();
        $this->companyRepository = new CompanyRepository();
        $this->transactionRepository = new TransactionRepository();
    }

    public function createFromQuotation($quotationId) {
        $quotation = $this->quotationRepository->findById((int) $quotationId);

        if (!$quotation) {
            throw new Exception('Quotation not found');
        }

        if ($quotation->status !== 'accepted') {
            throw new Exception('Only accepted quotation can create contract');
        }

        $existing = $this->repository->findByQuotationId($quotationId);

        if ($existing) {
            return (int) $existing->id;
        }

        $contractData = $this->buildContractData($quotation);

        return $this->repository->create([
            'quotation_id' => (int) $quotationId,
            'contract_file' => $this->encode($contractData),
            'status' => 'draft',
            'created_at' => current_time('mysql')
        ]);
    }

    public function sign($contractId, $data = []) {
    $contractId = (int) $contractId;
    $orderId = null;
    $bothSigned = false;
    $status = 'draft';

    $this->transactionRepository->start();

        try {
            $contract = $this->repository->findById($contractId);

            if (!$contract) {
                throw new Exception('Contract not found');
            }

            if ($contract->status === 'cancelled') {
                throw new Exception('Contract is cancelled');
            }

            $contractData = $this->contractData($contract);
            $party = $this->detectParty($contractData, $data);

            if (!$party) {
                throw new Exception('Signer does not belong to contract parties');
            }

            if (!empty($contractData['signatures'][$party]['signed'])) {
                $existingOrder = $this->orderRepository->findByContractId($contractId);

                $this->transactionRepository->commit();

                return [
                    'contract_id' => $contractId,
                    'status' => $contract->status,
                    'order_id' => $existingOrder ? (int) $existingOrder->id : null,
                    'already_signed' => true
                ];
            }

            $userId = (int) ($data['signed_by'] ?? ($data['user_id'] ?? 0));

            if ($userId <= 0) {
                throw new Exception('Signer user is required');
            }

            $signatureData = $this->signatureData($data['signature_data'] ?? '');

            if ($signatureData === '') {
                throw new Exception('Signature data is required');
            }

            $contractData['signatures'][$party] = [
                'signed' => true,
                'signed_by' => $userId,
                'signed_at' => current_time('mysql'),
                'signed_name' => $this->text($data['signed_name'] ?? ''),
                'signature_data' => $signatureData
            ];

            $bothSigned = $this->isBothSigned($contractData);

            $updateData = [
                'contract_file' => $this->encode($contractData)
            ];

            if ($bothSigned) {
                $status = 'signed';
                $updateData['status'] = 'signed';
                $updateData['signed_at'] = current_time('mysql');
                $contractData['signed_at'] = $updateData['signed_at'];
                $updateData['contract_file'] = $this->encode($contractData);
            }

            $this->repository->update($contractId, $updateData);

            if ($bothSigned) {
                $existingOrder = $this->orderRepository->findByContractId($contractId);

                if ($existingOrder) {
                    $orderId = (int) $existingOrder->id;
                } else {
                    $orderId = (int) $this->orderService->createFromContract($contractId, false);
                }
            }

            $this->transactionRepository->commit();

            if ($bothSigned && function_exists('wp_schedule_single_event')) {
                $args = [$contractId];

                if (!wp_next_scheduled('b2b_send_signed_contract_email', $args)) {
                    wp_schedule_single_event(time() + 10, 'b2b_send_signed_contract_email', $args);
                }
            }

            return [
                'contract_id' => $contractId,
                'status' => $bothSigned ? 'signed' : $status,
                'order_id' => $orderId,
                'already_signed' => false
            ];

        } catch (Exception $e) {
            $this->transactionRepository->rollback();
            throw $e;
        }
    }

    public function cancel($contractId, $data = []) {
        $contract = $this->repository->findById((int) $contractId);

        if (!$contract) {
            throw new Exception('Contract not found');
        }

        if ($contract->status === 'cancelled') {
            return $this->detail($contractId);
        }

        $order = $this->orderRepository->findByContractId((int) $contractId);

        if ($order) {
            $this->orderService->cancel($order->id, $data['reason'] ?? 'contract_cancelled');
        }

        $this->repository->update($contractId, [
            'status' => 'cancelled',
            'cancelled_at' => current_time('mysql')
        ]);

        return $this->detail($contractId);
    }

    public function detail($contractId) {
        $contract = $this->repository->findById((int) $contractId);

        if (!$contract) {
            throw new Exception('Contract not found');
        }

        $order = $this->orderRepository->findByContractId((int) $contract->id);

        return [
            'contract' => $contract,
            'contract_data' => $this->contractData($contract),
            'order' => $order
        ];
    }

    public function detailByQuotation($quotationId) {
        $contract = $this->repository->findByQuotationId((int) $quotationId);

        if (!$contract) {
            throw new Exception('Contract not found');
        }

        return $this->detail((int) $contract->id);
    }

    public function contractData($contract) {
        $decoded = $this->decode($contract->contract_file ?? '');

        if (!empty($decoded)) {
            return $this->withDefaultSignatures(
                $this->withDefaultTerms(
                    $this->withCurrentContractFields($decoded, $contract)
                )
            );
        }

        $quotation = $this->quotationRepository->findById((int) $contract->quotation_id);

        if (!$quotation) {
            return [];
        }

        return $this->withDefaultTerms($this->buildContractData($quotation));
    }

    private function withCurrentContractFields($data, $contract) {
        $quotation = $this->quotationRepository->findById((int) $contract->quotation_id);

        if (!$quotation) {
            return $data;
        }

        $fresh = $this->buildContractData($quotation);

        return $this->fillMissingData($data, $fresh);
    }

    private function buildContractData($quotation) {
        $rfq = $this->rfqRepository->findById((int) $quotation->rfq_id);

        if (!$rfq) {
            throw new Exception('RFQ not found');
        }

        $items = $this->quotationItemRepository->findByQuotationId((int) $quotation->id);
        $pricing = $this->pricingData($quotation->id, $items);
        $buyerCompany = $this->companyRepository->findById((int) $rfq->buyer_company_id);
        $sellerCompany = $this->companyRepository->findById((int) $quotation->seller_company_id);

        return $this->withDefaultSignatures([
            'contract_no' => 'B2B-' . date('Ymd') . '-' . (int) $quotation->id,
            'quotation_id' => (int) $quotation->id,
            'rfq_id' => (int) $quotation->rfq_id,
            'buyer_company_id' => (int) $rfq->buyer_company_id,
            'seller_company_id' => (int) $quotation->seller_company_id,
            'buyer_company' => $this->companyData($buyerCompany),
            'seller_company' => $this->companyData($sellerCompany),
            'buyer_emails' => $this->companyEmails((int) $rfq->buyer_company_id),
            'seller_emails' => $this->companyEmails((int) $quotation->seller_company_id),
            'subtotal_amount' => $pricing['subtotal_amount'],
            'discount_total' => $pricing['discount_total'],
            'total_amount' => $pricing['total_amount'],
            'items' => $this->contractItems($items, $pricing),
            'legal_basis' => $this->legalBasis(),
            'terms' => $this->defaultTerms(),
            'contract_place' => 'Sàn giao dịch B2B Ô Tô',
            'created_at' => current_time('mysql')
        ]);
    }

    private function pricingData($quotationId, $items) {
        $quotation = $this->quotationRepository->findById((int) $quotationId);

        if ($quotation) {
            $payload = trim((string) ($quotation->pricing_json ?? ''));

            if ($payload !== '') {
                $decoded = json_decode($payload, true);

                if (is_array($decoded)) {
                    return $this->normalizePricing($decoded, $items);
                }
            }

            $hasTotals = isset($quotation->subtotal_amount)
                || isset($quotation->discount_total)
                || isset($quotation->total_amount);

            if ($hasTotals) {
                return $this->normalizePricing([
                    'lines' => [],
                    'subtotal_amount' => round((float) ($quotation->subtotal_amount ?? 0), 2),
                    'discount_total' => round((float) ($quotation->discount_total ?? 0), 2),
                    'total_amount' => round((float) ($quotation->total_amount ?? 0), 2),
                ], $items);
            }
        }

        return $this->fallbackPricing($items);
    }

    private function normalizePricing($pricing, $items) {
        $fallback = $this->fallbackPricing($items);

        if (!is_array($pricing)) {
            return $fallback;
        }

        $lines = $pricing['lines'] ?? [];

        if (!is_array($lines) || empty($lines)) {
            $lines = $fallback['lines'];
        }

        return [
            'lines' => array_values($lines),
            'subtotal_amount' => isset($pricing['subtotal_amount'])
                ? round((float) $pricing['subtotal_amount'], 2)
                : round((float) ($fallback['subtotal_amount'] ?? 0), 2),
            'discount_total' => isset($pricing['discount_total'])
                ? round((float) $pricing['discount_total'], 2)
                : round((float) ($fallback['discount_total'] ?? 0), 2),
            'total_amount' => isset($pricing['total_amount'])
                ? round((float) $pricing['total_amount'], 2)
                : round((float) ($fallback['total_amount'] ?? 0), 2)
        ];
    }

    private function fallbackPricing($items) {
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

    private function contractItems($items, $pricing) {
        $lines = [];

        foreach ($pricing['lines'] ?? [] as $line) {
            $lines[(int) $line['product_id']] = $line;
        }

        $result = [];

        foreach ($items as $item) {
            $line = $lines[(int) $item->product_id] ?? [];
            $result[] = [
                'product_id' => (int) $item->product_id,
                'product_name' => $item->product_name ?? ('Sản phẩm #' . (int) $item->product_id),
                'brand' => $this->firstText($item->brand ?? '', $item->product_brand ?? ''),
                'color' => $this->firstText($item->color ?? '', $item->product_color ?? ''),
                'year' => $this->firstText($item->year ?? '', $item->years ?? '', $item->manufacture_year ?? '', $item->product_year ?? ''),
                'quantity' => (int) $item->quantity,
                'unit_price' => (float) ($line['unit_price'] ?? $item->price),
                'discount_percent' => (float) ($line['discount_percent'] ?? 0),
                'discount_amount' => (float) ($line['discount_amount'] ?? 0),
                'final_unit_price' => (float) ($line['final_unit_price'] ?? $item->price),
                'line_total' => (float) ($line['line_total'] ?? (((int) $item->quantity) * ((float) $item->price)))
            ];
        }

        return $result;
    }

    private function companyData($company) {
        if (!$company) {
            return [
                'id' => 0,
                'company_name' => '',
                'tax_code' => '',
                'address' => '',
                'phone' => '',
                'email' => '',
                'representative_name' => '',
                'citizen_id_number' => '',
                'website' => '',
                'bank_account' => '',
                'bank_name' => ''
            ];
        }

        $profile = $this->companyProfileData((int) $company->id);

        return [
            'id' => (int) $company->id,
            'company_name' => $this->firstText($company->company_name ?? '', $profile['company_name'] ?? ''),
            'tax_code' => $this->firstText($company->tax_code ?? '', $profile['tax_code'] ?? ''),
            'address' => $this->firstText($company->address ?? '', $profile['address'] ?? ''),
            'phone' => $this->firstText($company->phone ?? '', $profile['phone'] ?? ''),
            'email' => $this->firstText($company->email ?? '', $profile['email'] ?? ''),
            'representative_name' => $this->firstText($company->representative_name ?? '', $profile['representative_name'] ?? ''),
            'citizen_id_number' => $this->firstText($company->citizen_id_number ?? '', $profile['citizen_id_number'] ?? ''),
            'website' => $this->firstText($company->website ?? '', $profile['website'] ?? ''),
            'bank_account' => $this->firstText($company->bank_account ?? '', $profile['bank_account'] ?? ''),
            'bank_name' => $this->firstText($company->bank_name ?? '', $profile['bank_name'] ?? '')
        ];
    }

    private function companyProfileData($companyId) {
        $profile = $this->repository->companyProfileData((int) $companyId);
        $member = is_array($profile['member'] ?? null) ? $profile['member'] : [];
        $sellerRequest = is_array($profile['seller_request'] ?? null) ? $profile['seller_request'] : [];

        return [
            'company_name' => $sellerRequest['company_name'] ?? '',
            'tax_code' => $sellerRequest['tax_code'] ?? '',
            'address' => $sellerRequest['address'] ?? '',
            'phone' => $member['phone'] ?? '',
            'email' => $this->firstText($sellerRequest['company_email'] ?? '', $member['user_email'] ?? ''),
            'representative_name' => $this->firstText(
                $sellerRequest['representative_name'] ?? '',
                $member['fullname'] ?? '',
                $member['display_name'] ?? ''
            ),
            'citizen_id_number' => $sellerRequest['citizen_id_number'] ?? '',
            'website' => '',
            'bank_account' => '',
            'bank_name' => ''
        ];
    }

    private function fillMissingData($data, $fallback) {
        foreach ($fallback as $key => $value) {
            if (!array_key_exists($key, $data) || $this->isBlank($data[$key])) {
                $data[$key] = $value;
                continue;
            }

            if (
                is_array($data[$key])
                && is_array($value)
                && $this->isAssocArray($data[$key])
                && $this->isAssocArray($value)
            ) {
                $data[$key] = $this->fillMissingData($data[$key], $value);
            }
        }

        return $data;
    }

    private function isBlank($value) {
        return $value === null
            || $value === ''
            || (is_array($value) && empty($value));
    }

    private function isAssocArray($value) {
        if (!is_array($value) || empty($value)) {
            return false;
        }

        return array_keys($value) !== range(0, count($value) - 1);
    }

    private function firstText(...$values) {
        foreach ($values as $value) {
            $value = trim((string) $value);

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function companyEmails($companyId) {
        return $this->repository->companyEmails((int) $companyId);
    }

    private function legalBasis() {
        return [
            'Căn cứ Luật giao dịch điện tử 2005;',
            'Căn cứ Nghị định số 52/2013/NĐ-CP về thương mại điện tử, được sửa đổi bổ sung bởi Nghị định 85/2021/NĐ-CP;',
            'Căn cứ Thông tư 47/2014/TT-BCT, được sửa đổi bổ sung bởi Thông tư 01/2022/TT-BCT;',
            'Căn cứ Luật thương mại năm 2005;',
            'Căn cứ vào khả năng và nhu cầu giao dịch của hai bên.'
        ];
    }

    private function defaultTerms() {
        return [
            'escrow' => 'Người mua thanh toán khoản tiền ký quỹ cho nền tảng trước khi giao hàng. Người bán chỉ nhận được tiền sau khi người mua xác nhận hoặc hệ thống tự động giải phóng tiền.',
            'quotation_lock' => 'Sản phẩm, số lượng và giá cả tuân theo báo giá đã được chấp nhận trong hợp đồng này.',
            'delivery' => 'Người bán phải giao hàng theo báo giá đã thỏa thuận và cung cấp thông tin cập nhật giao hàng trung thực.',
            'inspection' => 'Người mua có thể kiểm tra hàng hóa và phải xác nhận đã nhận hàng khi hàng hóa đúng như yêu cầu.',
            'auto_release' => 'Nếu người mua không xác nhận trong vòng 7 ngày sau khi nhận được thông tin giao hàng, hệ thống có thể giải phóng tiền ký quỹ cho người bán.',
            'dispute' => 'Cả hai bên đều có thể mở yêu cầu hỗ trợ. Bộ phận hỗ trợ có thể xem xét bằng chứng trước khi hoàn tiền hoặc giải phóng tiền.',
            'cancellation' => 'Trước khi tiền ký quỹ được giải phóng, việc hủy đơn hàng có thể dừng đơn hàng và đánh dấu thanh toán thất bại/có thể hoàn tiền nếu có vấn đề chính đáng.'
        ];
    }

    private function withDefaultTerms($data) {
        $terms = is_array($data['terms'] ?? null) ? $data['terms'] : [];
        $data['terms'] = array_merge($terms, $this->defaultTerms());

        return $data;
    }

    private function detectParty($contractData, $data) {
        $companyId = (int) ($data['company_id'] ?? 0);
        $requestedParty = $data['party'] ?? '';

        if (in_array($requestedParty, ['buyer', 'seller'], true) && $companyId > 0) {
            $partyCompanyId = (int) ($contractData[$requestedParty . '_company_id'] ?? 0);

            return $companyId === $partyCompanyId ? $requestedParty : null;
        }

        if ($companyId > 0 && $companyId === (int) ($contractData['buyer_company_id'] ?? 0)) {
            return 'buyer';
        }

        if ($companyId > 0 && $companyId === (int) ($contractData['seller_company_id'] ?? 0)) {
            return 'seller';
        }

        return null;
    }

    private function isBothSigned($contractData) {
        return !empty($contractData['signatures']['buyer']['signed'])
            && !empty($contractData['signatures']['seller']['signed']);
    }

    private function withDefaultSignatures($data) {
        if (empty($data['signatures']) || !is_array($data['signatures'])) {
            $data['signatures'] = [];
        }

        foreach (['buyer', 'seller'] as $party) {
            if (empty($data['signatures'][$party])) {
                $data['signatures'][$party] = [
                    'signed' => false,
                    'signed_by' => null,
                    'signed_at' => null
                ];
            }
        }

        return $data;
    }

    private function signatureData($value) {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        if (strpos($value, 'data:image/') !== 0) {
            throw new Exception('Invalid signature data');
        }

        return $value;
    }

    private function text($value) {
        return function_exists('sanitize_text_field')
            ? sanitize_text_field((string) $value)
            : trim((string) $value);
    }

    public function sendSignedContractEmail($contractId) {
        $contract = $this->repository->findById((int) $contractId);

        if (!$contract) {
            error_log('CONTRACT MAIL FAILED: contract not found #' . (int) $contractId);
            return false;
        }

        if ($contract->status !== 'signed') {
            error_log('CONTRACT MAIL SKIPPED: contract not signed #' . (int) $contractId);
            return false;
        }

        $contractData = $this->contractData($contract);
        $this->emailContract((int) $contractId, $contractData);

        return true;
    }

    private function emailContract($contractId, $contractData) {
        $emails = $this->contractRecipientEmails($contractData);

        if (empty($emails)) {
            error_log('CONTRACT MAIL SKIPPED: no recipient for contract #' . (int) $contractId);
            return;
        }

        $subject = 'Hợp đồng giao dịch #' . (int) $contractId . ' đã ký';
        $subject = 'Hop dong giao dich #' . (int) $contractId . ' da ky';
        $html = $this->contractHtml($contractData);
        $body = $this->contractEmailBody($contractData) . $html;
        $attachments = $this->contractAttachments($contractId, $html);
        try {
            if (class_exists('Mailer')) {
                Mailer::sendContract($emails, $subject, $body, $attachments);
                return;
            }

            error_log('CONTRACT MAIL FAILED: Mailer class not found');
        } catch (Throwable $e) {
            error_log('CONTRACT SMTP MAIL FAILED: ' . $e->getMessage());
        }

        return;
    }

    private function contractRecipientEmails($contractData) {
        $emails = $this->signatureEmails($contractData);

        if (empty($emails)) {
            $emails = [
                $contractData['buyer_company']['email'] ?? '',
                $contractData['seller_company']['email'] ?? ''
            ];
        }

        return $this->validEmails($emails);
    }

    private function signatureEmails($contractData) {
        $userIds = [];

        foreach (['buyer', 'seller'] as $party) {
            $userId = (int) ($contractData['signatures'][$party]['signed_by'] ?? 0);

            if ($userId > 0) {
                $userIds[] = $userId;
            }
        }

        return $this->repository->userEmailsByIds($userIds);
    }

    private function validEmails($emails) {
        $emails = is_array($emails) ? $emails : [$emails];
        $valid = [];

        foreach ($emails as $email) {
            $email = trim((string) $email);

            if ($email === '') {
                continue;
            }

            $isValid = function_exists('is_email')
                ? is_email($email)
                : filter_var($email, FILTER_VALIDATE_EMAIL);

            if ($isValid) {
                $valid[] = strtolower($email);
            }
        }

        return array_values(array_unique($valid));
    }

    private function contractEmailBody($data) {
        return '
        <div style="font-family:Arial,sans-serif;max-width:800px;margin:0 auto 18px;color:#111">
          <p>Hop dong giao dich da duoc hai ben ky dien tu.</p>
          <p>File hop dong da duoc dinh kem trong email nay. Noi dung ben duoi la ban xem nhanh.</p>
          <p><strong>So hop dong:</strong> ' . esc_html($data['contract_no'] ?? '') . '</p>
        </div>';
    }

    private function contractAttachments($contractId, $html) {
        if (!function_exists('wp_upload_dir')) {
            return [];
        }

        $upload = wp_upload_dir();

        if (!empty($upload['error']) || empty($upload['basedir'])) {
            error_log('CONTRACT ATTACHMENT UPLOAD DIR ERROR: ' . ($upload['error'] ?? 'unknown'));
            return [];
        }

        $dir = trailingslashit($upload['basedir']) . 'b2b-contracts';

        if (!function_exists('wp_mkdir_p') || !wp_mkdir_p($dir)) {
            error_log('CONTRACT ATTACHMENT MKDIR FAILED: ' . $dir);
            return [];
        }

        $filename = function_exists('sanitize_file_name')
            ? sanitize_file_name('hop-dong-' . (int) $contractId . '-' . date('Ymd-His') . '.html')
            : ('hop-dong-' . (int) $contractId . '-' . date('Ymd-His') . '.html');
        $path = trailingslashit($dir) . $filename;

        if (file_put_contents($path, $this->contractAttachmentHtml($html)) === false) {
            error_log('CONTRACT ATTACHMENT WRITE FAILED: ' . $path);
            return [];
        }

        return [$path];
    }

    private function contractAttachmentHtml($html) {
        return '<!doctype html>
        <html lang="vi">
        <head>
          <meta charset="UTF-8">
          <title>Hop dong giao dich</title>
          <style>
            @page { size: A4; margin: 16mm; }
            body { margin: 0; background: #f3f4f6; }
            @media print { body { background: #fff; } }
          </style>
        </head>
        <body>' . $html . '</body>
        </html>';
    }

    private function contractHtml($data) {
        $buyer = $data['buyer_company'] ?? [];
        $seller = $data['seller_company'] ?? [];
        $itemsHtml = '';

        foreach (($data['items'] ?? []) as $index => $item) {
            $itemsHtml .= '<tr>'
                . '<td>' . ($index + 1) . '</td>'
                . '<td>' . esc_html($item['product_name'] ?? '') . '</td>'
                . '<td>' . esc_html($item['brand'] ?? 'Chưa cập nhật') . '</td>'
                . '<td>' . esc_html($item['color'] ?? 'Chưa cập nhật') . '</td>'
                . '<td>' . esc_html($item['year'] ?? 'Chưa cập nhật') . '</td>'
                . '<td>' . esc_html($item['quantity'] ?? '') . '</td>'
                . '<td>' . esc_html(number_format((float) ($item['unit_price'] ?? 0), 0, ',', '.')) . ' VND</td>'
                . '<td>' . esc_html($item['discount_percent'] ?? 0) . '%</td>'
                . '<td>' . esc_html(number_format((float) ($item['line_total'] ?? 0), 0, ',', '.')) . ' VND</td>'
                . '</tr>';
        }

        $basisHtml = '';
        foreach (($data['legal_basis'] ?? []) as $basis) {
            $basisHtml .= '<li>' . esc_html($basis) . '</li>';
        }

        $termsHtml = '';
        $termNumber = 1;
        foreach (($data['terms'] ?? $this->defaultTerms()) as $term) {
            $termsHtml .= '<p><strong>' . esc_html((string) $termNumber) . '.</strong> ' . esc_html($term) . '</p>';
            $termNumber++;
        }

        $buyerSignature = $data['signatures']['buyer']['signature_data'] ?? '';
        $sellerSignature = $data['signatures']['seller']['signature_data'] ?? '';

        return '
        <div style="font-family:Arial,sans-serif;max-width:800px;margin:0 auto;color:#111">
          <div style="text-align:center">
            <h3 style="margin:0">CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</h3>
            <p style="margin:4px 0 18px"><strong>Độc lập - Tự do - Hạnh phúc</strong></p>
            <h2>HỢP ĐỒNG GIAO DỊCH TRÊN SÀN THƯƠNG MẠI ĐIỆN TỬ</h2>
            <p>Số: ' . esc_html($data['contract_no'] ?? '') . '</p>
          </div>
          <p>Hôm nay, ngày ' . esc_html(date_i18n('d/m/Y')) . ', tại ' . esc_html($data['contract_place'] ?? '') . ', chúng tôi gồm:</p>
          ' . ($basisHtml ? '<h3>Căn cứ ký kết</h3><ul>' . $basisHtml . '</ul>' : '') . '
          <h3>Bên A - Bên bán/cung cấp</h3>
          <p><strong>Tên doanh nghiệp:</strong> ' . esc_html($seller['company_name'] ?? '') . '</p>
          <p><strong>Mã số doanh nghiệp:</strong> ' . esc_html($seller['tax_code'] ?? '') . '</p>
          <p><strong>Địa chỉ:</strong> ' . esc_html($seller['address'] ?? '') . '</p>
          <p><strong>Điện thoại:</strong> ' . esc_html($seller['phone'] ?? '') . '</p>
          <p><strong>Email:</strong> ' . esc_html($seller['email'] ?? '') . '</p>
          <p><strong>Người đại diện:</strong> ' . esc_html($seller['representative_name'] ?? '') . '</p>
          <p><strong>CCCD/CMND người đại diện:</strong> ' . esc_html($seller['citizen_id_number'] ?? '') . '</p>
          <h3>Bên B - Bên mua</h3>
          <p><strong>Tên tổ chức:</strong> ' . esc_html($buyer['company_name'] ?? '') . '</p>
          <p><strong>Mã số doanh nghiệp:</strong> ' . esc_html($buyer['tax_code'] ?? '') . '</p>
          <p><strong>Địa chỉ:</strong> ' . esc_html($buyer['address'] ?? '') . '</p>
          <p><strong>Điện thoại:</strong> ' . esc_html($buyer['phone'] ?? '') . '</p>
          <p><strong>Email:</strong> ' . esc_html($buyer['email'] ?? '') . '</p>
          <p><strong>Người đại diện:</strong> ' . esc_html($buyer['representative_name'] ?? '') . '</p>
          <p><strong>CCCD/CMND người đại diện:</strong> ' . esc_html($buyer['citizen_id_number'] ?? '') . '</p>
          <h3>Nội dung giao dịch</h3>
          <table style="width:100%;border-collapse:collapse" border="1" cellpadding="6">
            <thead><tr><th>STT</th><th>Sản phẩm</th><th>Hãng xe</th><th>Màu sắc</th><th>Năm</th><th>SL</th><th>Đơn giá</th><th>KM</th><th>Thành tiền</th></tr></thead>
            <tbody>' . $itemsHtml . '</tbody>
          </table>
          <p><strong>Tạm tính:</strong> ' . esc_html(number_format((float) ($data['subtotal_amount'] ?? 0), 0, ',', '.')) . ' VND</p>
          <p><strong>Khuyến mãi:</strong> ' . esc_html(number_format((float) ($data['discount_total'] ?? 0), 0, ',', '.')) . ' VND</p>
          <p><strong>Tổng thanh toán:</strong> ' . esc_html(number_format((float) ($data['total_amount'] ?? 0), 0, ',', '.')) . ' VND</p>
          <h3>Điều khoản thực hiện</h3>
          ' . $termsHtml . '
          <h3>Chữ ký điện tử</h3>
          <table style="width:100%;text-align:center"><tr>
            <td><strong>ĐẠI DIỆN BÊN B</strong><br>' . ($buyerSignature ? '<img src="' . esc_attr($buyerSignature) . '" style="max-height:90px">' : '') . '<br>' . esc_html($data['signatures']['buyer']['signed_name'] ?? '') . '</td>
            <td><strong>ĐẠI DIỆN BÊN A</strong><br>' . ($sellerSignature ? '<img src="' . esc_attr($sellerSignature) . '" style="max-height:90px">' : '') . '<br>' . esc_html($data['signatures']['seller']['signed_name'] ?? '') . '</td>
          </tr></table>
        </div>';
    }

    private function encode($data) {
        if (function_exists('wp_json_encode')) {
            return wp_json_encode($data);
        }

        return json_encode($data);
    }

    private function decode($value) {
        if (empty($value)) {
            return [];
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
