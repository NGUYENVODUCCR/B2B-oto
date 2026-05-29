<?php

require_once __DIR__ . '/../Support/Mailer.php';

class InvoiceService
{
    private $invoiceRepository;
    private $orderRepository;
    private $orderItemRepository;
    private $companyRepository;
    private $contractRepository;

    public function __construct()
    {
        $this->invoiceRepository = new InvoiceRepository();
        $this->orderRepository = new OrderRepository();
        $this->orderItemRepository = new OrderItemRepository();
        $this->companyRepository = new CompanyRepository();
        $this->contractRepository = new ContractRepository();
    }

    public function issueForOrder($orderId, $payment = null)
    {
        $orderId = (int) $orderId;

        $existing = $this->invoiceRepository->findByOrderId($orderId);

        if ($existing) {
            return $existing;
        }

        $order = $this->orderRepository->findById($orderId);

        if (!$order) {
            throw new Exception('Order not found');
        }

        $items = $this->orderItemRepository->findByOrderId($orderId);
        $items = $this->normalizeInvoiceItems($order, $items);

        $buyer = $this->companyRepository->findById((int) $order->buyer_company_id);
        $seller = $this->companyRepository->findById((int) $order->seller_company_id);

        $invoiceNumber = 'INV-' . date('Ymd') . '-' . $orderId . '-' . time();

        $html = $this->invoiceHtml($invoiceNumber, $order, $items, $buyer, $seller, $payment);
        $file = $this->saveInvoiceFile($invoiceNumber, $html);

        $invoiceId = $this->invoiceRepository->create([
            'order_id' => $orderId,
            'invoice_number' => $invoiceNumber,
            'invoice_file' => $file['url'],
            'issued_at' => current_time('mysql')
        ]);

        $invoice = $this->invoiceRepository->findById($invoiceId);

        $this->sendInvoiceEmail($order, $invoice, $file['path']);

        return $invoice;
    }

    private function invoiceHtml($invoiceNumber, $order, $items, $buyer, $seller, $payment)
    {
        $rows = '';
        $calculatedTotal = 0;

        foreach ($items as $item) {
            $productName = $item->product_name ?? ('Sản phẩm #' . ($item->product_id ?? ''));
            $brand = $item->brand ?? $item->product_brand ?? 'Chưa cập nhật';
            $color = $item->color ?? $item->product_color ?? 'Chưa cập nhật';
            $year = $item->year ?? $item->manufacture_year ?? $item->product_year ?? 'Chưa cập nhật';

            $quantity = (float) ($item->quantity ?? 0);
            $unitPrice = (float) ($item->unit_price ?? $item->price ?? 0);
            $lineTotal = (float) ($item->line_total ?? 0);

            if ($lineTotal <= 0) {
                $lineTotal = $quantity * $unitPrice;
            }

            $calculatedTotal += $lineTotal;

            $rows .= '
                <tr>
                    <td>' . esc_html($productName) . '</td>
                    <td>' . esc_html($brand) . '</td>
                    <td>' . esc_html($color) . '</td>
                    <td style="text-align:center;">' . esc_html($year) . '</td>
                    <td style="text-align:center;">' . esc_html(number_format($quantity, 0, ',', '.')) . '</td>
                    <td style="text-align:right;">' . number_format($unitPrice, 0, ',', '.') . ' VND</td>
                    <td style="text-align:right;">' . number_format($lineTotal, 0, ',', '.') . ' VND</td>
                </tr>
            ';
        }

        $total = (float) ($order->total_amount ?? 0);

        if ($total <= 0 && $payment && !empty($payment->amount)) {
            $total = (float) $payment->amount;
        }

        if ($total <= 0) {
            $total = $calculatedTotal;
        }

        $paidAt = $payment && !empty($payment->paid_at) ? $payment->paid_at : current_time('mysql');

        return '
        <!doctype html>
        <html lang="vi">
        <head>
            <meta charset="utf-8">
            <title>Hóa đơn ' . esc_html($invoiceNumber) . '</title>
        </head>
        <body style="font-family:Arial,sans-serif;color:#111827;padding:24px;">
            <div style="max-width:980px;margin:0 auto;border:1px solid #e5e7eb;border-radius:14px;padding:24px;">
                <h1 style="margin:0 0 8px;">HÓA ĐƠN ĐIỆN TỬ</h1>
                <p style="margin:0;color:#6b7280;">B2B Marketplace - Ô tô trực tuyến</p>

                <hr style="margin:20px 0;border:none;border-top:1px solid #e5e7eb;">

                <p><strong>Số hóa đơn:</strong> ' . esc_html($invoiceNumber) . '</p>
                <p><strong>Order ID:</strong> #' . (int) $order->id . '</p>
                <p><strong>Ngày thanh toán:</strong> ' . esc_html($paidAt) . '</p>
                <p><strong>Trạng thái:</strong> Đã thanh toán escrow</p>

                <h3>Bên mua</h3>
                <p><strong>Công ty:</strong> ' . esc_html($buyer->company_name ?? ('Buyer #' . $order->buyer_company_id)) . '</p>
                <p><strong>Mã số thuế:</strong> ' . esc_html($buyer->tax_code ?? '-') . '</p>
                <p><strong>Địa chỉ:</strong> ' . esc_html($buyer->address ?? '-') . '</p>

                <h3>Bên bán</h3>
                <p><strong>Công ty:</strong> ' . esc_html($seller->company_name ?? ('Seller #' . $order->seller_company_id)) . '</p>
                <p><strong>Mã số thuế:</strong> ' . esc_html($seller->tax_code ?? '-') . '</p>
                <p><strong>Địa chỉ:</strong> ' . esc_html($seller->address ?? '-') . '</p>

                <h3>Chi tiết hàng hóa</h3>
                <table width="100%" cellpadding="10" cellspacing="0" style="border-collapse:collapse;border:1px solid #e5e7eb;">
                    <thead>
                        <tr style="background:#f3f4f6;">
                            <th align="left">Sản phẩm</th>
                            <th align="left">Hãng xe</th>
                            <th align="left">Màu sắc</th>
                            <th align="center">Năm SX</th>
                            <th align="center">Số lượng</th>
                            <th align="right">Đơn giá</th>
                            <th align="right">Thành tiền</th>
                        </tr>
                    </thead>
                    <tbody>' . $rows . '</tbody>
                    <tfoot>
                        <tr>
                            <td colspan="6" style="text-align:right;font-weight:bold;">Tổng thanh toán</td>
                            <td style="text-align:right;font-weight:bold;">' . number_format($total, 0, ',', '.') . ' VND</td>
                        </tr>
                    </tfoot>
                </table>

                <p style="margin-top:20px;color:#6b7280;font-size:13px;">
                    Đây là hóa đơn điện tử được tạo tự động sau khi người mua thanh toán escrow trên hệ thống.
                </p>
            </div>
        </body>
        </html>';
    }

    private function saveInvoiceFile($invoiceNumber, $html)
    {
        $upload = wp_upload_dir();
        $dir = trailingslashit($upload['basedir']) . 'b2b-invoices';

        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }

        $filename = sanitize_file_name($invoiceNumber . '.html');
        $path = trailingslashit($dir) . $filename;
        $url = trailingslashit($upload['baseurl']) . 'b2b-invoices/' . $filename;

        file_put_contents($path, $html);

        return [
            'path' => $path,
            'url' => $url,
        ];
    }

    private function sendInvoiceEmail($order, $invoice, $invoicePath)
    {
        $email = $this->buyerEmail((int) $order->buyer_company_id);

        if (!$email) {
            error_log('[B2B INVOICE] Không tìm thấy email buyer cho order #' . $order->id);
            return false;
        }

        $body = '
            <div style="font-family:Arial,sans-serif;">
                <h2>Thanh toán escrow thành công</h2>
                <p>Hệ thống đã ghi nhận thanh toán cho Order #' . (int) $order->id . '.</p>
                <p>Số hóa đơn: <strong>' . esc_html($invoice->invoice_number) . '</strong></p>
                <p>Bạn có thể xem hóa đơn tại đây:</p>
                <p><a href="' . esc_url($invoice->invoice_file) . '">Xem hóa đơn điện tử</a></p>
            </div>
        ';

        Mailer::sendContract(
            [$email],
            'Hóa đơn thanh toán Order #' . (int) $order->id,
            $body,
            [$invoicePath]
        );

        return true;
    }

    private function buyerEmail($buyerCompanyId)
    {
        $buyerCompanyId = (int) $buyerCompanyId;

        if ($buyerCompanyId <= 0) {
            return null;
        }

        $email = $this->companyRepository->findPrimaryMemberEmail($buyerCompanyId);

        if ($this->isValidEmail($email)) {
            return strtolower(trim((string) $email));
        }

        $fallbackEmail = $this->companyRepository->findLatestSellerRequestEmail($buyerCompanyId);

        if ($this->isValidEmail($fallbackEmail)) {
            return strtolower(trim((string) $fallbackEmail));
        }

        return null;
    }

    private function isValidEmail($email)
    {
        $email = trim((string) $email);

        if ($email === '') {
            return false;
        }

        if (function_exists('is_email')) {
            return (bool) is_email($email);
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    private function normalizeInvoiceItems($order, $items)
    {
        $contractItems = [];

        if (!empty($order->contract_id)) {
            $contract = $this->contractRepository->findById((int) $order->contract_id);
            $contractData = $this->decodeJson($contract->contract_file ?? '');

            if (!empty($contractData['items']) && is_array($contractData['items'])) {
                foreach ($contractData['items'] as $contractItem) {
                    $productId = (int) ($contractItem['product_id'] ?? 0);
                    if ($productId > 0) {
                        $contractItems[$productId] = $contractItem;
                    }
                }
            }
        }

        $normalized = [];

        foreach ($items as $item) {
            $productId = (int) ($item->product_id ?? 0);
            $contractItem = $contractItems[$productId] ?? [];

            $quantity = (float) ($item->quantity ?? 0);

            $unitPrice = (float) ($item->unit_price ?? $item->price ?? 0);
            if ($unitPrice <= 0) {
                $unitPrice = (float) (
                    $contractItem['final_unit_price']
                    ?? $contractItem['unit_price']
                    ?? $contractItem['price']
                    ?? 0
                );
            }

            $lineTotal = (float) ($item->line_total ?? 0);
            if ($lineTotal <= 0) {
                $lineTotal = $quantity * $unitPrice;
            }
            if ($lineTotal <= 0) {
                $lineTotal = (float) ($contractItem['line_total'] ?? 0);
            }

            $item->product_name = $this->firstText(
                $item->product_name ?? '',
                $contractItem['product_name'] ?? '',
                'Sản phẩm #' . $productId
            );

            $item->brand = $this->firstText(
                $item->brand ?? '',
                $item->product_brand ?? '',
                $contractItem['brand'] ?? ''
            );

            $item->color = $this->firstText(
                $item->color ?? '',
                $item->product_color ?? '',
                $contractItem['color'] ?? ''
            );

            $item->year = $this->firstText(
                $item->year ?? '',
                $item->manufacture_year ?? '',
                $item->product_year ?? '',
                $contractItem['year'] ?? '',
                $contractItem['product_year'] ?? ''
            );

            $item->unit_price = $unitPrice;
            $item->line_total = $lineTotal;

            $normalized[] = $item;
        }

        return $normalized;
    }

    private function decodeJson($value)
    {
        if (empty($value)) {
            return [];
        }

        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function firstText(...$values)
    {
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}
