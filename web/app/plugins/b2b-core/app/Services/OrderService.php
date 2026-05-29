<?php

require_once __DIR__ . '/../Support/Mailer.php';
require_once __DIR__ . '/WpUserService.php';

class OrderService {
    private $repository;
    private $orderItemRepository;
    private $contractRepository;
    private $quotationRepository;
    private $quotationItemRepository;
    private $rfqRepository;
    private $paymentService;
    private $companyRepository;
    private $transactionRepository;
    private $adminLogRepository;

    public function __construct() {
        $this->repository = new OrderRepository();
        $this->orderItemRepository = new OrderItemRepository();
        $this->contractRepository = new ContractRepository();
        $this->quotationRepository = new QuotationRepository();
        $this->quotationItemRepository = new QuotationItemRepository();
        $this->rfqRepository = new RFQRepository();
        $this->paymentService = new PaymentService();
        $this->companyRepository = new CompanyRepository();
        $this->transactionRepository = new TransactionRepository();
        $this->adminLogRepository = new AdminLogRepository();
    }

    public function createFromContract($contractId, $useTransaction = true) {
        $contract = $this->contractRepository
            ->findById($contractId);

        if (!$contract) {
            throw new Exception('Contract not found');
        }

        if ($contract->status !== 'signed') {
            throw new Exception('Contract must be signed before creating order');
        }

        $existing = $this->repository->findByContractId((int) $contractId);

        if ($existing) {
            return (int) $existing->id;
        }

        $orderData = $this->orderDataFromContract($contract);

        if (empty($orderData['items'])) {
            throw new Exception('Order must have items');
        }

        if ((float) $orderData['total_amount'] <= 0) {
            $orderData['total_amount'] = $this->sumItems($orderData['items']);
        }

        if ($useTransaction) {
            $this->transactionRepository->start();
        }

        try {
            $orderId = $this->repository->create([
                'contract_id' => $contractId,
                'buyer_company_id' => $orderData['buyer_company_id'],
                'seller_company_id' => $orderData['seller_company_id'],
                'total_amount' => $orderData['total_amount'],
                'status' => 'pending',
                'created_at' => current_time('mysql')
            ]);

            $this->orderItemRepository->createMany($orderId, $orderData['items']);
            $this->paymentService->create($orderId);

            if ($useTransaction) {
                $this->transactionRepository->commit();
            }

            return $orderId;

        } catch (Exception $e) {
            if ($useTransaction) {
                $this->transactionRepository->rollback();
            }
            throw $e;
        }
    }

    public function detail($orderId) {
        $order = $this->repository->findById((int) $orderId);

        if (!$order) {
            throw new Exception('Order not found');
        }

        return [
            'order' => $order,
            'items' => $this->orderItemRepository->findByOrderId($order->id),
            'payment' => $this->paymentService->byOrder($order->id)
        ];
    }

    public function list($companyId, array $context = []) {
        $roles = $this->normalizeRoles($context['roles'] ?? []);
        $search = trim((string) ($context['search'] ?? ''));

        if ($this->canViewAllOrders($roles)) {
            return $this->repository->listAllForSupport($search);
        }

        if ((int) $companyId <= 0) {
            throw new Exception('Missing company_id');
        }

        return $this->repository->listForCompany((int) $companyId, $search);
    }

    public function sellerHistory($companyId, array $filters = []) {
        if ((int) $companyId <= 0) {
            throw new Exception('Missing company_id');
        }

        return $this->repository->sellerHistory((int) $companyId, $filters);
    }

    public function markDelivering($orderId, $companyId = 0) {
    $order = $this->repository->findById((int) $orderId);

    if (!$order) {
        throw new Exception('Order not found');
    }

    if ((int) $companyId > 0 && (int) $order->seller_company_id !== (int) $companyId) {
        throw new Exception('Chỉ bên bán của giao dịch này mới được xác nhận giao hàng.');
    }

    if ($order->status !== 'paid') {
        throw new Exception('Order must be paid before delivery');
    }

    $this->repository->update($orderId, [
        'status' => 'delivering'
    ]);

    return $this->detail($orderId);
}

    public function complete($orderId, $companyId = 0) {
    $order = $this->repository->findById((int) $orderId);

    if (!$order) {
        throw new Exception('Order not found');
    }

    if ((int) $companyId > 0 && (int) $order->buyer_company_id !== (int) $companyId) {
        throw new Exception('Chỉ bên mua của giao dịch này mới được xác nhận đã nhận hàng.');
    }

    if (!in_array($order->status, ['paid', 'delivering'], true)) {
        throw new Exception('Only paid or delivering order can be completed');
    }

    $this->repository->update($orderId, [
    'status' => 'completed'
    ]);

    $payment = $this->paymentService->byOrder($orderId);

    $notificationStatus = $this->notifyAdminForManualPayout($order, $payment);

    return [
        'order' => $this->detail($orderId),
        'payment' => $payment,
        'admin_payout_notification' => $notificationStatus,
        'message' => 'Buyer đã xác nhận nhận hàng. Đơn đang chờ admin duyệt giải ngân.'
    ];
}

    private function notifyAdminForManualPayout($order, $payment) {
        try {
            $message = $this->adminPayoutMessage($order, $payment);
            $this->saveAdminPayoutLog($message);

            $adminEmails = $this->adminEmails();

            if (empty($adminEmails)) {
                error_log('[B2B PAYOUT NOTICE] Không tìm thấy email admin để gửi thông báo giải ngân.');
                return [
                    'logged' => true,
                    'emailed' => false,
                    'reason' => 'missing_admin_email'
                ];
            }

            $attachments = $this->contractAttachmentsForPayout((int) $order->contract_id);

            Mailer::sendAdminNotification(
                $adminEmails,
                'Đơn hàng cần giải ngân',
                $this->adminPayoutEmailBody($message),
                $attachments
            );

            return [
                'logged' => true,
                'emailed' => true,
                'sent_to' => $adminEmails
            ];
        } catch (Throwable $e) {
            error_log('[B2B PAYOUT NOTICE ERROR] ' . $e->getMessage());

            return [
                'logged' => false,
                'emailed' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function adminPayoutMessage($order, $payment) {
        $contract = $this->contractRepository->findById((int) $order->contract_id);
        $contractData = $this->decode($contract->contract_file ?? '');
        $sellerCompany = $this->companyRepository->findById((int) $order->seller_company_id);
        $sellerContractData = is_array($contractData['seller_company'] ?? null)
            ? $contractData['seller_company']
            : [];

        $contractNo = $this->firstText(
            $contractData['contract_no'] ?? '',
            $contract ? ('HĐ #' . (int) $contract->id) : ''
        );

        $paidAmount = (float) ($payment->amount ?? $order->total_amount ?? 0);
        $sellerPayoutAmount = (float) ($payment->seller_payout_amount ?? $paidAmount);

        $sellerName = $this->firstText(
            $sellerContractData['company_name'] ?? '',
            $sellerCompany->company_name ?? '',
            'Seller #' . (int) $order->seller_company_id
        );

        $bankAccount = $this->firstText(
            $sellerContractData['bank_account'] ?? '',
            $sellerContractData['bank_account_number'] ?? '',
            $sellerCompany->bank_account ?? '',
            $sellerCompany->bank_account_number ?? '',
            'Chưa cập nhật'
        );

        $bankName = $this->firstText(
            $sellerContractData['bank_name'] ?? '',
            $sellerCompany->bank_name ?? '',
            'Chưa cập nhật'
        );

        $bankAccountName = $this->firstText(
            $sellerContractData['bank_account_name'] ?? '',
            $sellerContractData['representative_name'] ?? '',
            $sellerName
        );

        $lines = [
            'Tiêu đề: Đơn hàng cần giải ngân',
            'Thời gian: ' . current_time('mysql'),
            'Order ID: #' . (int) $order->id,
            'Hợp đồng: ' . $contractNo,
            'Contract ID: #' . (int) $order->contract_id,
            'Số tiền buyer đã thanh toán: ' . $this->money($paidAmount),
            'Seller: ' . $sellerName,
            'Tên ngân hàng seller: ' . $bankName,
            'Số tài khoản seller: ' . $bankAccount,
            'Tên chủ tài khoản: ' . $bankAccountName,
            'Ghi chú: Buyer đã bấm "Đã nhận hàng". Admin vui lòng kiểm tra hợp đồng và chuyển khoản thủ công cho seller.'
        ];

        return implode("\n", $lines);
    }

    private function saveAdminPayoutLog($message) {
        return $this->adminLogRepository->create($this->adminLogActorId(), $message);
    }

    private function adminLogActorId() {
        $adminId = $this->firstAdminUserId();

        if ($adminId > 0) {
            return $adminId;
        }

        try {
            $currentUserId = (int) AuthHelper::userId();
            if ($currentUserId > 0) {
                return $currentUserId;
            }
        } catch (Throwable $e) {
            // fallback below
        }

        return 1;
    }

    private function firstAdminUserId() {
        $admins = WpUserService::instance()->listUsers([
            'role__in' => ['administrator', 'admin'],
            'number' => 1,
            'fields' => ['ID']
        ]);

        if (!empty($admins) && !empty($admins[0]->ID)) {
            return (int) $admins[0]->ID;
        }

        return 0;
    }

    private function canViewAllOrders(array $roles)
    {
        foreach ($roles as $role) {
            $role = strtoupper(trim((string) $role));

            if (
                $role === 'ADMIN'
                || $role === 'ADMINISTRATOR'
                || $role === 'ROLE_ADMIN'
                || $role === 'SUPPORT'
                || $role === 'ROLE_SUPPORT'
            ) {
                return true;
            }
        }

        return false;
    }

    private function normalizeRoles($roles)
    {
        if (is_string($roles)) {
            $roles = explode(',', $roles);
        }

        if (!is_array($roles)) {
            $roles = [$roles];
        }

        $flat = [];
        $stack = $roles;

        while (!empty($stack)) {
            $item = array_shift($stack);

            if (is_array($item)) {
                foreach ($item as $nested) {
                    $stack[] = $nested;
                }
                continue;
            }

            if (is_object($item)) {
                $stack[] = $item->role_name ?? ($item->name ?? ($item->slug ?? ($item->role ?? '')));
                continue;
            }

            $value = strtoupper(trim((string) $item));

            if ($value === '') {
                continue;
            }

            $flat[$value] = true;
        }

        return array_keys($flat);
    }

    private function adminEmails() {
        $emails = [];

        $optionEmail = function_exists('get_option') ? (string) get_option('admin_email') : '';
        if ($optionEmail !== '') {
            $emails[] = $optionEmail;
        }

        $envEmail = (string) (getenv('B2B_ADMIN_EMAIL') ?: '');
        if ($envEmail !== '') {
            $emails[] = $envEmail;
        }

        $admins = WpUserService::instance()->listUsers([
            'role__in' => ['administrator', 'admin'],
            'number' => 20,
            'fields' => ['user_email']
        ]);

        foreach ($admins as $admin) {
            if (!empty($admin->user_email)) {
                $emails[] = $admin->user_email;
            }
        }

        $emails = array_filter(array_unique(array_map('trim', $emails)), function ($email) {
            return filter_var($email, FILTER_VALIDATE_EMAIL);
        });

        return array_values($emails);
    }

    private function adminPayoutEmailBody($message) {
        return '<div style="font-family: Arial, sans-serif; line-height: 1.6; color: #111827;">'
            . '<h2 style="margin: 0 0 12px;">Đơn hàng cần giải ngân</h2>'
            . '<p>Buyer đã xác nhận đã nhận hàng. Admin vui lòng kiểm tra lại hợp đồng và chuyển khoản thủ công cho seller.</p>'
            . '<pre style="white-space: pre-wrap; background: #f3f4f6; padding: 14px; border-radius: 8px;">'
            . esc_html($message)
            . '</pre>'
            . '</div>';
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

    private function money($amount) {
        return number_format((float) $amount, 0, ',', '.') . ' VND';
    }

    public function cancel($orderId, $reason = '') {
        $order = $this->repository->findById((int) $orderId);

        if (!$order) {
            throw new Exception('Order not found');
        }

        if ($order->status === 'completed') {
            throw new Exception('Completed order cannot be cancelled');
        }

        if ($order->status === 'cancelled') {
            return $this->detail($orderId);
        }

        $this->paymentService->failByOrder($orderId);

        $this->repository->update($orderId, [
            'status' => 'cancelled'
        ]);

        return $this->detail($orderId);
    }

    private function orderDataFromContract($contract) {
        $contractData = $this->decode($contract->contract_file ?? '');

        if (!empty($contractData['buyer_company_id']) && !empty($contractData['seller_company_id'])) {
            return [
                'buyer_company_id' => (int) $contractData['buyer_company_id'],
                'seller_company_id' => (int) $contractData['seller_company_id'],
                'total_amount' => (float) ($contractData['total_amount'] ?? 0),
                'items' => $this->itemsFromContractData($contractData)
            ];
        }

        $quotation = $this->quotationRepository->findById((int) $contract->quotation_id);

        if (!$quotation) {
            throw new Exception('Quotation not found');
        }

        $rfq = $this->rfqRepository->findById((int) $quotation->rfq_id);

        if (!$rfq) {
            throw new Exception('RFQ not found');
        }

        $items = $this->quotationItemRepository->findByQuotationId((int) $quotation->id);

        return [
            'buyer_company_id' => (int) $rfq->buyer_company_id,
            'seller_company_id' => (int) $quotation->seller_company_id,
            'total_amount' => $this->quotationItemRepository->totalByQuotationId((int) $quotation->id),
            'items' => $this->itemsFromObjects($items)
        ];
    }

    private function itemsFromContractData($contractData) {
    if (empty($contractData['items']) || !is_array($contractData['items'])) {
        return [];
    }

    $items = [];

    foreach ($contractData['items'] as $item) {
        $isArray = is_array($item);

        $productId = (int) ($isArray
            ? ($item['product_id'] ?? 0)
            : ($item->product_id ?? 0));

        $quantity = (int) ($isArray
            ? ($item['quantity'] ?? 1)
            : ($item->quantity ?? 1));

        $price = (float) ($isArray
            ? ($item['final_unit_price'] ?? $item['unit_price'] ?? $item['price'] ?? 0)
            : ($item->final_unit_price ?? $item->unit_price ?? $item->price ?? 0));

        if ($price <= 0) {
            $lineTotal = (float) ($isArray
                ? ($item['line_total'] ?? 0)
                : ($item->line_total ?? 0));

            if ($lineTotal > 0 && $quantity > 0) {
                $price = $lineTotal / $quantity;
            }
        }

        $items[] = [
            'product_id' => $productId,
            'quantity'   => $quantity,
            'price'      => $price,
        ];
    }

    return $items;
}

    private function itemsFromObjects($rows) {
        $items = [];

        foreach ($rows as $row) {
            $items[] = [
                'product_id' => (int) $row->product_id,
                'quantity' => (int) $row->quantity,
                'price' => (float) $row->price
            ];
        }

        return $items;
    }

    private function sumItems($items) {
        $total = 0;

        foreach ($items as $item) {
            $total += ((int) $item['quantity']) * ((float) $item['price']);
        }

        return $total;
    }

    private function contractAttachmentsForPayout($contractId) {
    if ((int) $contractId <= 0) {
        return [];
    }

    if (!function_exists('wp_upload_dir')) {
        return [];
    }

    $upload = wp_upload_dir();

    if (!empty($upload['error']) || empty($upload['basedir'])) {
        return [];
    }

    $dir = trailingslashit($upload['basedir']) . 'b2b-contracts';

    if (!is_dir($dir)) {
        return [];
    }

    $pattern = trailingslashit($dir) . 'hop-dong-' . (int) $contractId . '-*.html';

    $files = glob($pattern);

    if (empty($files)) {
        return [];
    }

    usort($files, function ($a, $b) {
        return filemtime($b) <=> filemtime($a);
    });

    $latestFile = $files[0] ?? '';

    if (!is_string($latestFile) || !file_exists($latestFile)) {
        return [];
    }

    return [$latestFile];
}

    private function decode($value) {
        if (empty($value)) {
            return [];
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
