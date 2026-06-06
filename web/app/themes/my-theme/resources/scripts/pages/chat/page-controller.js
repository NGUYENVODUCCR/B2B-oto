import { ContractAPI } from '../../api/contract.js';
import { ChatAPI } from '../../api/chat.js';
import { getHomeUrl } from '../../api/http.js';
import { OrderAPI } from '../../api/order.js';
import { PaymentAPI } from '../../api/payment.js';
import { QuotationAPI } from '../../api/quotation.js';
import { ReviewAPI } from '../../api/review.js';
import { SupportAPI } from '../../api/support.js';
import { UserAPI } from '../../api/user.js';
import {
  escapeHtml,
  formatCurrency,
  getProfileCompanyId,
  getProfileWpUserId,
} from '../../utils/format.js';
import { createChatWebSocket, getChatWebSocketUrl } from '../../utils/chat-websocket.js';
import { createRealtimeLoop, emitRealtimeEvent } from '../../utils/realtime.js';
import { createChatAccess } from './access.js';
import { createChatSession } from './session.js';
import { createSupportTicketContext } from './support-ticket-context.js';
import { createInitialChatState } from './state.js';
import {
  bulkConversationFrom,
  isBulkPurchaseTicket,
  isHiddenSupportTicketInTradeChat,
  supportConversationFrom,
} from './conversations.js';
import { quotationPricingLine, quoteLineMath } from './pricing.js';
import { createQuotePanelRenderers } from './quote-panels.js';
import { createSignatureModalController } from './signature-modal.js';
import { createChatDom, qs } from './dom.js';
import { createBulkPanelRenderers } from './bulk-panels.js';
import { createSupportRequestModal } from './support-request-modal.js';
import { formatDate } from './date.js';
import {
  contractA4Document,
  contractPanel,
  conversationRow,
  conversationTitle,
  messageBubble,
  orderPanel,
  quotationCard,
  statusLabel,
} from './templates.js';

const state = createInitialChatState();

const {
  companyId,
  wpUserId,
  roleForRfq,
  supportMessageSenderType,
  activeRole,
  preferredBulkChannel,
  isBulkActive,
  isEditingDealForm,
  activeQuotation,
  ownQuotation,
  currentPartySigned,
  bulkById,
  handleChatError,
} = createChatSession({
  state,
  qs,
  getHomeUrl,
  getProfileCompanyId,
  getProfileWpUserId,
});

const {
  hydrateAccessContext,
  canManageSupportTickets,
} = createChatAccess({ state });

const {
  clearActiveTicketContext,
  hydrateActiveTicketContext,
  supportTicketOrderId,
  supportTicketContractId,
  supportTicketReferenceLabel,
  isContractSupportTicket,
  isOrderSupportTicket,
} = createSupportTicketContext({ state });

async function sendTradeMessage(message, senderType = roleForRfq()) {
  const senderId = wpUserId();
  const payload = {
    rfq_id: state.activeId,
    sender_type: senderType,
    message,
  };

  if (senderId > 0) {
    payload.sender_id = senderId;
  }

  return ChatAPI.sendMessage(payload);
}

function renderConversations() {
  const search = (qs('chatSearchInput')?.value || '').toLowerCase().trim();

  state.filtered = state.conversations.filter((rfq) => {
    if (!search) return true;
    return [
      rfq.id,
      rfq.bulk_id,
      rfq.ticket_id,
      rfq.title,
      rfq.buyer_company_name,
      rfq.seller_company_names,
      rfq.counterparty_name,
      rfq.product_names,
      rfq.last_message,
      rfq.status,
    ].some((value) => String(value || '').toLowerCase().includes(search));
  });

  if (!state.filtered.length) {
    setConversationList('<div class="chat-empty">Chưa có đoạn chat giao dịch.</div>');
    return;
  }

  setConversationList(state.filtered.map((rfq) => (
    conversationRow(rfq, rfq.participant_role, state.activeId)
  )).join(''));
}

function messageKey(messages) {
  if (!messages.length) {
    return 'empty';
  }

  const last = messages[messages.length - 1];
  return `${messages.length}:${last.id || ''}:${last.created_at || ''}:${last.message || ''}`;
}

function shouldStickToBottom(messageBox) {
  return messageBox.scrollHeight - messageBox.scrollTop - messageBox.clientHeight < 90;
}

const {
  setConversationList,
  setDealPanelHtml,
  setMessageBoxHtml,
} = createChatDom({ shouldStickToBottom });

function renderMessages(options = {}) {
  const messageBox = qs('chatMessages');
  const role = activeRole();

  if (!messageBox) return;

  if (!state.messages.length) {
    setMessageBoxHtml(
      '<div class="chat-empty">Chưa có tin nhắn. Hãy bắt đầu thương lượng.</div>',
      options
    );
    return;
  }

  const html = state.messages.map((message) => messageBubble(message, role)).join('');
  setMessageBoxHtml(html, options);
}

function currentUserName() {
  return state.profile?.wp_user?.display_name
    || state.profile?.user?.fullname
    || state.profile?.user?.name
    || '';
}

const {
  renderSellerQuotePanel,
  renderBuyerQuotePanel,
  recalculateQuoteForm,
  handleQuoteCalculatorInput,
} = createQuotePanelRenderers({
  state,
  companyId,
  ownQuotation,
  escapeHtml,
  formatCurrency,
  quotationPricingLine,
  quoteLineMath,
  quotationCard,
  statusLabel,
});

const SUPPORT_META_START = '[[B2B_SUPPORT_META]]';
const SUPPORT_META_END = '[[/B2B_SUPPORT_META]]';

function buildSupportMetaBlock(request = {}) {
  const lines = [
    SUPPORT_META_START,
    `kind=${request.kind || ''}`,
    `order_id=${request.order_id || ''}`,
    `contract_id=${request.contract_id || ''}`,
    `rfq_id=${state.activeId || ''}`,
    SUPPORT_META_END,
  ];

  return lines.join('\n');
}

function renderSupportAdminActions(ticket = state.activeTicket) {
  if (!canManageSupportTickets()) {
    return '';
  }

  const orderId = supportTicketOrderId(ticket);
  const contractId = supportTicketContractId(ticket);
  const actions = [];

  if (isContractSupportTicket(ticket) && contractId > 0) {
    actions.push(`
      <button type="button" class="danger" data-chat-action="support-cancel-contract" data-contract-id="${escapeHtml(contractId)}">
        Hủy hợp đồng
      </button>
    `);
  }

  if (isOrderSupportTicket(ticket) && orderId > 0) {
    actions.push(`
      <button type="button" class="danger" data-chat-action="support-cancel-order" data-order-id="${escapeHtml(orderId)}">
        Hủy order
      </button>
    `);
  }

  if (!actions.length) {
    return '';
  }

  return `
    <div class="chat-inline-actions chat-support-admin-actions">
      ${actions.join('')}
    </div>
  `;
}


const {
  closeBulkFormModal,
  handleBulkDeadlineChange,
  openBulkFormModal,
  renderBulkDealPanel,
  validateBulkDeadline,
} = createBulkPanelRenderers({
  state,
  qs,
  escapeHtml,
  formatCurrency,
  statusLabel,
  companyId,
  activeRole,
});

function renderDealPanel() {
  const panel = qs('chatDealPanel');

  if (!panel) return;

  if (state.activeSupportTicketId && state.activeTicket) {
    setDealPanelHtml(`
      <section class="chat-panel">
        <h3>Thông tin ticket support</h3>
        <div class="chat-support-summary">
          <p><strong>Loại:</strong> ${escapeHtml(state.activeTicket.type || 'support')}</p>
          <p><strong>Support phụ trách:</strong> ${escapeHtml(state.activeTicket.support_user_name || 'Tự động phân công')}</p>
          <p><strong>Mã rfq hoặc bulk của bạn (của chat rfq):</strong> ${escapeHtml(supportTicketReferenceLabel(state.activeTicket))}</p>
          <p><strong>Trạng thái:</strong> ${escapeHtml(statusLabel(state.activeTicket.status || 'open'))}</p>
          <p><strong>Tạo lúc:</strong> ${escapeHtml(formatDate(state.activeTicket.created_at))}</p>
        </div>
        ${renderSupportAdminActions(state.activeTicket)}
      </section>
    `);
    return;
  }

  if (state.activeBulk) {
    setDealPanelHtml(renderBulkDealPanel());
    return;
  }

  const role = roleForRfq();
  const accepted = activeQuotation();
  const currentParty = role;
  const canSign = state.contractDetail?.contract?.status === 'draft' && !currentPartySigned(currentParty);

  let html = role === 'seller' ? renderSellerQuotePanel() : renderBuyerQuotePanel();

  if (accepted && !state.contractDetail) {
    html += `
      <section class="chat-panel">
        <h3>Hợp đồng</h3>
        <button type="button" data-chat-action="create-contract" data-quotation-id="${escapeHtml((accepted.quotation || accepted).id)}">Tạo hợp đồng</button>
      </section>
    `;
  }

  html += contractPanel(state.contractDetail, currentParty, canSign);

  if (state.contractDetail?.contract?.status === 'signed' && !state.orderDetail?.order) {
    html += `
      <section class="chat-panel">
        <h3>Order</h3>
        <button type="button" data-chat-action="create-order" data-contract-id="${escapeHtml(state.contractDetail.contract.id)}">Tạo order</button>
      </section>
    `;
  }

  html += orderPanel(
  state.orderDetail,
  role,
  state.reviewDetail,
  companyId(),
  canManageSupportTickets()
);

  setDealPanelHtml(html);
}

function renderRfqHeader() {
  const rfq = state.rfqDetail?.rfq;

  if (!rfq) return;

  qs('chatEmptyState')?.classList.add('hidden');
  qs('chatActivePane')?.classList.remove('hidden');
  qs('chatActiveTitle').innerText = conversationTitle(rfq, roleForRfq(rfq));
  qs('chatActiveKicker').innerText = `RFQ #${rfq.id}`;
  qs('chatActiveMeta').innerText = rfq.product_names || (state.rfqDetail.items || []).map((item) => item.product_name).filter(Boolean).join(', ');
  qs('chatActiveStatus').innerText = statusLabel(rfq.status);
}

function renderActivePane(options = {}) {
  if (!state.rfqDetail?.rfq) return;

  renderRfqHeader();

  renderConversations();
  renderMessages({ forceScroll: Boolean(options.forceScroll) });
  renderDealPanel();
}

function renderBulkActivePane(options = {}) {
  const request = state.activeBulk;

  if (!request) return;

  qs('chatEmptyState')?.classList.add('hidden');
  qs('chatActivePane')?.classList.remove('hidden');
  qs('chatActiveTitle').innerText = request.viewer_role === 'seller'
    ? 'Kênh RFQ chung với Support'
    : 'Thu mua số lượng lớn';
  qs('chatActiveKicker').innerText = `Bulk RFQ #${request.id}`;
  qs('chatActiveMeta').innerText = request.product_info?.product_name || 'Yêu cầu thu mua số lượng lớn';
  qs('chatActiveStatus').innerText = statusLabel(request.status);

  renderConversations();
  renderMessages({ forceScroll: Boolean(options.forceScroll) });
  renderDealPanel();
}

function renderSupportActivePane(options = {}) {
  const ticket = state.activeTicket;

  if (!ticket) return;

  qs('chatEmptyState')?.classList.add('hidden');
  qs('chatActivePane')?.classList.remove('hidden');
  qs('chatActiveTitle').innerText = 'Hỗ trợ';
  qs('chatActiveKicker').innerText = `Ticket #${ticket.id}`;
  qs('chatActiveMeta').innerText = ticket.order_id ? `Order #${ticket.order_id}` : 'Yêu cầu hỗ trợ hệ thống';
  qs('chatActiveStatus').innerText = statusLabel(ticket.status || 'open');

  renderConversations();
  renderMessages({ forceScroll: Boolean(options.forceScroll) });
  renderDealPanel();
}

async function loadContractAndOrder() {
  state.contractDetail = null;
  state.orderDetail = null;
  state.reviewDetail = null;

  const accepted = activeQuotation();
  if (!accepted) return;

  const quoteId = (accepted.quotation || accepted).id;
  const contractRef = await ContractAPI.byQuotation(quoteId).catch(() => null);
  const contractId = contractRef?.contract?.id || contractRef?.contract_id || contractRef?.id;

  if (!contractId) return;

  state.contractDetail = contractRef?.contract ? contractRef : await ContractAPI.detail(contractId).catch(() => null);
  const orderId = state.contractDetail?.order?.id;

  if (orderId) {
    state.orderDetail = await OrderAPI.detail(orderId).catch(() => null);
    state.reviewDetail = await ReviewAPI.byOrder(orderId).catch(() => null);
  }
}

async function openConversation(rfqId, options = {}) {
  const isCurrentConversation = (
    !state.activeSupportTicketId
    && !state.activeBulkId
    && String(state.activeId) === String(rfqId)
    && Boolean(state.rfqDetail?.rfq)
  );
  const preserveContent = options.preserveContent ?? isCurrentConversation;
  const previousMessages = preserveContent ? state.messages : [];
  const previousQuotations = preserveContent ? state.quotations : [];

  state.activeSupportTicketId = null;
  state.activeTicket = null;
  clearActiveTicketContext();
  state.activeBulkId = null;
  state.activeBulk = null;
  state.activeId = rfqId;
  state.chatSocket?.subscribe?.(rfqId);
  qs('chatActivePane')?.classList.remove('hidden');
  qs('chatEmptyState')?.classList.add('hidden');

  if (!preserveContent) {
    setMessageBoxHtml('<div class="chat-empty">Đang tải tin nhắn...</div>');
    setDealPanelHtml('<div class="chat-empty">Đang tải giao dịch...</div>');
  }

  renderConversations();

  state.rfqDetail = await ChatAPI.detail(rfqId);
  const summary = state.conversations.find((rfq) => String(rfq.id) === String(rfqId));
  if (summary && state.rfqDetail?.rfq) {
    state.rfqDetail.rfq = {
      ...summary,
      ...state.rfqDetail.rfq,
    };
  }
  state.messages = await ChatAPI.messages(rfqId).catch(() => previousMessages);
  state.lastMessageKey = messageKey(state.messages);
  state.quotations = await QuotationAPI.byRfq(rfqId).catch(() => previousQuotations);
  await loadContractAndOrder();
  renderActivePane({ forceScroll: Boolean(options.forceScroll) || !preserveContent });

  const url = new URL(window.location.href);
  url.searchParams.delete('support_ticket_id');
  url.searchParams.delete('bulk_id');
  url.searchParams.set('rfq_id', rfqId);
  window.history.replaceState({}, '', url);
}

async function openBulkConversation(bulkId) {
  state.activeSupportTicketId = null;
  state.activeTicket = null;
  clearActiveTicketContext();
  state.activeId = `bulk:${bulkId}`;
  state.activeBulkId = bulkId;
  state.rfqDetail = null;
  state.quotations = [];
  state.contractDetail = null;
  state.orderDetail = null;
  state.reviewDetail = null;
  qs('chatActivePane')?.classList.remove('hidden');
  qs('chatEmptyState')?.classList.add('hidden');
  setMessageBoxHtml('<div class="chat-empty">Đang tải tin nhắn bulk...</div>');
  setDealPanelHtml('<div class="chat-empty">Đang tải bulk RFQ...</div>');
  renderConversations();

  const fresh = await SupportAPI.listBulkPurchases({
    channel: canManageSupportTickets() ? 'seller' : undefined,
  });
  const request = (Array.isArray(fresh) ? fresh : []).find((item) => String(item.id) === String(bulkId)) || bulkById(bulkId);
  if (!request) {
    throw new Error('Không tìm thấy bulk RFQ.');
  }

  state.activeBulk = request;
  state.messages = Array.isArray(request.messages) ? request.messages : [];
  state.lastMessageKey = messageKey(state.messages);
  renderBulkActivePane({ forceScroll: true });

  const url = new URL(window.location.href);
  url.searchParams.delete('rfq_id');
  url.searchParams.delete('support_ticket_id');
  url.searchParams.set('bulk_id', bulkId);
  window.history.replaceState({}, '', url);
}

async function openSupportTicket(ticketId) {
  state.activeSupportTicketId = ticketId;
  state.activeTicket = null;
  clearActiveTicketContext();
  state.activeId = `support:${ticketId}`;
  state.activeBulkId = null;
  state.activeBulk = null;
  state.rfqDetail = null;
  state.quotations = [];
  state.contractDetail = null;
  state.orderDetail = null;
  state.reviewDetail = null;
  qs('chatActivePane')?.classList.remove('hidden');
  qs('chatEmptyState')?.classList.add('hidden');
  setMessageBoxHtml('<div class="chat-empty">Đang tải yêu cầu hỗ trợ...</div>');
  setDealPanelHtml('<div class="chat-empty">Đang tải ticket support...</div>');
  renderConversations();

  const data = await SupportAPI.detail(ticketId);
  const ticket = data?.ticket || data;

  if (isHiddenSupportTicketInTradeChat(ticket)) {
    alert('Kênh chat chung seller được quản lý ở trang Hỗ trợ riêng.');
    window.location.href = canManageSupportTickets()
      ? getHomeUrl('/support-workspace/')
      : `${getHomeUrl('/support/')}?view=seller-channel`;
    return;
  }

  state.activeTicket = ticket;
  await hydrateActiveTicketContext(ticketId, ticket);

  const messages = Array.isArray(data?.messages)
    ? data.messages
    : Array.isArray(ticket?.messages)
      ? ticket.messages
      : [];

    state.messages = messages.map((message) => ({
    ...message,
    sender_type: supportMessageSenderType(message, ticket),
  }));
  state.lastMessageKey = messageKey(state.messages);
  renderSupportActivePane({ forceScroll: true });

  const url = new URL(window.location.href);
  url.searchParams.delete('rfq_id');
  url.searchParams.delete('bulk_id');
  url.searchParams.set('support_ticket_id', ticketId);
  window.history.replaceState({}, '', url);
}

function mergeActiveSummary() {
  if (!state.rfqDetail?.rfq) {
    return;
  }

  const summary = state.conversations.find((rfq) => String(rfq.id) === String(state.activeId));
  if (!summary) {
    return;
  }

  state.rfqDetail.rfq = {
    ...summary,
    ...state.rfqDetail.rfq,
    status: summary.status || state.rfqDetail.rfq.status,
  };
  renderRfqHeader();
}

async function refreshConversations(options = {}) {
  if (state.conversationRefreshInFlight) {
    return;
  }

  state.conversationRefreshInFlight = true;

  if (options.showLoading) {
    setConversationList('<div class="chat-empty">Dang tai doan chat...</div>');
  }

  try {
    const [rfqRows, supportTickets, bulkRows] = await Promise.all([
    ChatAPI.conversations().catch(() => []),
    SupportAPI.list({
      include_messages: true,
      all: canManageSupportTickets(),
    }).catch(() => []),
    SupportAPI.listBulkPurchases({
      channel: canManageSupportTickets() ? 'seller' : undefined,
    }).catch(() => []),
  ]);

    const visibleSupportTickets = Array.isArray(supportTickets)
      ? supportTickets.filter((ticket) => (
        !isBulkPurchaseTicket(ticket) &&
        !isHiddenSupportTicketInTradeChat(ticket)
      ))
      : [];

    state.conversations = [
      ...(Array.isArray(bulkRows) ? bulkRows.map(bulkConversationFrom) : []),
      ...visibleSupportTickets.map(supportConversationFrom),
      ...(Array.isArray(rfqRows) ? rfqRows : []),
    ].sort((a, b) => {
      const aTime = Date.parse(a.last_message_at || '');
      const bTime = Date.parse(b.last_message_at || '');
      const safeATime = Number.isFinite(aTime) ? aTime : 0;
      const safeBTime = Number.isFinite(bTime) ? bTime : 0;

      if (safeBTime !== safeATime) {
        return safeBTime - safeATime;
      }

      return String(b.id || '').localeCompare(String(a.id || ''), undefined, { numeric: true });
    });

    mergeActiveSummary();
    renderConversations();

    if (!options.openFirst) {
      return;
    }

    const params = new URLSearchParams(window.location.search);
const requestedSupportTicket = params.get('support_ticket_id');
const requestedRfq = params.get('rfq_id');
const requestedBulk = params.get('bulk_id');

if (requestedSupportTicket) {
  await openSupportTicket(requestedSupportTicket);
  return;
}

if (requestedBulk) {
  await openBulkConversation(requestedBulk);
  return;
}

if (requestedRfq) {
  const exists = state.conversations.some((row) => (
    !row.kind && String(row.id) === String(requestedRfq)
  ));

  if (exists) {
    await openConversation(requestedRfq);
    return;
  }

  const cleanUrl = new URL(window.location.href);
  cleanUrl.searchParams.delete('rfq_id');
  window.history.replaceState({}, '', cleanUrl);
  }

  const first = state.conversations[0];

  if (!first) {
    return;
  }

  if (first.kind === 'support') {
    await openSupportTicket(first.ticket_id);
    return;
  }

  if (first.kind === 'bulk') {
    await openBulkConversation(first.bulk_id);
    return;
  }

  await openConversation(first.id);
  } finally {
    state.conversationRefreshInFlight = false;
  }
}

async function refreshActiveThread(options = {}) {
  if (!state.activeId || state.activeRefreshInFlight) {
    return;
  }

  state.activeRefreshInFlight = true;

  try {
    if (state.activeSupportTicketId) {
      const data = await SupportAPI.detail(state.activeSupportTicketId).catch(() => null);
      const ticket = data?.ticket || null;

      if (ticket) {
        state.activeTicket = ticket;
        if (!state.activeTicketContext || options.full || options.forcePanel) {
          await hydrateActiveTicketContext(state.activeSupportTicketId, ticket);
        }
        const messages = Array.isArray(data?.messages)
          ? data.messages
          : Array.isArray(ticket?.messages)
            ? ticket.messages
            : [];

            state.messages = messages.map((message) => ({
            ...message,
          sender_type: supportMessageSenderType(message, ticket),
        }));
        state.lastMessageKey = messageKey(state.messages);

        if (!isEditingDealForm() || options.forcePanel) {
          renderSupportActivePane({ forceScroll: Boolean(options.forceScroll) });
        } else {
          renderMessages({ forceScroll: Boolean(options.forceScroll) });
          renderConversations();
        }
      }

      return;
    }

    if (state.activeBulkId) {
      const fresh = await SupportAPI.listBulkPurchases({
        channel: preferredBulkChannel(state.activeBulk),
      }).catch(() => []);
      const request = (Array.isArray(fresh) ? fresh : []).find((item) => String(item.id) === String(state.activeBulkId));

      if (request) {
        state.activeBulk = request;
        state.messages = Array.isArray(request.messages) ? request.messages : [];
        state.lastMessageKey = messageKey(state.messages);
        if (!isEditingDealForm() || options.forcePanel) {
          renderBulkActivePane({ forceScroll: Boolean(options.forceScroll) });
        } else {
          renderMessages({ forceScroll: Boolean(options.forceScroll) });
          renderConversations();
        }
      }

      return;
    }

    const messages = await ChatAPI.messages(state.activeId).catch(() => state.messages);
    const nextKey = messageKey(messages);

    if (nextKey !== state.lastMessageKey || options.forceMessages) {
      state.messages = messages;
      state.lastMessageKey = nextKey;
      renderMessages({ forceScroll: Boolean(options.forceScroll) });
    }

    if (options.full) {
      state.quotations = await QuotationAPI.byRfq(state.activeId).catch(() => state.quotations);
      await loadContractAndOrder();

      if (!isEditingDealForm() || options.forcePanel) {
        renderDealPanel();
      }
    }
  } finally {
    state.activeRefreshInFlight = false;
  }
}

function realtimePayloadRfqId(payload = {}) {
  return payload.rfq_id
    || payload.rfqId
    || payload.payload?.rfq_id
    || payload.data?.rfq_id
    || payload.message?.rfq_id
    || null;
}

function realtimePayloadSupportTicketId(payload = {}) {
  return payload.support_ticket_id
    || payload.supportTicketId
    || payload.payload?.support_ticket_id
    || payload.data?.support_ticket_id
    || null;
}

async function refreshFromRealtime(payload = {}) {
  const rfqId = realtimePayloadRfqId(payload);
  const bulkId = payload.bulk_id || payload.bulkId || payload.data?.bulk_id || null;
  const supportTicketId = realtimePayloadSupportTicketId(payload);
  const isActive = (!rfqId && !bulkId && !supportTicketId)
    || String(rfqId) === String(state.activeId)
    || (bulkId && String(bulkId) === String(state.activeBulkId))
    || (supportTicketId && String(supportTicketId) === String(state.activeSupportTicketId));

  await refreshConversations().catch(() => null);

  if (state.activeId && isActive) {
    await refreshActiveThread({
      full: true,
      forceMessages: true,
      forcePanel: !isEditingDealForm(),
    }).catch(() => null);
  }
}

function notifyChatSocket(payload = {}) {
  state.chatSocket?.notify?.({
    rfq_id: state.activeId,
    ...payload,
  });
}

function startRealtimePolling() {
  state.messagePoll?.stop?.();
  state.conversationPoll?.stop?.();

  state.messagePoll = createRealtimeLoop({
    interval: 3000,
    maxInterval: 15000,
    run: () => refreshActiveThread({ full: false }),
    immediate: false,
    canRun: () => Boolean(state.activeId) && !isEditingDealForm(),
  });

    state.conversationPoll = createRealtimeLoop({
    interval: 4000,
    maxInterval: 12000,
    eventName: 'b2b:chat:changed',
    run: async () => {
      await refreshConversations().catch(() => null);
      await refreshActiveThread({
        full: true,
        forceMessages: true,
        forcePanel: true,
      });
    },
    immediate: false,
    canRun: () => Boolean(state.activeId) && !isEditingDealForm(),
  });
  state.messagePoll.start();
  state.conversationPoll.start();
}

function startChatRealtime() {
  state.messagePoll?.stop?.();
  state.conversationPoll?.stop?.();
  state.chatSocket?.stop?.();

  if (!getChatWebSocketUrl()) {
    startRealtimePolling();
    return;
  }

  state.chatSocket = createChatWebSocket({
    getActiveId: () => state.activeId,
    onEvent: refreshFromRealtime,
  });

  if (!state.chatSocket.start()) {
    startRealtimePolling();
    return;
  }

  state.messagePoll = createRealtimeLoop({
    interval: 10000,
    maxInterval: 30000,
    run: () => refreshActiveThread({ full: false }),
    immediate: false,
    canRun: () => Boolean(state.activeId) && !isEditingDealForm(),
  });

    state.conversationPoll = createRealtimeLoop({
    interval: 4000,
    maxInterval: 12000,
    eventName: 'b2b:chat:changed',
    run: async () => {
      await refreshConversations().catch(() => null);
      await refreshActiveThread({
        full: true,
        forceMessages: true,
        forcePanel: true,
      });
    },
    immediate: false,
    canRun: () => Boolean(state.activeId) && !isEditingDealForm(),
  });

  state.messagePoll.start();
  state.conversationPoll.start();
}

async function loadConversations() {
  return refreshConversations({
    showLoading: true,
    openFirst: true,
  });

  setConversationList('<div class="chat-empty">Đang tải đoạn chat...</div>');
  state.conversations = await ChatAPI.conversations();
  state.conversations = Array.isArray(state.conversations) ? state.conversations : [];
  renderConversations();

  const requested = new URLSearchParams(window.location.search).get('rfq_id');
  const firstId = requested || state.conversations[0]?.id;

  if (firstId) {
    await openConversation(firstId);
  }
}

async function handleMessageSubmit(event) {
  event.preventDefault();

  const input = qs('chatMessageInput');
  const message = input?.value.trim();

  if (!message || !state.activeId) return;

  if (state.activeSupportTicketId) {
    await SupportAPI.sendMessage({
      ticket_id: state.activeSupportTicketId,
      sender_id: wpUserId(),
      message,
    });
  } else if (state.activeBulkId) {
    const role = activeRole();

    if (role === 'buyer' && state.activeBulk?.status === 'published') {
      alert('Buyer không được gửi tin vào kênh RFQ chung của seller.');
      return;
    }

    await SupportAPI.sendBulkMessage({
      bulk_id: state.activeBulkId,
      channel: preferredBulkChannel(state.activeBulk),
      message,
    });
  } else {
    await sendTradeMessage(message);
  }

  input.value = '';
  await refreshActiveThread({ full: true, forceScroll: true, forceMessages: true, forcePanel: true });
  await refreshConversations().catch(() => null);
  notifyChatSocket({ type: 'chat.message' });
  emitRealtimeEvent('b2b:chat:changed', {
    rfq_id: (state.activeSupportTicketId || state.activeBulkId) ? null : state.activeId,
    bulk_id: state.activeBulkId || null,
    support_ticket_id: state.activeSupportTicketId || null,
    type: 'message',
  });
}

async function handleFormSubmit(event) {
  const form = event.target.closest('[data-chat-form]');
  if (!form) return;

  event.preventDefault();

  if (!validateBulkDeadline(form)) {
    return;
  }

  state.formSubmitInFlight = true;

  try {
    if (form.dataset.chatForm === 'bulk-product') {
      const formData = new FormData(form);
      await SupportAPI.submitBulkProductForm({
        bulk_id: form.dataset.bulkId,
        ...Object.fromEntries(formData.entries()),
      });
    }

    if (form.dataset.chatForm === 'bulk-join') {
    const formData = new FormData(form);
    const payload = {
      bulk_id: form.dataset.bulkId,
      ...Object.fromEntries(formData.entries()),
    };

    const unitPriceInput = form.elements.unit_price;
    const unitPrice = Number(payload.unit_price || 0);

    if (!String(payload.unit_price || '').trim()) {
      alert('Vui lòng nhập đơn giá / xe trước khi tham gia RFQ.');
      unitPriceInput?.focus();
      return;
    }

    if (!Number.isFinite(unitPrice) || unitPrice <= 0) {
      alert('Đơn giá / xe phải lớn hơn 0.');
      unitPriceInput?.focus();
      return;
    }

    const response = await SupportAPI.joinBulkRFQ(payload);

    if (response?.requires_quantity_confirmation) {
      const ok = confirm(
        `${response.message}\n\n` +
        `Số lượng bạn nhập: ${response.original_quantity}\n` +
        `Số lượng còn thiếu: ${response.adjusted_quantity}\n\n` +
        `Bấm OK để đồng ý tham gia với số lượng ${response.adjusted_quantity}.\n` +
        `Bấm Hủy để không tham gia.`
      );

      if (!ok) {
        return;
      }

      await SupportAPI.joinBulkRFQ({
        ...payload,
        available_quantity: response.adjusted_quantity,
        quantity: response.adjusted_quantity,
        confirm_adjusted_quantity: true,
      });
    }
  }

    if (form.dataset.chatForm === 'quote') {
      const quotationId = form.dataset.quotationId;
      const items = Array.from(form.querySelectorAll('[data-quote-line]')).map((line) => ({
        product_id: Number(line.dataset.quoteProduct),
        quantity: Number(line.querySelector('[data-quote-qty-input]')?.value || 1),
        unit_price: Number(line.querySelector('[data-quote-price-input]')?.value || 0),
        discount_percent: Number(line.querySelector('[data-quote-discount-input]')?.value || 0),
      }));

      const payload = {
        rfq_id: state.activeId,
        seller_company_id: companyId(),
        items,
      };

      if (quotationId) {
        await QuotationAPI.update(quotationId, payload);
      } else {
        await QuotationAPI.submit(payload);
      }

      await sendTradeMessage(quotationId ? 'Seller đã cập nhật báo giá.' : 'Seller đã gửi báo giá.', 'seller').catch(() => null);
      await refreshActiveThread({
      full: true,
      forceMessages: true,
      forcePanel: true,
    });

    notifyChatSocket({
      type: 'quotation_changed',
      rfq_id: state.activeId,
    });

    emitRealtimeEvent('b2b:chat:changed', {
      rfq_id: state.activeId,
      type: 'quotation_changed',
    });
    }

    if (form.dataset.chatForm === 'review') {
      await ReviewAPI.create({
        order_id: Number(form.dataset.orderId),
        rating: Number(form.elements.rating.value),
        comment: form.elements.comment.value,
      });
      emitRealtimeEvent('b2b:reviews:changed', { order_id: Number(form.dataset.orderId) });
      emitRealtimeEvent('b2b:notifications:changed', { order_id: Number(form.dataset.orderId), type: 'review_created' });
      alert('Đã gửi đánh giá.');
    }

    state.formDirty = false;
    closeBulkFormModal();

    if (state.activeBulkId) {
      await openBulkConversation(state.activeBulkId);
      emitRealtimeEvent('b2b:chat:changed', { bulk_id: state.activeBulkId, type: form.dataset.chatForm });
      return;
    }

    if (form.dataset.chatForm === 'quote') {
      return;
    }

    await openConversation(state.activeId);
    notifyChatSocket({ type: form.dataset.chatForm });
    emitRealtimeEvent('b2b:chat:changed', { rfq_id: state.activeId, type: form.dataset.chatForm });
  } finally {
    state.formSubmitInFlight = false;
  }
}

const {
  openSignatureModal,
  openContractViewModal,
} = createSignatureModalController({
  state,
  qs,
  ContractAPI,
  companyId,
  wpUserId,
  currentUserName,
  contractA4Document,
  handleChatError,
  openConversation: (...args) => openConversation(...args),
  notifyChatSocket: (...args) => notifyChatSocket(...args),
  emitRealtimeEvent,
});

const {
  buildContractSupportContext,
  buildOrderSupportContext,
  handleSupportRequestModalClick,
  handleSupportRequestSubmit,
  openSupportRequestModal,
} = createSupportRequestModal({
  state,
  qs,
  wpUserId,
  companyId,
  currentUserName,
  formatCurrency,
  statusLabel,
  buildSupportMetaBlock,
  SupportAPI,
  emitRealtimeEvent,
  openSupportTicket,
});

async function handleActionClick(event) {
  const button = event.target.closest('[data-chat-action]');
  if (!button) return;

  const action = button.dataset.chatAction;

  if (action === 'open-rfq') {
    await openConversation(button.dataset.rfqId);
    return;
  }

  if (action === 'support-cancel-contract') {
    const contractId = Number(button.dataset.contractId || 0);

    if (!canManageSupportTickets()) {
      alert('Chỉ support/admin mới được hủy hợp đồng từ ticket.');
      return;
    }

    if (!contractId) {
      alert('Không tìm thấy Contract ID trong ticket này.');
      return;
    }

    const ok = confirm(`Bạn chắc chắn muốn hủy hợp đồng #${contractId}?`);

    if (!ok) return;

    await ContractAPI.cancel(contractId, `support_cancel_from_ticket_${state.activeSupportTicketId || ''}`);
    await SupportAPI.sendMessage({
      ticket_id: state.activeSupportTicketId,
      sender_id: wpUserId(),
      message: `Support/Admin đã hủy hợp đồng #${contractId}.`,
    }).catch(() => null);
    await openSupportTicket(state.activeSupportTicketId);
    emitRealtimeEvent('b2b:support:changed', { ticket_id: state.activeSupportTicketId, contract_id: contractId, type: 'contract_cancelled' });
    return;
  }

  if (action === 'support-cancel-order') {
    const orderId = Number(button.dataset.orderId || 0);

    if (!canManageSupportTickets()) {
      alert('Chỉ support/admin mới được hủy order từ ticket.');
      return;
    }

    if (!orderId) {
      alert('Không tìm thấy Order ID trong ticket này.');
      return;
    }

    const ok = confirm(`Bạn chắc chắn muốn hủy order #${orderId}?`);

    if (!ok) return;

    await OrderAPI.cancel(orderId, `support_cancel_from_ticket_${state.activeSupportTicketId || ''}`);
    await SupportAPI.sendMessage({
      ticket_id: state.activeSupportTicketId,
      sender_id: wpUserId(),
      message: `Support/Admin đã hủy order #${orderId}.`,
    }).catch(() => null);
    await openSupportTicket(state.activeSupportTicketId);
    emitRealtimeEvent('b2b:support:changed', { ticket_id: state.activeSupportTicketId, order_id: orderId, type: 'order_cancelled' });
    return;
  }

  if (action === 'bulk-open-form') {
    openBulkFormModal(button.dataset.bulkForm || 'buyer');
    return;
  }

  if (action === 'bulk-request-form') {
    await SupportAPI.requestBulkForm(button.dataset.bulkId);
  }

  if (action === 'bulk-send-rfq') {
    await SupportAPI.sendBulkRFQ(button.dataset.bulkId);
  }

  if (action === 'bulk-send-buyer') {
    await SupportAPI.sendBulkToBuyer(button.dataset.bulkId);
  }

  if (action === 'bulk-accept') {
    await SupportAPI.acceptBulkQuotation(button.dataset.bulkId);
  }

  if (action.startsWith('bulk-')) {
    await openBulkConversation(button.dataset.bulkId);
    await refreshConversations().catch(() => null);
    emitRealtimeEvent('b2b:chat:changed', { bulk_id: button.dataset.bulkId, type: action });
    return;
  }

  if (action === 'accept-quote') await QuotationAPI.accept(button.dataset.quotationId);
  if (action === 'reject-quote') await QuotationAPI.reject(button.dataset.quotationId);
  if (action === 'create-contract') await ContractAPI.createFromQuotation(button.dataset.quotationId);
  if (action === 'view-contract') {
    await openContractViewModal(button.dataset.contractId);
    return;
  }
  if (action === 'sign-contract') {
    await openSignatureModal(button.dataset.contractId, button.dataset.party);
    return;
  }
  if (action === 'support-contract') {
  const contractId = button.dataset.contractId;
  const context = buildContractSupportContext(contractId);

    openSupportRequestModal('contract', context, {
      contract_id: contractId,
    });

    return;
  }
  if (action === 'create-order') await OrderAPI.createFromContract(button.dataset.contractId);
  if (action === 'pay-order') {
    const orderId = button.dataset.orderId;
    let paymentId = button.dataset.paymentId;

    if (!paymentId) {
      const created = await PaymentAPI.create(orderId, 'wallet');
      paymentId = created.payment_id;
    }

    const paidPayment = await PaymentAPI.pay(paymentId, `wallet-${orderId}-${Date.now()}`);

    const invoice = paidPayment?.invoice;

    if (invoice?.invoice_file) {
      const openInvoice = window.confirm(
        `Thanh toán escrow thành công.\n\nHóa đơn: ${invoice.invoice_number}\nHệ thống đã gửi hóa đơn về email người thanh toán.\n\nBạn có muốn mở hóa đơn điện tử ngay không?`
      );

      if (openInvoice) {
        window.open(invoice.invoice_file, '_blank', 'noopener,noreferrer');
      }
    } else {
      window.alert('Thanh toán escrow thành công. Hệ thống đang xử lý hóa đơn điện tử.');
    }

    notifyChatSocket({ type: 'payment_paid', order_id: orderId });
    emitRealtimeEvent('b2b:payment:changed', { order_id: Number(orderId) });
  }
  if (action === 'deliver-order') {
  await OrderAPI.markDelivering(button.dataset.orderId);
}

if (action === 'complete-order') {
  await OrderAPI.complete(button.dataset.orderId);
  alert('Đã xác nhận nhận hàng. Đơn đang chờ Admin duyệt giải ngân cho seller.');
}

if (action === 'admin-release-payment') {
  const paymentId = button.dataset.paymentId;

  if (!paymentId) {
    alert('Không tìm thấy Payment ID để giải ngân.');
    return;
  }

  const ok = confirm('Bạn chắc chắn muốn duyệt giải ngân escrow cho seller?');

  if (!ok) return;

  await PaymentAPI.release(paymentId, false);
  alert('Đã duyệt giải ngân cho seller.');
}
  if (action === 'support-ticket') {
  const orderId = button.dataset.orderId;
  const context = buildOrderSupportContext(orderId);

  openSupportRequestModal('order', context, {
    order_id: orderId,
  });

  return;
}

  await openConversation(state.activeId);
  notifyChatSocket({ type: action });
  emitRealtimeEvent('b2b:chat:changed', { rfq_id: state.activeId, type: action });
  emitRealtimeEvent('b2b:wallet:changed', { source: 'chat_action', action });
}

async function initChatPage() {
  if (!qs('chatConversationList')) return;

  try {
    state.profile = await UserAPI.getProfile();
    await hydrateAccessContext().catch(() => null);
  } catch (error) {
    qs('chatConversationList').innerHTML = '<div class="chat-empty">Bạn cần đăng nhập để xem chat.</div>';
    qs('chatEmptyState').innerHTML = `<h2>Chưa đăng nhập</h2><p><a href="${getHomeUrl('/login')}">Đăng nhập</a> để xem tin nhắn giao dịch.</p>`;
    return;
  }

  qs('chatRefreshBtn')?.addEventListener('click', () => {
    state.formDirty = false;
    loadConversations().catch((error) => alert(error.message || 'Không thể tải lại danh sách chat.'));
  });
  qs('chatSearchInput')?.addEventListener('input', renderConversations);
  document.addEventListener('submit', handleSupportRequestSubmit);
  document.addEventListener('click', handleSupportRequestModalClick);
  qs('chatConversationList')?.addEventListener('click', (event) => {
    const bulkRow = event.target.closest('[data-chat-bulk]');
    if (bulkRow) {
      state.formDirty = false;
      openBulkConversation(bulkRow.dataset.chatBulk).catch((error) => alert(error.message || 'Không thể mở bulk RFQ.'));
      return;
    }

    const supportRow = event.target.closest('[data-chat-support]');
    if (supportRow) {
      state.formDirty = false;
      openSupportTicket(supportRow.dataset.chatSupport).catch((error) => alert(error.message || 'Không thể mở ticket support.'));
      return;
    }

    const row = event.target.closest('[data-chat-rfq]');
    if (row) {
      state.formDirty = false;
      openConversation(row.dataset.chatRfq).catch((error) => alert(error.message || 'Không thể mở đoạn chat.'));
    }
  });
  qs('chatDealPanel')?.addEventListener('keydown', (event) => {
    if (!['Enter', ' '].includes(event.key)) {
      return;
    }

    const preview = event.target.closest('.chat-contract-preview[data-chat-action="view-contract"]');
    if (!preview) {
      return;
    }

    event.preventDefault();
    openContractViewModal(preview.dataset.contractId).catch((error) => handleChatError(error, 'Không thể mở hợp đồng.'));
  });
  qs('chatMessageForm')?.addEventListener('submit', (event) => {
    handleMessageSubmit(event).catch((error) => alert(error.message || 'Không thể gửi tin nhắn.'));
  });
  qs('chatDealPanel')?.addEventListener('submit', (event) => {
    handleFormSubmit(event).catch((error) => alert(error.message || 'Không thể xử lý biểu mẫu giao dịch.'));
  });
  document.addEventListener('submit', (event) => {
    if (!event.target.closest('#chatBulkFormModal')) {
      return;
    }

    handleFormSubmit(event).catch((error) => alert(error.message || 'Không thể xử lý biểu mẫu giao dịch.'));
  });
  qs('chatDealPanel')?.addEventListener('input', (event) => {
    if (event.target.closest('[data-chat-form]')) {
      state.formDirty = true;
    }

    handleQuoteCalculatorInput(event);
  });
  qs('chatDealPanel')?.addEventListener('click', (event) => {
    handleActionClick(event).catch((error) => handleChatError(error, 'Không thể xử lý thao tác giao dịch.'));
  });
  document.addEventListener('input', (event) => {
    if (event.target.closest('#chatBulkFormModal [data-chat-form]')) {
      state.formDirty = true;
    }
  });
  document.addEventListener('change', handleBulkDeadlineChange);
  document.addEventListener('click', (event) => {
    if (event.target.closest('[data-bulk-modal-close]') || event.target.id === 'chatBulkFormModal') {
      closeBulkFormModal();
    }
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !qs('chatBulkFormModal')?.classList.contains('hidden')) {
      closeBulkFormModal();
      return;
    }

    if (event.key === 'Escape' && !qs('chatSignatureModal')?.classList.contains('hidden')) {
      closeSignatureModal();
    }
  });

  let initialLoadOk = false;

  await loadConversations().then(() => {
    initialLoadOk = true;
  }).catch((error) => {
    setConversationList(`<div class="chat-empty">${escapeHtml(error.message || 'Không thể tải danh sách chat.')}</div>`);
  });

  if (initialLoadOk) {
    startChatRealtime();
  }

  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState !== 'visible') {
      return;
    }

    refreshConversations().catch(() => null);
    refreshActiveThread({ full: true }).catch(() => null);
  });
}

function bootChatPage() {
  if (window.__B2B_CHAT_PAGE_BOOTED__) {
    return;
  }

  window.__B2B_CHAT_PAGE_BOOTED__ = true;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initChatPage);
    return;
  }

  initChatPage();
}

bootChatPage();
