<?php

require_once __DIR__ . '/WpUserService.php';
require_once __DIR__ . '/../Repositories/RFQRepository.php';

class SupportService
{
    private $ticketRepository;
    private $messageRepository;
    private $companyRepository;
    private $productRepository;
    private $rfqRepository;
    private $rfqService;
    private $quotationService;

    public function __construct()
    {
        $this->ticketRepository = new SupportTicketRepository();
        $this->messageRepository = new SupportMessageRepository();
        $this->companyRepository = new CompanyRepository();
        $this->productRepository = new ProductRepository();
        $this->rfqRepository = new RFQRepository();
        $this->rfqService = new RFQService();
        $this->quotationService = new QuotationService();
    }

    public function createTicket($data)
    {
        $userId = (int) ($data['user_id'] ?? 0);
        $supportUserId = (int) ($data['support_user_id'] ?? 0);
        $ticketType = $this->ticketType($data['type'] ?? 'dispute');
        $rfqId = (int) ($data['rfq_id'] ?? 0);
        $bulkId = (int) ($data['bulk_id'] ?? 0);
        $supportReference = $this->text($data['support_reference'] ?? '');

        if ($userId <= 0) {
            throw new Exception('Missing user_id');
        }

        if ($supportUserId > 0 && !$this->isSupportUserId($supportUserId)) {
            throw new Exception('Support user is invalid');
        }

        $resolved = $this->extractSupportReferenceIds(
            $data['type'] ?? '',
            $supportReference,
            $rfqId,
            $bulkId
        );

        $rfqId = (int) ($resolved['rfq_id'] ?? 0);
        $bulkId = (int) ($resolved['bulk_id'] ?? 0);

        $resolvedFromRfq = $this->resolveRfqReferences($rfqId, $bulkId);
        $rfqId = (int) ($resolvedFromRfq['rfq_id'] ?? $rfqId);
        $bulkId = (int) ($resolvedFromRfq['bulk_id'] ?? $bulkId);

        $this->validateTicketReferenceAccess($data, $ticketType, $rfqId, $bulkId);
        $this->assertNoDuplicateOpenTicket($data, $userId, $ticketType, $rfqId, $bulkId);

        if ($supportUserId <= 0 && $this->isBulkTicketType($ticketType, $bulkId)) {
            $supportUserId = $this->randomSupportUserId($userId);
        }

        $ticketId = $this->ticketRepository->create([
            'user_id' => $userId,
            'order_id' => !empty($data['order_id']) ? (int) $data['order_id'] : null,
            'type' => $ticketType,
            'status' => 'open',
            'created_at' => current_time('mysql')
        ]);

        $this->saveTicketMeta($ticketId, [
            'support_user_id' => $supportUserId > 0 ? $supportUserId : null,
            'rfq_id' => $rfqId > 0 ? $rfqId : null,
            'bulk_id' => $bulkId > 0 ? $bulkId : null,
            'support_reference' => $supportReference !== '' ? $supportReference : null,
        ]);

        if (!empty($data['message'])) {
            $this->sendMessage([
                'ticket_id' => $ticketId,
                'sender_id' => $userId,
                'message' => $data['message'],
                'advance_status' => false
            ]);
        }

        return $this->detail($ticketId);
    }

    public function sendMessage($data)
    {
        $ticketId = (int) ($data['ticket_id'] ?? 0);
        $senderId = (int) ($data['sender_id'] ?? 0);

        if ($ticketId <= 0 || $senderId <= 0 || empty($data['message'])) {
            throw new Exception('Missing support message data');
        }

        $ticket = $this->ticketRepository->findById($ticketId);

        if (!$ticket) {
            throw new Exception('Support ticket not found');
        }

        if ($ticket->status === 'closed') {
            throw new Exception('Support ticket is closed');
        }

        $messageId = $this->messageRepository->create([
            'ticket_id' => $ticketId,
            'sender_id' => $senderId,
            'message' => $this->text($data['message']),
            'created_at' => current_time('mysql')
        ]);

        $advanceStatus = array_key_exists('advance_status', $data)
            ? (bool) $data['advance_status']
            : true;

        if ($advanceStatus && $ticket->status === 'open') {
            $this->ticketRepository->update($ticketId, [
                'status' => 'processing'
            ]);
        }

        return [
            'message_id' => $messageId,
            'ticket' => $this->detail($ticketId)
        ];
    }

    public function close($ticketId, $status = 'closed')
    {
        $status = in_array($status, ['resolved', 'closed'], true) ? $status : 'closed';

        $ticket = $this->ticketRepository->findById((int) $ticketId);

        if (!$ticket) {
            throw new Exception('Support ticket not found');
        }

        $this->ticketRepository->update($ticketId, [
            'status' => $status
        ]);

        return $this->detail($ticketId);
    }

    public function list($userId = null, $all = false, $includeMessages = false, $viewer = [])
    {
        if ($all) {
            $tickets = $this->ticketRepository->listAll();

            if ($this->hasAnyRole($viewer, ['SUPPORT']) && !$this->hasAnyRole($viewer, ['ADMIN'])) {
                $supportUserId = (int) $userId;
                $tickets = array_values(array_filter($tickets, function ($ticket) use ($supportUserId) {
                    $ticketId = (int) ($ticket->id ?? 0);
                    $meta = $this->ticketMeta($ticketId);
                    $assignedSupportId = (int) ($meta['support_user_id'] ?? 0);

                    return $assignedSupportId <= 0 || $assignedSupportId === $supportUserId;
                }));
            }
        } else {
            if ((int) $userId <= 0) {
                throw new Exception('Missing user_id');
            }

            $tickets = $this->ticketRepository->listByUser((int) $userId);
        }

        if (!$includeMessages) {
            return array_map(function ($ticket) {
                return $this->decorateTicketRecord($ticket);
            }, $tickets);
        }

        return array_map(function ($ticket) {
            if (!is_object($ticket) && !is_array($ticket)) {
                return $ticket;
            }

            $ticketData = is_object($ticket) ? (array) $ticket : $ticket;
            $ticketId = (int) ($ticketData['id'] ?? $ticketData['ID'] ?? 0);

            $ticketData['messages'] = $this->messageRepository->listByTicket($ticketId);

            return $this->decorateTicketRecord($ticketData);
        }, $tickets);
    }

    public function detail($ticketId)
    {
        $ticket = $this->ticketRepository->findById((int) $ticketId);

        if (!$ticket) {
            throw new Exception('Support ticket not found');
        }

        return [
            'ticket' => $this->decorateTicketRecord($ticket),
            'messages' => $this->messageRepository->listByTicket($ticket->id)
        ];
    }

    private function validateTicketReferenceAccess($data, $ticketType, $rfqId, $bulkId)
    {
        if (!$this->shouldValidateTicketReference($data, $ticketType)) {
            return;
        }

        $rfqId = (int) $rfqId;
        $bulkId = (int) $bulkId;

        if ($rfqId <= 0 && $bulkId <= 0) {
            throw new Exception('Vui lòng nhập đúng mã RFQ hoặc Bulk từ trang Tin nhắn để tạo ticket hỗ trợ.');
        }

        $companyId = (int) ($data['company_id'] ?? 0);

        if ($companyId <= 0) {
            throw new Exception('Không xác định công ty của tài khoản. Vui lòng đăng nhập lại rồi thử lại.');
        }

        if ($rfqId > 0 && !$this->isRfqVisibleForCompany($rfqId, $companyId)) {
            throw new Exception('Mã RFQ #' . $rfqId . ' không thuộc cuộc giao dịch trong trang Tin nhắn của bạn.');
        }

        if ($bulkId > 0 && !$this->isBulkVisibleForViewer($bulkId, $data, $companyId)) {
            throw new Exception('Mã Bulk #' . $bulkId . ' không thuộc cuộc giao dịch trong trang Tin nhắn của bạn.');
        }
    }

    private function assertNoDuplicateOpenTicket($data, $userId, $ticketType, $rfqId, $bulkId)
    {
        if (!$this->shouldValidateTicketReference($data, $ticketType)) {
            return;
        }

        $userId = (int) $userId;
        $rfqId = (int) $rfqId;
        $bulkId = (int) $bulkId;

        if ($userId <= 0 || ($rfqId <= 0 && $bulkId <= 0)) {
            return;
        }

        $tickets = $this->ticketRepository->listByUser($userId);

        foreach ($tickets as $ticket) {
            $ticketId = (int) ($ticket->id ?? 0);
            $existingType = (string) ($ticket->type ?? '');
            $status = strtolower(trim((string) ($ticket->status ?? '')));

            if (
                $ticketId <= 0
                || !$this->isTicketActiveForDuplicateCheck($status)
                || !$this->shouldValidateTicketReference([], $existingType)
            ) {
                continue;
            }

            $meta = $this->ticketMeta($ticketId);
            $sameRfq = $rfqId > 0 && (int) ($meta['rfq_id'] ?? 0) === $rfqId;
            $sameBulk = $bulkId > 0 && (int) ($meta['bulk_id'] ?? 0) === $bulkId;

            if (!$sameRfq && !$sameBulk) {
                continue;
            }

            $referenceLabel = $sameRfq ? ('RFQ #' . $rfqId) : ('Bulk #' . $bulkId);
            $statusLabel = $this->ticketStatusForDuplicateMessage($status);

            throw new Exception(
                'Đã có ticket #' . $ticketId
                . ' cho ' . $referenceLabel
                . ' (trạng thái: ' . $statusLabel . '). '
                . 'Vui lòng tiếp tục trao đổi trong ticket hiện có.'
            );
        }
    }

    private function isTicketActiveForDuplicateCheck($status)
    {
        $status = strtolower(trim((string) $status));

        return !in_array($status, ['closed', 'cancelled', 'rejected'], true);
    }

    private function ticketStatusForDuplicateMessage($status)
    {
        $status = strtolower(trim((string) $status));

        return [
            'open' => 'mở',
            'processing' => 'đang xử lý',
            'resolved' => 'đã giải quyết',
            'closed' => 'đã đóng',
        ][$status] ?? $status;
    }
    private function shouldValidateTicketReference($data, $ticketType)
    {
        if (!empty($data['skip_reference_validation'])) {
            return false;
        }

        $ticketType = strtolower(trim((string) $ticketType));

        return !in_array($ticketType, ['seller_broadcast_channel', 'bulk_seller_channel'], true);
    }

    private function isRfqVisibleForCompany($rfqId, $companyId)
    {
        $rfqId = (int) $rfqId;
        $companyId = (int) $companyId;

        if ($rfqId <= 0 || $companyId <= 0) {
            return false;
        }

        try {
            $detail = $this->rfqService->detail($rfqId);
        } catch (Throwable $e) {
            return false;
        }

        $rfq = $detail['rfq'] ?? null;

        if (!$rfq) {
            return false;
        }

        if ((int) ($rfq->buyer_company_id ?? 0) === $companyId) {
            return true;
        }

        $sellerCompanyIds = array_map('intval', (array) ($detail['seller_company_ids'] ?? []));

        return in_array($companyId, $sellerCompanyIds, true);
    }

    private function isBulkVisibleForViewer($bulkId, $data, $companyId)
    {
        $bulk = $this->bulkById((int) $bulkId);

        if (!$bulk) {
            return false;
        }

        $viewer = is_array($data) ? $data : [];
        $viewer['company_id'] = (int) $companyId;

        return $this->canViewBulk($bulk, $viewer);
    }

    public function ticketContext($ticketId)
    {
        $detail = $this->detail((int) $ticketId);
        $ticket = $detail['ticket'] ?? null;
        $messages = $detail['messages'] ?? [];

        return $this->buildTicketContext($ticket, $messages);
    }

    private function buildTicketContext($ticket, $messages = [])
    {
        $ticketData = is_object($ticket)
            ? (array) $ticket
            : (is_array($ticket) ? $ticket : []);

        $sources = $this->supportTicketSources($ticketData, $messages);
        $meta = $this->extractSupportMetaFromSources($sources);
        $referenceHint = $this->supportReferenceHint($ticketData, $meta);

        $orderId = $this->firstPositiveInt([
            $ticketData['order_id'] ?? 0,
            $meta['order_id'] ?? 0,
            $this->extractSupportRefId(['order id', 'ma order', 'don hang', 'order'], $sources),
        ]);

        $contractId = $this->firstPositiveInt([
            $ticketData['contract_id'] ?? 0,
            $meta['contract_id'] ?? 0,
            $this->extractSupportRefId(['contract id', 'ma hop dong', 'hop dong', 'contract'], $sources),
        ]);

        $rfqId = $this->firstPositiveInt([
            $ticketData['rfq_id'] ?? 0,
            $meta['rfq_id'] ?? 0,
            (($referenceHint['kind'] ?? '') === 'rfq') ? ($referenceHint['id'] ?? 0) : 0,
            $this->extractSupportRefId(['rfq id', 'rfq'], $sources),
        ]);

        $bulkId = $this->firstPositiveInt([
            $ticketData['bulk_id'] ?? 0,
            $meta['bulk_id'] ?? 0,
            (($referenceHint['kind'] ?? '') === 'bulk') ? ($referenceHint['id'] ?? 0) : 0,
            $this->extractSupportRefId(['bulk id', 'bulk rfq', 'bulk'], $sources),
        ]);

        $searchText = $this->supportTicketSearchText($ticketData, $sources);
        $kind = $this->resolveSupportTicketKind(
            $ticketData,
            $meta,
            $referenceHint,
            $orderId,
            $contractId,
            $rfqId,
            $bulkId,
            $searchText
        );
        $referenceLabel = $this->supportTicketReferenceLabel(
            $kind,
            $referenceHint,
            $orderId,
            $contractId,
            $rfqId,
            $bulkId
        );

        $isContractTicket = $contractId > 0
            || $kind === 'contract'
            || strpos($searchText, 'contract') !== false;
        $isOrderTicket = $orderId > 0
            || $kind === 'order'
            || strpos($searchText, 'order') !== false
            || strpos($searchText, 'escrow') !== false
            || strpos($searchText, 'payment') !== false;

        return [
            'kind' => $kind,
            'order_id' => $orderId > 0 ? $orderId : null,
            'contract_id' => $contractId > 0 ? $contractId : null,
            'rfq_id' => $rfqId > 0 ? $rfqId : null,
            'bulk_id' => $bulkId > 0 ? $bulkId : null,
            'reference_label' => $referenceLabel,
            'is_contract_ticket' => $isContractTicket,
            'is_order_ticket' => $isOrderTicket,
        ];
    }

    private function supportTicketSources($ticketData, $messages = [])
    {
        $sources = [];

        foreach (['message', 'initial_message'] as $key) {
            $value = trim((string) ($ticketData[$key] ?? ''));

            if ($value !== '') {
                $sources[] = $value;
            }
        }

        foreach ((array) $messages as $message) {
            if (!is_object($message) && !is_array($message)) {
                continue;
            }

            $row = is_object($message) ? (array) $message : $message;
            $text = trim((string) ($row['message'] ?? $row['content'] ?? ''));

            if ($text !== '') {
                $sources[] = $text;
            }
        }

        return $sources;
    }

    private function extractSupportMetaFromSources($sources)
    {
        $meta = [];

        foreach ((array) $sources as $source) {
            $source = (string) $source;

            if ($source === '') {
                continue;
            }

            if (!preg_match('/\[\[B2B_SUPPORT_META\]\]([\s\S]*?)\[\[\/B2B_SUPPORT_META\]\]/', $source, $match)) {
                continue;
            }

            $block = trim((string) ($match[1] ?? ''));

            if ($block === '') {
                continue;
            }

            foreach (preg_split('/\r?\n/', $block) as $line) {
                if (strpos($line, '=') === false) {
                    continue;
                }

                $parts = explode('=', $line, 2);
                $key = trim((string) ($parts[0] ?? ''));
                $value = trim((string) ($parts[1] ?? ''));

                if ($key !== '') {
                    $meta[$key] = $value;
                }
            }
        }

        return $meta;
    }

    private function supportReferenceHint($ticketData, $meta)
    {
        $sources = [
            trim((string) ($ticketData['support_reference'] ?? '')),
            trim((string) ($meta['support_reference'] ?? '')),
        ];

        foreach ($sources as $source) {
            if ($source === '') {
                continue;
            }

            $lower = strtolower($source);
            preg_match('/\d+/', $source, $idMatch);
            $id = !empty($idMatch[0]) ? (int) $idMatch[0] : 0;

            if (strpos($lower, 'rfq') !== false) {
                return [
                    'kind' => 'rfq',
                    'id' => $id,
                ];
            }

            if (strpos($lower, 'bulk') !== false) {
                return [
                    'kind' => 'bulk',
                    'id' => $id,
                ];
            }
        }

        return [
            'kind' => '',
            'id' => 0,
        ];
    }

    private function extractSupportRefId($labels, $sources)
    {
        $normalizedLabels = array_values(array_filter(array_map(function ($label) {
            return strtolower(trim((string) $label));
        }, (array) $labels)));

        if (empty($normalizedLabels)) {
            return 0;
        }

        foreach ((array) $sources as $source) {
            foreach (preg_split('/\r?\n/', (string) $source) as $line) {
                $lowerLine = strtolower($line);
                $matched = false;

                foreach ($normalizedLabels as $label) {
                    if ($label !== '' && strpos($lowerLine, $label) !== false) {
                        $matched = true;
                        break;
                    }
                }

                if (!$matched) {
                    continue;
                }

                if (preg_match_all('/\d+/', $line, $matches) && !empty($matches[0])) {
                    $last = end($matches[0]);
                    $id = (int) $last;

                    if ($id > 0) {
                        return $id;
                    }
                }
            }
        }

        return 0;
    }

    private function resolveSupportTicketKind($ticketData, $meta, $referenceHint, $orderId, $contractId, $rfqId, $bulkId, $searchText)
    {
        $metaKind = strtolower(trim((string) ($meta['kind'] ?? '')));

        if (in_array($metaKind, ['rfq', 'bulk', 'contract', 'order'], true)) {
            return $metaKind;
        }

        $referenceKind = strtolower(trim((string) ($referenceHint['kind'] ?? '')));

        if (in_array($referenceKind, ['rfq', 'bulk'], true)) {
            return $referenceKind;
        }

        $type = strtolower(trim((string) ($ticketData['type'] ?? '')));

        if ($bulkId > 0 || strpos($type, 'bulk') !== false || strpos($searchText, 'bulk') !== false) {
            return 'bulk';
        }

        if ($contractId > 0 || strpos($type, 'contract') !== false || strpos($searchText, 'contract') !== false) {
            return 'contract';
        }

        if (
            $orderId > 0
            || strpos($type, 'order') !== false
            || strpos($type, 'payment') !== false
            || strpos($searchText, 'order') !== false
            || strpos($searchText, 'escrow') !== false
            || strpos($searchText, 'payment') !== false
        ) {
            return 'order';
        }

        if ($rfqId > 0 || strpos($type, 'rfq') !== false || strpos($searchText, 'rfq') !== false) {
            return 'rfq';
        }

        return '';
    }

    private function supportTicketSearchText($ticketData, $sources)
    {
        $chunks = array_values(array_filter(array_merge([
            (string) ($ticketData['type'] ?? ''),
            (string) ($ticketData['support_reference'] ?? ''),
        ], (array) $sources)));

        return strtolower(implode("\n", $chunks));
    }

    private function supportTicketReferenceLabel($kind, $referenceHint, $orderId, $contractId, $rfqId, $bulkId)
    {
        $hintKind = strtolower(trim((string) ($referenceHint['kind'] ?? '')));
        $hintId = (int) ($referenceHint['id'] ?? 0);

        if ($hintKind === 'rfq' && $hintId > 0) {
            return 'RFQ #' . $hintId;
        }

        if ($hintKind === 'bulk' && $hintId > 0) {
            return 'Bulk #' . $hintId;
        }

        if ($kind === 'bulk' && $bulkId > 0) {
            return 'Bulk #' . $bulkId;
        }

        if (in_array($kind, ['rfq', 'contract', 'order'], true) && $rfqId > 0) {
            return 'RFQ #' . $rfqId;
        }

        if ($rfqId > 0) {
            return 'RFQ #' . $rfqId;
        }

        if ($bulkId > 0) {
            return 'Bulk #' . $bulkId;
        }

        if ($orderId > 0) {
            return 'Order #' . $orderId;
        }

        if ($contractId > 0) {
            return 'Contract #' . $contractId;
        }

        return 'Khong co';
    }

    private function firstPositiveInt($values)
    {
        foreach ((array) $values as $value) {
            $number = (int) $value;

            if ($number > 0) {
                return $number;
            }
        }

        return 0;
    }

    public function supportAgents()
    {
        $users = WpUserService::instance()->listUsers([
            'role__in' => ['support', 'administrator', 'admin'],
            'orderby' => 'display_name',
            'order' => 'ASC',
        ]);

        $rows = [];

        foreach ((array) $users as $user) {
            $userId = (int) ($user->ID ?? 0);

            if ($userId <= 0) {
                continue;
            }

            $displayName = trim((string) ($user->display_name ?? $user->user_login ?? ''));

            if ($displayName === '') {
                $displayName = 'Support #' . $userId;
            }

            $roleType = 'admin';

            foreach ((array) ($user->roles ?? []) as $role) {
                if (strpos(strtoupper((string) $role), 'SUPPORT') !== false) {
                    $roleType = 'support';
                    break;
                }
            }

            if ($roleType !== 'support' && class_exists('\\B2B\\Helpers\\RoleHelper')) {
                try {
                    if (\B2B\Helpers\RoleHelper::hasRole($userId, 'ROLE_SUPPORT')) {
                        $roleType = 'support';
                    }
                } catch (Throwable $e) {
                }
            }

            $rows[] = [
                'id' => $userId,
                'name' => $displayName,
                'role' => $roleType,
            ];
        }

        usort($rows, function ($a, $b) {
            $aRank = ($a['role'] ?? '') === 'support' ? 0 : 1;
            $bRank = ($b['role'] ?? '') === 'support' ? 0 : 1;

            if ($aRank !== $bRank) {
                return $aRank <=> $bRank;
            }

            return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        return $rows;
    }

    public function startBulkPurchase($data)
    {
        $userId = $this->positiveInt($data, 'user_id');
        $companyId = $this->positiveInt($data, 'company_id');
        $assignedSupportUserId = (int) ($data['support_user_id'] ?? 0);

        if ($assignedSupportUserId <= 0) {
            $assignedSupportUserId = $this->randomSupportUserId();
        }
        $message = 'tôi muốn thu mua số lượng lớn, hãy hỗ trợ tôi';
        $ticketDetail = $this->createTicket([
            'user_id' => $userId,
            'company_id' => $companyId,
            'type' => 'bulk_purchase',
            'message' => $message,
            'support_user_id' => $assignedSupportUserId > 0 ? $assignedSupportUserId : null,
            'skip_reference_validation' => true,
        ]);

        $ticket = $ticketDetail['ticket'] ?? null;
        $bulkId = $this->nextBulkId();
        $bulk = [
            'id' => $bulkId,
            'ticket_id' => (int) ($ticket->id ?? 0),
            'support_user_id' => $assignedSupportUserId > 0 ? $assignedSupportUserId : null,
            'buyer_user_id' => $userId,
            'buyer_company_id' => $companyId,
            'buyer_company_name' => $this->companyName($companyId),
            'status' => 'started',
            'initial_message' => $message,
            'product_info' => [],
            'seller_offers' => [],
            'contracts' => [],
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];

        $this->saveBulk($bulk);

        return [
            'bulk' => $this->decorateBulk($bulk, $data),
            'ticket' => $ticketDetail
        ];
    }

    public function bulkPurchases($data)
    {
        $rows = [];

        foreach ($this->bulkIndex() as $bulkId) {
            $bulk = $this->bulkById($bulkId);

            if (!$bulk || !$this->canViewBulk($bulk, $data)) {
                continue;
            }

            $rows[] = $this->decorateBulk($bulk, $data);
        }

        usort($rows, function ($a, $b) {
            return (int) $b['id'] <=> (int) $a['id'];
        });

        return $rows;
    }

    public function requestBulkForm($data)
    {
        $this->assertSupport($data);
        $bulk = $this->requireBulk($data);

        if (!in_array($bulk['status'], ['started', 'form_requested'], true)) {
            throw new Exception('Bulk request cannot request buyer form now');
        }

        $bulk['status'] = 'form_requested';
        $bulk['updated_at'] = current_time('mysql');
        $this->saveBulk($bulk);
        $this->systemTicketMessage(
            $bulk,
            (int) $data['user_id'],
            'Người hỗ trợ: Quý khách vui lòng điền form thông tin sản phẩm để bộ phận hỗ trợ gửi RFQ đến seller phù hợp.'
        );

        return $this->decorateBulk($bulk, $data);
    }

    public function submitBulkProductForm($data)
    {
        $bulk = $this->requireBulk($data);
        $this->assertBulkBuyer($bulk, $data);

        if (in_array($bulk['status'], ['published', 'fulfilled', 'buyer_notified', 'accepted'], true)) {
            throw new Exception('Bulk request product form cannot be changed now');
        }

        $productInfo = $this->bulkProductInfo($data);
        $bulk['product_info'] = $productInfo;
        $bulk['status'] = 'buyer_submitted';
        $bulk['updated_at'] = current_time('mysql');
        $this->saveBulk($bulk);
        $this->systemTicketMessage(
            $bulk,
            (int) $data['user_id'],
            'Đã gửi form thông tin sản phẩm:' . "\n" . $this->productInfoText($productInfo)
        );

        return $this->decorateBulk($bulk, $data);
    }

    public function sendBulkRFQ($data)
    {
        $this->assertSupport($data);
        $bulk = $this->requireBulk($data);

        if (empty($bulk['product_info'])) {
            throw new Exception('Buyer product form is missing');
        }

        if (!empty($bulk['seller_rfq_sent_at']) && !empty($bulk['seller_ticket_id'])) {
            return $this->decorateBulk($bulk, $data);
        }

        if ($bulk['status'] !== 'buyer_submitted') {
            throw new Exception('Bulk request cannot be sent to sellers now');
        }

        $bulk['status'] = 'published';
        $bulk['published_at'] = current_time('mysql');
        $bulk['updated_at'] = current_time('mysql');

        $bulk['broadcast_message'] = implode("\n", [
            'Hệ thống TMDT B2B Marketplace xin thông báo: vừa có yêu cầu thu mua số lượng lớn sản phẩm sau.',
            'Nếu muốn tham gia, hãy bấm vào nút Tham Gia bên dưới form và điền số lượng có thể cung cấp.',
            'Hãy chắc chắn thông tin sản phẩm của mình trùng khớp với thông tin sản phẩm mà khách hàng muốn.'
        ]);

        $bulk = $this->ensureBulkSellerTicket($bulk, (int) $data['user_id']);

        $this->saveBulk($bulk);

        $this->systemBulkSellerMessage(
            $bulk,
            (int) $data['user_id'],
            $bulk['broadcast_message'] . "\n\n" . $this->productInfoText($bulk['product_info'])
        );

        $bulk['seller_rfq_sent_at'] = current_time('mysql');
        $bulk['updated_at'] = current_time('mysql');
        $this->saveBulk($bulk);

        return $this->decorateBulk($bulk, $data);
    }

    public function joinBulkRFQ($data)
    {
        $this->assertSeller($data);
        $bulk = $this->requireBulk($data);

        if ($bulk['status'] !== 'published') {
            throw new Exception('Bulk RFQ is not open for seller participation');
        }

        if ((int) $bulk['buyer_company_id'] === (int) $data['company_id']) {
            throw new Exception('Buyer company cannot join its own bulk RFQ');
        }

        $offer = $this->sellerOffer($data);
        $offers = $bulk['seller_offers'] ?? [];
        $sellerCompanyKey = (string) $offer['seller_company_id'];
        $requestedQuantity = $this->requestedQuantity($bulk);
        $offeredQuantityExceptCurrentSeller = 0;

        foreach ($offers as $companyKey => $existingOffer) {
            if ((string) $companyKey === $sellerCompanyKey) {
                continue;
            }

            $offeredQuantityExceptCurrentSeller += (int) ($existingOffer['available_quantity'] ?? 0);
        }

        $remainingQuantity = max(0, $requestedQuantity - $offeredQuantityExceptCurrentSeller);
        $originalQuantity = (int) ($offer['available_quantity'] ?? 0);

        if ($requestedQuantity > 0 && $remainingQuantity <= 0) {
            throw new Exception('Số lượng RFQ bulk đã đủ, seller không thể tham gia thêm.');
        }

        if (
            $requestedQuantity > 0 &&
            $originalQuantity > $remainingQuantity &&
            empty($data['confirm_adjusted_quantity'])
        ) {
            return [
                'requires_quantity_confirmation' => true,
                'original_quantity' => $originalQuantity,
                'adjusted_quantity' => $remainingQuantity,
                'message' => 'Vì số lượng hàng người mua đang cần ít hơn, nên hệ thống đã tự cập nhật lại đúng số lượng còn thiếu, quý khách vui lòng kiểm tra lại có muốn cung cấp số lượng này hay không?',
                'bulk' => $this->decorateBulk($bulk, $data)
            ];
        }

        if (
            $requestedQuantity > 0 &&
            $originalQuantity > $remainingQuantity &&
            !empty($data['confirm_adjusted_quantity'])
        ) {
            $offer['available_quantity'] = $remainingQuantity;
            $offer['quantity_adjusted'] = true;
            $offer['adjusted_message'] = 'Seller đã đồng ý cung cấp theo số lượng còn thiếu: ' . $remainingQuantity;
        }

        $offers[$sellerCompanyKey] = $offer;
        $bulk['seller_offers'] = $offers;

  
        $this->systemBulkSellerMessage(
            $bulk,
            (int) $data['user_id'],
            $offer['seller_company_name'] . ' đã tham gia RFQ bulk với số lượng ' . (int) $offer['available_quantity'] . '.'
        );


        if (
            empty($bulk['seller_closed_message_sent_at']) &&
            $this->offeredQuantity($bulk) >= $this->requestedQuantity($bulk)
        ) {
            $closedMessage = 'Số lượng seller tham gia đã đủ. Hệ thống tự động đóng phiên RFQ và tạo hợp đồng riêng cho từng seller.';

            $bulk['status'] = 'accepted';
            $bulk['fulfilled_at'] = current_time('mysql');
            $bulk['accepted_at'] = current_time('mysql');
            $bulk['closed_at'] = current_time('mysql');
            $bulk['seller_closed_message_sent_at'] = current_time('mysql');
            $bulk['contracts'] = $this->bulkContracts($bulk);

        
            $this->systemBulkSellerMessage(
                $bulk,
                (int) $data['user_id'],
                $closedMessage
            );


            $this->systemTicketMessage(
                $bulk,
                (int) $data['user_id'],
                $closedMessage
            );
        }

        $bulk['updated_at'] = current_time('mysql');
        $this->saveBulk($bulk);

        return $this->decorateBulk($bulk, $data);
    }

    public function sendBulkToBuyer($data)
    {
        $this->assertSupport($data);
        $bulk = $this->requireBulk($data);

        if ($this->offeredQuantity($bulk) < $this->requestedQuantity($bulk)) {
            throw new Exception('Seller offered quantity has not reached buyer requested quantity');
        }

        $bulk['status'] = 'buyer_notified';
        $bulk['buyer_notified_at'] = current_time('mysql');
        $bulk['updated_at'] = current_time('mysql');
        $this->saveBulk($bulk);
        $this->systemTicketMessage(
            $bulk,
            (int) $data['user_id'],
            "Support đã hoàn tất tiếp nhận yêu cầu RFQ đến toàn bộ seller, quý khách hàng vui lòng kiểm tra lại thông tin mà form đã gửi về.\n\n" . $this->sellerOffersText($bulk)
        );

        return $this->decorateBulk($bulk, $data);
    }

    public function acceptBulkQuotation($data)
    {
        $bulk = $this->requireBulk($data);
        $this->assertBulkBuyer($bulk, $data);

        if ($bulk['status'] !== 'buyer_notified') {
            throw new Exception('Bulk quotation cannot be accepted now');
        }

        $bulk['contracts'] = $this->bulkContracts($bulk);
        $bulk['status'] = 'accepted';
        $bulk['accepted_at'] = current_time('mysql');
        $bulk['updated_at'] = current_time('mysql');
        $this->saveBulk($bulk);
        $this->systemTicketMessage(
            $bulk,
            (int) $data['user_id'],
            'Buyer đã đồng ý báo giá. Hệ thống đã tạo hồ sơ hợp đồng bulk cho từng seller.'
        );

        return $this->decorateBulk($bulk, $data);
    }

    public function sendBulkMessage($data)
    {
        $bulk = $this->requireBulk($data);
        $message = $this->text($data['message'] ?? '');

        if ($message === '') {
            throw new Exception('Missing bulk message');
        }

        $ticketId = $this->bulkMessageTicketId($bulk, $data);

        if ($ticketId <= 0) {
            throw new Exception('Bulk chat does not have ticket thread');
        }

        return $this->sendMessage([
            'ticket_id' => $ticketId,
            'sender_id' => (int) ($data['user_id'] ?? 0),
            'message' => $message,
            'advance_status' => true
        ]);
    }

    public function sellerChannel($data)
    {
        $this->assertSellerOrSupport($data);
        $userId = (int) ($data['user_id'] ?? 0);
        $ticketId = $this->ensureSellerBroadcastTicketId($userId);
        $detail = $this->detail($ticketId);
        $ticket = $detail['ticket'] ?? null;
        $messages = is_array($detail['messages'] ?? null) ? $detail['messages'] : [];
        $supportOwnerId = (int) ($ticket->user_id ?? 0);

        $messages = array_map(function ($message) use ($supportOwnerId) {
            $senderId = (int) ($message->sender_id ?? 0);
            $senderType = 'seller';

            if ($senderId > 0 && $this->isSupportUserId($senderId)) {
                $senderType = 'support';
            } elseif ($supportOwnerId > 0 && $senderId === $supportOwnerId) {
                $senderType = 'support';
            }

            $message->sender_type = $senderType;

            return $message;
        }, $messages);

        return [
            'ticket' => $ticket,
            'messages' => $messages
        ];
    }

    public function sendSellerChannelMessage($data)
    {
        $this->assertSellerOrSupport($data);

        $senderId = (int) ($data['user_id'] ?? 0);
        $message = $this->text($data['message'] ?? '');

        if ($senderId <= 0 || $message === '') {
            throw new Exception('Missing seller channel message');
        }

        $ticketId = $this->ensureSellerBroadcastTicketId($senderId);

        $this->sendMessage([
            'ticket_id' => $ticketId,
            'sender_id' => $senderId,
            'message' => $message,
            'advance_status' => true
        ]);

        return $this->sellerChannel($data);
    }

    private function ticketType($type)
    {
        $type = function_exists('sanitize_text_field')
            ? sanitize_text_field((string) $type)
            : trim((string) $type);

        return $type !== '' ? $type : 'dispute';
    }

    private function isBulkTicketType($ticketType, $bulkId = 0)
    {
        if ((int) $bulkId > 0) {
            return true;
        }

        return strpos(strtolower((string) $ticketType), 'bulk') !== false;
    }

    private function extractSupportReferenceIds($type, $reference, $rfqId = 0, $bulkId = 0)
    {
        $rfqId = (int) $rfqId;
        $bulkId = (int) $bulkId;
        $referenceText = strtolower(trim((string) $reference));

        if ($rfqId > 0 || $bulkId > 0 || $referenceText === '') {
            return [
                'rfq_id' => $rfqId,
                'bulk_id' => $bulkId,
            ];
        }

        if (!preg_match('/\d+/', $referenceText, $matched)) {
            return [
                'rfq_id' => $rfqId,
                'bulk_id' => $bulkId,
            ];
        }

        $refNumber = (int) ($matched[0] ?? 0);

        if ($refNumber <= 0) {
            return [
                'rfq_id' => $rfqId,
                'bulk_id' => $bulkId,
            ];
        }

        if (strpos($referenceText, 'bulk') !== false) {
            return [
                'rfq_id' => $rfqId,
                'bulk_id' => $refNumber,
            ];
        }

        if (strpos($referenceText, 'rfq') !== false) {
            return [
                'rfq_id' => $refNumber,
                'bulk_id' => $bulkId,
            ];
        }

        $typeText = strtolower(trim((string) $type));

        if (
            strpos($typeText, 'bulk') !== false ||
            strpos($typeText, 'thu mua') !== false ||
            strpos($typeText, 'số lượng lớn') !== false
        ) {
            return [
                'rfq_id' => $rfqId,
                'bulk_id' => $refNumber,
            ];
        }

        return [
            'rfq_id' => $refNumber,
            'bulk_id' => $bulkId,
        ];
    }

    private function resolveRfqReferences($rfqId, $bulkId)
    {
        return $this->rfqRepository->resolveReferences($rfqId, $bulkId);
    }

private function decorateTicketRecord($ticket)
    {
        if (!is_object($ticket) && !is_array($ticket)) {
            return $ticket;
        }

        $isObject = is_object($ticket);
        $ticketData = $isObject ? (array) $ticket : $ticket;
        $ticketId = (int) ($ticketData['id'] ?? $ticketData['ID'] ?? 0);

        if ($ticketId <= 0) {
            return $isObject ? (object) $ticketData : $ticketData;
        }

        $meta = $this->ticketMeta($ticketId);
        $supportUserId = (int) ($meta['support_user_id'] ?? 0);
        $rfqId = (int) ($meta['rfq_id'] ?? 0);
        $bulkId = (int) ($meta['bulk_id'] ?? 0);

        $ticketData['support_user_id'] = $supportUserId > 0 ? $supportUserId : null;
        $ticketData['rfq_id'] = $rfqId > 0 ? $rfqId : null;
        $ticketData['bulk_id'] = $bulkId > 0 ? $bulkId : null;
        $ticketData['support_reference'] = $meta['support_reference'] ?? null;

        if ($supportUserId > 0) {
            $user = WpUserService::instance()->findRawById($supportUserId);
            $supportName = trim((string) ($user->display_name ?? $user->user_login ?? ''));
            $ticketData['support_user_name'] = $supportName !== '' ? $supportName : ('Support #' . $supportUserId);
        } else {
            $ticketData['support_user_name'] = null;
        }

        return $isObject ? (object) $ticketData : $ticketData;
    }

    private function saveTicketMeta($ticketId, $meta)
    {
        $ticketId = (int) $ticketId;

        if ($ticketId <= 0) {
            return;
        }

        $payload = [
            'support_user_id' => (int) ($meta['support_user_id'] ?? 0),
            'rfq_id' => (int) ($meta['rfq_id'] ?? 0),
            'bulk_id' => (int) ($meta['bulk_id'] ?? 0),
            'support_reference' => trim((string) ($meta['support_reference'] ?? '')),
            'updated_at' => current_time('mysql'),
        ];

        if ($payload['support_user_id'] <= 0) {
            $payload['support_user_id'] = null;
        }

        if ($payload['rfq_id'] <= 0) {
            $payload['rfq_id'] = null;
        }

        if ($payload['bulk_id'] <= 0) {
            $payload['bulk_id'] = null;
        }

        if ($payload['support_reference'] === '') {
            $payload['support_reference'] = null;
        }

        $this->ticketRepository->update($ticketId, [
            'support_user_id' => $payload['support_user_id'],
            'rfq_id' => $payload['rfq_id'],
            'bulk_id' => $payload['bulk_id'],
            'support_reference' => $payload['support_reference'],
            'meta_json' => wp_json_encode($payload),
        ]);
    }

    private function ticketMeta($ticketId)
    {
        $ticketId = (int) $ticketId;

        if ($ticketId <= 0) {
            return [];
        }

        $ticket = $this->ticketRepository->findById($ticketId);

        if ($ticket) {
            $meta = [
                'support_user_id' => isset($ticket->support_user_id) ? (int) $ticket->support_user_id : 0,
                'rfq_id' => isset($ticket->rfq_id) ? (int) $ticket->rfq_id : 0,
                'bulk_id' => isset($ticket->bulk_id) ? (int) $ticket->bulk_id : 0,
                'support_reference' => isset($ticket->support_reference) ? (string) $ticket->support_reference : '',
                'updated_at' => $ticket->updated_at ?? null,
            ];

            if ($meta['support_user_id'] > 0 || $meta['rfq_id'] > 0 || $meta['bulk_id'] > 0 || $meta['support_reference'] !== '') {
                return $meta;
            }

            if (!empty($ticket->meta_json)) {
                $decoded = json_decode((string) $ticket->meta_json, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        return [];
    }

    private function positiveInt($data, $key)
    {
        $value = (int) ($data[$key] ?? 0);

        if ($value <= 0) {
            throw new Exception('Missing or invalid ' . $key);
        }

        return $value;
    }

    private function text($value)
{
    $text = trim((string) $value);

    if (function_exists('wp_unslash')) {
        $text = wp_unslash($text);
    }

    if (function_exists('sanitize_textarea_field')) {
        $text = sanitize_textarea_field($text);
    }

    if (function_exists('wp_check_invalid_utf8')) {
        $text = wp_check_invalid_utf8($text, false);
    }

    return $text;
}

    private function number($value)
    {
        return round((float) $value, 2);
    }

    private function roles($data)
    {
        $raw = $data['roles'] ?? [];

        if (is_array($raw)) {
            return array_map(function ($role) {
                return strtoupper(trim((string) (is_object($role) ? ($role->role_name ?? '') : $role)));
            }, $raw);
        }

        $raw = trim((string) $raw);

        if ($raw === '') {
            return [];
        }

        if ($raw[0] === '[') {
            $decoded = json_decode($raw, true);

            if (is_array($decoded)) {
                return $this->roles(['roles' => $decoded]);
            }
        }

        return [strtoupper($raw)];
    }

    private function hasAnyRole($data, $needles)
    {
        $roles = $this->roles($data);

        foreach ($roles as $role) {
            foreach ($needles as $needle) {
                if (strpos($role, strtoupper($needle)) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isSupport($data)
    {
        return $this->hasAnyRole($data, ['ADMIN', 'SUPPORT']);
    }

    private function isSeller($data)
    {
        return $this->hasAnyRole($data, ['SELLER']);
    }

    private function assertSupport($data)
    {
        if (!$this->isSupport($data)) {
            throw new Exception('Only support/admin can do this action');
        }
    }

    private function assertSeller($data)
    {
        if (!$this->isSeller($data) || (int) ($data['company_id'] ?? 0) <= 0) {
            throw new Exception('Only seller company can join bulk RFQ');
        }
    }

    private function assertSellerOrSupport($data)
    {
        if (!$this->isSeller($data) && !$this->isSupport($data)) {
            throw new Exception('Only seller/support/admin can do this action');
        }
    }

    private function assertBulkBuyer($bulk, $data)
    {
        if ((int) ($bulk['buyer_company_id'] ?? 0) !== (int) ($data['company_id'] ?? 0)) {
            throw new Exception('Only buyer company can do this action');
        }
    }

    private function nextBulkId()
    {
        return $this->rfqRepository->nextBulkId();
    }

    private function bulkIndex()
    {
        return $this->rfqRepository->bulkIndex();
    }

    private function saveBulk($bulk)
    {
        $this->rfqRepository->saveBulk((array) $bulk);
    }

    private function bulkById($bulkId)
    {
        return $this->rfqRepository->bulkById($bulkId);
    }

    private function requireBulk($data)
    {
        $bulkId = (int) ($data['bulk_id'] ?? ($data['id'] ?? 0));
        $bulk = $this->bulkById($bulkId);

        if (!$bulk) {
            throw new Exception('Bulk purchase request not found');
        }

        if (!$this->canViewBulk($bulk, $data)) {
            throw new Exception('You cannot view this bulk purchase request');
        }

        return $bulk;
    }

    private function canViewBulk($bulk, $data)
    {
        if ($this->isSupport($data)) {
            if ($this->hasAnyRole($data, ['ADMIN'])) {
                return true;
            }

            $assignedSupportId = $this->bulkAssignedSupportUserId($bulk);
            $viewerUserId = (int) ($data['user_id'] ?? 0);

            if ($assignedSupportId > 0) {
                return $viewerUserId > 0 && $assignedSupportId === $viewerUserId;
            }

            return true;
        }

        if ((int) ($bulk['buyer_company_id'] ?? 0) === (int) ($data['company_id'] ?? 0)) {
            return true;
        }

        if ($this->isSeller($data)) {
            return in_array($bulk['status'] ?? '', ['published', 'fulfilled', 'buyer_notified', 'accepted'], true);
        }

        return false;
    }

    private function decorateBulk($bulk, $data)
    {
        $requestedQuantity = $this->requestedQuantity($bulk);
        $offeredQuantity = $this->offeredQuantity($bulk);
        $viewerCompanyId = (int) ($data['company_id'] ?? 0);
        $viewerOffer = null;

        $allOffers = is_array($bulk['seller_offers'] ?? null) ? $bulk['seller_offers'] : [];

        foreach ($allOffers as $offer) {
            if ((int) ($offer['seller_company_id'] ?? 0) === $viewerCompanyId) {
                $viewerOffer = $offer;
                break;
            }
        }

        $isSupport = $this->isSupport($data);
        $isSeller = $this->isSeller($data);
        $isBuyer = (int) ($bulk['buyer_company_id'] ?? 0) === $viewerCompanyId;
        $bulkForMessages = $bulk;
        $bulkForMessages['seller_offers'] = $allOffers;
        $bulk['seller_offers'] = array_values($allOffers);
        $bulk['seller_offers_hidden'] = false;
        $bulk['requested_quantity'] = $requestedQuantity;
        $bulk['offered_quantity'] = $offeredQuantity;
        $bulk['remaining_quantity'] = max(0, $requestedQuantity - $offeredQuantity);
        $bulk['viewer_role'] = $isSupport
            ? 'support'
            : ($isBuyer ? 'buyer' : ($isSeller ? 'seller' : 'buyer'));
        $bulk['viewer_offer'] = $viewerOffer;
        $bulk['can_request_form'] = $isSupport && in_array($bulk['status'], ['started', 'form_requested'], true);
        $bulk['can_submit_buyer_form'] = $isBuyer && in_array($bulk['status'], ['form_requested', 'buyer_submitted'], true);
        $bulk['can_send_rfq'] = $isSupport && $bulk['status'] === 'buyer_submitted';
        $bulk['can_join'] = !$isSupport && $isSeller && $bulk['status'] === 'published' && !$viewerOffer && $viewerCompanyId !== (int) ($bulk['buyer_company_id'] ?? 0);
        $bulk['can_send_buyer'] = $isSupport && $bulk['status'] === 'fulfilled';
        $bulk['can_accept'] = $isBuyer && $bulk['status'] === 'buyer_notified' && !empty($allOffers);
        $bulk['messages'] = $this->bulkMessages($bulkForMessages, $data);
        $bulk['support_user_id'] = $this->bulkAssignedSupportUserId($bulk);

        return $bulk;
    }

    private function bulkAssignedSupportUserId($bulk)
    {
        $bulkSupportId = (int) ($bulk['support_user_id'] ?? 0);

        if ($bulkSupportId > 0) {
            return $bulkSupportId;
        }

        $ticketId = (int) ($bulk['ticket_id'] ?? 0);

        if ($ticketId <= 0) {
            return 0;
        }

        $meta = $this->ticketMeta($ticketId);

        return (int) ($meta['support_user_id'] ?? 0);
    }

    private function randomSupportUserId($fallbackUserId = 0)
    {
        $rows = $this->supportAgents();
        $supportIds = [];
        $adminIds = [];

        foreach ((array) $rows as $row) {
            $id = (int) ($row['id'] ?? 0);

            if ($id <= 0) {
                continue;
            }

            $role = strtolower((string) ($row['role'] ?? ''));

            if ($role === 'support') {
                $supportIds[] = $id;
            } else {
                $adminIds[] = $id;
            }
        }

        if (!empty($supportIds)) {
            return (int) $supportIds[array_rand($supportIds)];
        }

        if (!empty($adminIds)) {
            return (int) $adminIds[array_rand($adminIds)];
        }

        return (int) $fallbackUserId;
    }
    private function bulkMessages($bulk, $data = [])
    {
        $ticketId = $this->bulkMessageTicketId($bulk, $data);

        if ($ticketId <= 0) {
            return [];
        }

        $messages = array_map(function ($message) use ($bulk) {
            $senderId = (int) ($message->sender_id ?? 0);
            $messageText = (string) ($message->message ?? '');
            $senderType = 'support';
            $isSystemSupportMessage =
                strpos($messageText, 'Số lượng seller tham gia đã đủ') === 0 ||
                strpos($messageText, 'Hệ thống tự động') === 0 ||
                strpos($messageText, 'Hệ thống TMDT B2B Marketplace xin thông báo') === 0 ||
                strpos($messageText, 'Support đã hoàn tất') === 0 ||
                strpos($messageText, 'Người hỗ trợ:') === 0;

            if ($isSystemSupportMessage) {
                $message->sender_type = 'support';
                return $message;
            }

            if ($senderId === (int) ($bulk['buyer_user_id'] ?? 0)) {
                $senderType = 'buyer';
            }

            foreach (($bulk['seller_offers'] ?? []) as $offer) {
                if ($senderId > 0 && $senderId === (int) ($offer['seller_user_id'] ?? 0)) {
                    $senderType = 'seller';
                    break;
                }
            }

            $message->sender_type = $senderType;

            return $message;
        }, $this->messageRepository->listByTicket($ticketId));

        return $messages;
    }

    private function bulkMessageTicketId($bulk, $data = [])
    {

        if ($this->isSupport($data)) {
            if (($data['channel'] ?? '') === 'seller') {
                $sellerTicketId = (int) ($bulk['seller_ticket_id'] ?? 0);

                if ($sellerTicketId > 0) {
                    return $sellerTicketId;
                }
            }
            return (int) ($bulk['ticket_id'] ?? 0);
        }
        if ($this->isSeller($data)) {
            return (int) ($bulk['seller_ticket_id'] ?? 0);
        }
        return (int) ($bulk['ticket_id'] ?? 0);
    }
    private function bulkProductInfo($data)
    {
        $quantity = (int) ($data['quantity'] ?? 0);

        if ($quantity <= 0) {
            throw new Exception('Missing requested quantity');
        }

        $deadline = $this->text($data['deadline'] ?? '');
        $today = function_exists('current_time') ? current_time('Y-m-d') : date('Y-m-d');

        if ($deadline !== '' && $deadline < $today) {
            throw new Exception('Hạn cần hàng phải từ ngày hôm nay trở đi');
        }

        return [
            'product_name' => $this->text($data['product_name'] ?? ''),
            'brand' => $this->text($data['brand'] ?? ''),
            'year' => $this->text($data['year'] ?? ''),
            'color' => $this->text($data['color'] ?? ''),
            'quantity' => $quantity,
            'target_price' => $this->number($data['target_price'] ?? 0),
            'delivery_location' => $this->text($data['delivery_location'] ?? ''),
            'deadline' => $deadline,
            'description' => $this->text($data['description'] ?? '')
        ];
    }

    private function sellerOffer($data)
    {
        $quantity = (int) ($data['available_quantity'] ?? ($data['quantity'] ?? 0));

        if ($quantity <= 0) {
            throw new Exception('Vui lòng nhập số lượng có thể cung cấp.');
        }

        $rawUnitPrice = trim((string) ($data['unit_price'] ?? ''));

        if ($rawUnitPrice === '') {
            throw new Exception('Vui lòng nhập đơn giá / xe trước khi tham gia RFQ.');
        }

        $unitPrice = $this->number($rawUnitPrice);

        if ($unitPrice <= 0) {
            throw new Exception('Đơn giá / xe phải lớn hơn 0.');
        }

        $companyId = (int) ($data['company_id'] ?? 0);

        return [
            'seller_company_id' => $companyId,
            'seller_user_id' => (int) ($data['user_id'] ?? 0),
            'seller_company_name' => $this->companyName($companyId),
            'product_name' => $this->text($data['product_name'] ?? ''),
            'brand' => $this->text($data['brand'] ?? ''),
            'year' => $this->text($data['year'] ?? ''),
            'color' => $this->text($data['color'] ?? ''),
            'available_quantity' => $quantity,
            'unit_price' => $unitPrice,
            'note' => $this->text($data['note'] ?? ''),
            'created_at' => current_time('mysql')
        ];
    }

    private function requestedQuantity($bulk)
    {
        return (int) ($bulk['product_info']['quantity'] ?? 0);
    }

    private function offeredQuantity($bulk)
    {
        $total = 0;

        foreach (($bulk['seller_offers'] ?? []) as $offer) {
            $total += (int) ($offer['available_quantity'] ?? 0);
        }

        return $total;
    }

    private function companyName($companyId)
    {
        $company = $this->companyRepository->findById((int) $companyId);

        return $company ? (string) $company->company_name : ('Company #' . (int) $companyId);
    }

    private function productInfoText($product)
    {
        return implode("\n", array_filter([
            'Sản phẩm: ' . ($product['product_name'] ?? ''),
            'Hãng xe: ' . ($product['brand'] ?? ''),
            'Năm/màu: ' . trim(($product['year'] ?? '') . ' ' . ($product['color'] ?? '')),
            'Số lượng cần mua: ' . (int) ($product['quantity'] ?? 0),
            'Giá mong muốn: ' . number_format((float) ($product['target_price'] ?? 0), 0, ',', '.') . ' VND',
            'Hạn cần hàng: ' . ($product['deadline'] ?? ''),
            'Mô tả: ' . ($product['description'] ?? '')
        ]));
    }

    private function sellerOffersText($bulk)
    {
        $lines = [];

        foreach (($bulk['seller_offers'] ?? []) as $offer) {
            $lines[] = '- ' . ($offer['seller_company_name'] ?? ('Seller #' . ($offer['seller_company_id'] ?? '')))
                . ': SL ' . (int) ($offer['available_quantity'] ?? 0)
                . ', giá ' . number_format((float) ($offer['unit_price'] ?? 0), 0, ',', '.') . ' VND'
                . ', sản phẩm ' . ($offer['product_name'] ?? '');
        }

        return implode("\n", $lines);
    }

    private function ensureBulkSellerTicket($bulk, $supportUserId)
    {
        if (!empty($bulk['seller_ticket_id'])) {
            return $bulk;
        }

        $ticketId = $this->ticketRepository->create([
            'user_id' => (int) $supportUserId,
            'order_id' => null,
            'type' => 'bulk_seller_channel',
            'status' => 'open',
            'created_at' => current_time('mysql')
        ]);

        $bulk['seller_ticket_id'] = (int) $ticketId;

        return $bulk;
    }

    private function systemBulkSellerMessage($bulk, $senderId, $message)
    {
        if (empty($bulk['seller_ticket_id']) || (int) $senderId <= 0) {
            return;
        }

        try {
            $this->sendMessage([
                'ticket_id' => (int) $bulk['seller_ticket_id'],
                'sender_id' => (int) $senderId,
                'message' => $message,
                'advance_status' => true
            ]);
        } catch (Exception $e) {
        }
    }

    private function systemTicketMessage($bulk, $senderId, $message)
    {
        if (empty($bulk['ticket_id']) || (int) $senderId <= 0) {
            return;
        }

        try {
            $this->sendMessage([
                'ticket_id' => (int) $bulk['ticket_id'],
                'sender_id' => (int) $senderId,
                'message' => $message,
                'advance_status' => true
            ]);
        } catch (Exception $e) {
        }
    }

    private function bulkContracts($bulk)
    {
        if (!empty($bulk['contracts']) && is_array($bulk['contracts']) && !empty($bulk['contracts'][0]['contract_id'])) {
            return $bulk['contracts'];
        }

        $contracts = [];

        foreach (($bulk['seller_offers'] ?? []) as $offer) {
            $contracts[] = $this->createBulkSellerTrade($bulk, $offer);
        }

        return $contracts;
    }

    private function createBulkSellerTrade($bulk, $offer)
    {
        $productInfo = $bulk['product_info'] ?? [];
        $sellerCompanyId = (int) ($offer['seller_company_id'] ?? 0);
        $quantity = (int) ($offer['available_quantity'] ?? 0);
        $unitPrice = (float) ($offer['unit_price'] ?? 0);
        $name = $this->firstText(
            $offer['product_name'] ?? '',
            $productInfo['product_name'] ?? '',
            'Bulk RFQ #' . (int) $bulk['id']
        );

        $productId = $this->productRepository->create([
            'company_id' => $sellerCompanyId,
            'name' => $name,
            'description' => $this->bulkProductDescription($bulk, $offer),
            'price_from' => $unitPrice,
            'years' => is_numeric($offer['year'] ?? null) ? (int) $offer['year'] : (is_numeric($productInfo['year'] ?? null) ? (int) $productInfo['year'] : null),
            'color' => $this->firstText($offer['color'] ?? '', $productInfo['color'] ?? '', 'Khác'),
            'brand' => $this->firstText($offer['brand'] ?? '', $productInfo['brand'] ?? '', 'Khác'),
            'quantity' => $quantity,
            'status' => 'draft'
        ]);

        $rfqDetail = $this->rfqService->create([
            'buyer_company_id' => (int) $bulk['buyer_company_id'],
            'message' => 'Bulk RFQ #' . (int) $bulk['id'] . ': ' . $this->productInfoText($productInfo),
            'rfq_type' => 'assisted',
            'created_by' => 'support',
            'bulk_id' => (int) $bulk['id'],
            'items' => [[
                'product_id' => $productId,
                'quantity' => $quantity,
                'note' => 'Tạo tự động từ bulk RFQ #' . (int) $bulk['id']
            ]]
        ]);

        $rfqId = (int) ($rfqDetail['rfq']->id ?? 0);
        $quotationDetail = $this->quotationService->submit([
            'rfq_id' => $rfqId,
            'seller_company_id' => $sellerCompanyId,
            'items' => [[
                'product_id' => $productId,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_percent' => 0
            ]]
        ]);

        $quotationId = (int) ($quotationDetail['quotation']->id ?? 0);
        $accepted = $this->quotationService->accept($quotationId);
        $contractId = (int) ($accepted['contract_id'] ?? 0);

        return [
            'bulk_id' => (int) $bulk['id'],
            'rfq_id' => $rfqId,
            'product_id' => $productId,
            'quotation_id' => $quotationId,
            'contract_id' => $contractId,
            'buyer_company_id' => (int) $bulk['buyer_company_id'],
            'seller_company_id' => $sellerCompanyId,
            'seller_company_name' => $offer['seller_company_name'] ?? $this->companyName($sellerCompanyId),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_amount' => round($quantity * $unitPrice, 2),
            'status' => 'draft',
            'created_at' => current_time('mysql')
        ];
    }

    private function bulkProductDescription($bulk, $offer)
    {
        return implode("\n", array_filter([
            'Tạo từ bulk RFQ #' . (int) ($bulk['id'] ?? 0),
            'Buyer yêu cầu:',
            $this->productInfoText($bulk['product_info'] ?? []),
            'Seller phản hồi:',
            'Sản phẩm: ' . ($offer['product_name'] ?? ''),
            'Số lượng: ' . (int) ($offer['available_quantity'] ?? 0),
            'Đơn giá: ' . number_format((float) ($offer['unit_price'] ?? 0), 0, ',', '.') . ' VND',
            'Ghi chú: ' . ($offer['note'] ?? '')
        ]));
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

    private function ensureSellerBroadcastTicketId($userId = 0)
    {
        $latest = $this->ticketRepository->findLatestByType('seller_broadcast_channel');

        if (!empty($latest->id)) {
            return (int) $latest->id;
        }

        $ownerId = (int) $userId > 0 ? (int) $userId : 1;
        $ticketId = (int) $this->ticketRepository->create([
            'user_id' => $ownerId,
            'order_id' => null,
            'type' => 'seller_broadcast_channel',
            'status' => 'open',
            'created_at' => current_time('mysql')
        ]);

        return $ticketId;
    }

    private function isSupportUserId($userId)
    {
        $userId = (int) $userId;

        if ($userId <= 0) {
            return false;
        }

        if (class_exists('\\B2B\\Helpers\\RoleHelper')) {
            try {
                if (
                    \B2B\Helpers\RoleHelper::hasRole($userId, 'ROLE_ADMIN')
                    || \B2B\Helpers\RoleHelper::hasRole($userId, 'ROLE_SUPPORT')
                ) {
                    return true;
                }
            } catch (Throwable $e) {
            }
        }

        $roles = WpUserService::instance()->roles($userId);

        if (empty($roles)) {
            return false;
        }

        foreach ((array) $roles as $role) {
            $upper = strtoupper((string) $role);

            if (strpos($upper, 'ADMIN') !== false || strpos($upper, 'SUPPORT') !== false) {
                return true;
            }
        }

        return false;
    }
}