import { SupportAPI } from '../../api/support.js';
import { getHomeUrl } from '../../api/http.js';
import { PaymentAPI } from '../../api/payment.js';
import { createRealtimeLoop, emitRealtimeEvent } from '../../utils/realtime.js';

import { createAdminApi } from '../../api/admin.js';
import { createSupportAccess } from './access.js';
import { createSupportRenderers } from './renderers.js';
import { createInitialSupportState } from './state.js';
import { createSupportAgentLoader } from './agents.js';
import { createInternalSupportChat } from './internal-chat.js';
import { buildSupportContextByReference, getSupportReferenceValue, normalizeSupportType } from './context.js';
import {
  buildSupportMetaBlock,
  bulkStatusLabel,
  escapeHtml,
  formatCurrency,
  formatDate,
  normalizeMojibakeText,
  normalizeSupportChatMessages,
  supportCurrentUserId,
  supportCurrentUserName,
  ticketStatusLabel,
} from './helpers.js';

const state = createInitialSupportState();

const adminApi = createAdminApi();

let chatInterval = null;

const {
  hydrateAccessContext,
  canManageTickets,
  hasSellerRole,
  hasBuyerRole,
  canOpenPublicSellerChannel,
  canAccessSellerChannel,
  getSupportViewMode,
  isHelpMode,
  isSellerChannelMode,
  isInternalSupportView,
  isPublicSellerChannelView,
  syncSupportNavigationLinks,
  applySupportVisibility,
} = createSupportAccess({
  state,
  getHomeUrl,
});

function isEditingSupportForm() {
  return state.formDirty || Boolean(document.activeElement?.closest?.('#supportPage form'));
}

function setScopedMessage(id, message, type = 'info') {
  const node = document.getElementById(id);

  if (!node) return;

  node.textContent = message || '';
  node.dataset.type = type;
}

function setMessage(message, type = 'info') {
  setScopedMessage('supportMessage', message, type);
}

function setBulkMessage(message, type = 'info') {
  setScopedMessage('bulkPurchaseMessage', message, type);
}

const {
  renderTickets,
  renderPendingPayouts,
  renderSellerChannel,
  renderBulkRequests,
} = createSupportRenderers({
  state,
  getHomeUrl,
  canManageTickets,
});

async function loadTickets(options = {}) {
  if (state.loading) return;

  state.loading = true;

  if (!options.silent) {
    renderTickets();
  }

  try {
    const rows = await SupportAPI.list({ all: canManageTickets() });
    const hiddenTypes = new Set(['bulk_seller_channel', 'seller_broadcast_channel']);

    state.tickets = (Array.isArray(rows) ? rows : []).filter((ticket) => (
      !hiddenTypes.has(String(ticket?.type || '').toLowerCase())
    ));

    setMessage('');
  } catch (error) {
    state.tickets = [];
    setMessage(error.message || 'Không tải được danh sách support.', 'error');
  } finally {
    state.loading = false;
    renderTickets();
  }
}

async function loadPendingPayouts(options = {}) {
  if (!document.getElementById('supportPayoutList')) return;
  if (state.payoutLoading) return;

  state.payoutLoading = true;

  if (!options.silent) {
    renderPendingPayouts();
  }

  try {
    const rows = await PaymentAPI.pendingReleases(200);
    state.pendingPayouts = Array.isArray(rows) ? rows : [];
  } catch (error) {
    state.pendingPayouts = [];
  } finally {
    state.payoutLoading = false;
    renderPendingPayouts();
  }
}

async function loadSellerChannel(options = {}) {
  const hasContainers = document.querySelectorAll('[data-seller-channel-messages]').length > 0;

  if (!hasContainers || state.sellerChannelLoading || !canAccessSellerChannel()) return;

  state.sellerChannelLoading = true;

  if (!options.silent) {
    renderSellerChannel();
  }

  try {
    const data = await SupportAPI.sellerChannel();
    state.sellerChannel = {
      ticket: data?.ticket || null,
      messages: Array.isArray(data?.messages) ? data.messages : [],
    };
  } catch (error) {
    state.sellerChannel = { ticket: null, messages: [] };
  } finally {
    state.sellerChannelLoading = false;
    renderSellerChannel();
  }
}

const {
  loadSupportAgents,
  renderSupportAgentOptions,
} = createSupportAgentLoader({
  state,
  SupportAPI,
  escapeHtml,
});

async function loadBulkRequests(options = {}) {
  if (!document.getElementById('bulkPurchaseList')) return;

  if (state.bulkLoading) return;

  state.bulkLoading = true;

  if (!options.silent) {
    renderBulkRequests();
  }

  try {
    state.bulkRequests = await SupportAPI.listBulkPurchases();
    setBulkMessage('');
  } catch (error) {
    state.bulkRequests = [];
    setBulkMessage(error.message || 'Không tải được bulk RFQ.', 'error');
  } finally {
    state.bulkLoading = false;
    renderBulkRequests();
  }
}
async function handleCreate(event) {
  event.preventDefault();

  const form = event.currentTarget;
  const messageId = form.dataset.messageId || 'supportMessage';
  const formData = new FormData(form);

  const type = String(formData.get('type') || 'dispute').trim();
  const supportUserId = Number(formData.get('support_user_id') || 0);
  const referenceValue = getSupportReferenceValue(formData);
  const userMessage = String(formData.get('message') || '').trim();

  if (!referenceValue) {
    setScopedMessage(messageId, 'Vui lòng nhập mã RFQ hoặc Bulk từ trang Tin nhắn.', 'error');
    return;
  }

  try {
    setScopedMessage(messageId, 'Đang lấy thông tin phiên giao dịch...', 'info');

    const built = await buildSupportContextByReference(type, referenceValue);

    if (built?.referenceValid === false) {
      setScopedMessage(messageId, built.referenceError || 'Mã RFQ/Bulk không hợp lệ.', 'error');
      return;
    }

    if (Number(built?.rfqId || 0) <= 0 && Number(built?.bulkId || 0) <= 0) {
      setScopedMessage(messageId, 'Mã RFQ/Bulk không thuộc cuộc giao dịch của bạn.', 'error');
      return;
    }

    const payload = {
      type,
      support_reference: referenceValue || '',
      message: [
        buildSupportMetaBlock({
          kind: normalizeSupportType(type),
          rfq_id: Number(built.rfqId || 0) || '',
          bulk_id: Number(built.bulkId || 0) || '',
          support_user_id: supportUserId > 0 ? supportUserId : '',
          support_reference: referenceValue || '',
        }),
        '',
        built.context,
        '',
        'NỘI DUNG NGƯỜI DÙNG GỬI',
        userMessage || '-',
      ].join('\n'),
    };

    if (built.orderId) {
      payload.order_id = built.orderId;
    }

    if (supportUserId > 0) {
      payload.support_user_id = supportUserId;
    }

    if (Number(built.rfqId || 0) > 0) {
      payload.rfq_id = Number(built.rfqId);
    }

    if (Number(built.bulkId || 0) > 0) {
      payload.bulk_id = Number(built.bulkId);
    }

    setScopedMessage(messageId, 'Đang tạo ticket...', 'info');
    await SupportAPI.createTicket(payload);

    form.reset();
    state.formDirty = false;
    setScopedMessage(messageId, 'Đã tạo ticket support.', 'success');

    emitRealtimeEvent('b2b:Hệ thống TMDT B2B Marketplace xin thông báo:changed');

    if (isInternalSupportView()) {
      await loadTickets();
    }
  } catch (error) {
    setScopedMessage(messageId, error.message || 'Không tạo được ticket.', 'error');
  }
}

async function handleReply(event) {
  const form = event.target.closest('[data-support-reply]');

  if (!form) return;

  event.preventDefault();

  const ticketId = form.dataset.supportReply;
  const input = form.querySelector('input[name="message"]');
  const message = input?.value?.trim();

  if (!message) return;

  try {
    setMessage('Đang gửi phản hồi...', 'info');
    await SupportAPI.sendMessage({
      ticket_id: ticketId,
      message,
    });
    input.value = '';
    state.formDirty = false;
    setMessage('Đã gửi phản hồi.', 'success');
    emitRealtimeEvent('b2b:Hệ thống TMDT B2B Marketplace xin thông báo:changed');
    await loadTickets();
  } catch (error) {
    setMessage(error.message || 'Không gửi được phản hồi.', 'error');
  }
}

async function handleClose(event) {
  const button = event.target.closest('[data-support-close]');

  if (!button) return;

  event.preventDefault();

  try {
    setMessage('Đang cập nhật ticket...', 'info');
    await SupportAPI.close(button.dataset.supportClose, button.dataset.status || 'closed');
    setMessage('Đã cập nhật ticket.', 'success');
    emitRealtimeEvent('b2b:Hệ thống TMDT B2B Marketplace xin thông báo:changed');
    await loadTickets();
  } catch (error) {
    setMessage(error.message || 'Không cập nhật được ticket.', 'error');
  }
}

async function handleReleasePayout(event) {
  const button = event.target.closest('[data-support-release-payment]');

  if (!button) return;

  event.preventDefault();

  const paymentId = Number(button.dataset.supportReleasePayment || 0);
  const orderId = Number(button.dataset.orderId || 0);

  if (!paymentId) {
    window.alert('Không tìm thấy payment để giải ngân.');
    return;
  }

  const ok = window.confirm(`Bạn chắc chắn muốn duyệt giải ngân cho payment #${paymentId}?`);

  if (!ok) return;

  button.disabled = true;

  try {
    await PaymentAPI.release(paymentId, false);
    emitRealtimeEvent('b2b:payment:changed', { payment_id: paymentId, order_id: orderId });
    window.alert('Đã duyệt giải ngân thành công.');
    await loadPendingPayouts();
  } catch (error) {
    window.alert(error?.message || 'Không thể duyệt giải ngân.');
  } finally {
    button.disabled = false;
  }
}
async function handleRefundPayment(event) {
  const button = event.target.closest('[data-support-refund-payment]');

  if (!button) return;

  event.preventDefault();

  const paymentId = Number(button.dataset.supportRefundPayment || 0);
  const orderId = Number(button.dataset.orderId || 0);
  const refundAmount = Number(button.dataset.paymentAmount || 0);

  if (!paymentId || refundAmount <= 0) {
    window.alert('Không tìm thấy payment hoặc số tiền cần hoàn.');
    return;
  }

  const reason = window.prompt(
    `Nhập lý do hoàn ${formatCurrency(refundAmount)} cho bên mua của payment #${paymentId}:`,
    'Bên bán vi phạm / Support hoàn tiền cho bên mua'
  );

  if (reason === null) return;

  const finalReason = String(reason || '').trim();

  if (!finalReason) {
    window.alert('Vui lòng nhập lý do hoàn tiền.');
    return;
  }

  const ok = window.confirm(
    `Xác nhận HOÀN TIỀN TOÀN BỘ ${formatCurrency(refundAmount)} về bên mua?\n\nPayment #${paymentId} sẽ không giải ngân cho bên bán.`
  );

  if (!ok) return;

  button.disabled = true;

  try {
    await PaymentAPI.supportSettle({
      payment_id: paymentId,
      order_id: orderId,
      refund_amount: refundAmount,
      reason: finalReason,
    });

    emitRealtimeEvent('b2b:payment:changed', {
      payment_id: paymentId,
      order_id: orderId,
    });

    window.alert('Đã hoàn tiền cho bên mua của order thành công.');
    await loadPendingPayouts();
  } catch (error) {
    window.alert(error?.message || 'Không thể hoàn tiền cho bên mua.');
  } finally {
    button.disabled = false;
  }
}
async function handleSellerChannelSubmit(event) {
  const form = event.target.closest('[data-seller-channel-form]');

  if (!form) return;

  event.preventDefault();

  const textarea = form.querySelector('textarea[name="message"]');
  const message = textarea?.value?.trim() || '';

  if (!message) return;

  const submitBtn = form.querySelector('button[type="submit"]');

  if (submitBtn) {
    submitBtn.disabled = true;
  }

  try {
    await SupportAPI.sendSellerChannelMessage(message);
    textarea.value = '';
    state.formDirty = false;
    emitRealtimeEvent('b2b:Hệ thống TMDT B2B Marketplace xin thông báo:changed');
    emitRealtimeEvent('b2b:seller-channel:changed');
    await loadSellerChannel({ silent: true });
  } catch (error) {
    window.alert(error?.message || 'Không gửi được tin nhắn kênh seller.');
  } finally {
    if (submitBtn) {
      submitBtn.disabled = false;
    }
  }
}

function payloadFromForm(form) {
  const formData = new FormData(form);
  const payload = {};

  formData.forEach((value, key) => {
    payload[key] = value;
  });

  return payload;
}

async function handleBulkSubmit(event) {
  const productForm = event.target.closest('[data-bulk-product-form]');
  const joinForm = event.target.closest('[data-bulk-join-form]');

  if (!productForm && !joinForm) return;

  event.preventDefault();

  try {
    setBulkMessage('Đang xử lý bulk RFQ...', 'info');

    if (productForm) {
      await SupportAPI.submitBulkProductForm({
        bulk_id: productForm.dataset.bulkProductForm,
        ...payloadFromForm(productForm),
      });
      setBulkMessage('Đã gửi form thông tin sản phẩm cho Support.', 'success');
    }

    if (joinForm) {
      await SupportAPI.joinBulkRFQ({
        bulk_id: joinForm.dataset.bulkJoinForm,
        ...payloadFromForm(joinForm),
      });
      setBulkMessage('Đã gửi thông tin tham gia cho Support.', 'success');
    }

    emitRealtimeEvent('b2b:Hệ thống TMDT B2B Marketplace xin thông báo:changed');
    await loadBulkRequests();
    state.formDirty = false;
  } catch (error) {
    setBulkMessage(error.message || 'Không xử lý được bulk RFQ.', 'error');
  }
}

async function handleBulkAction(event) {
  const button = event.target.closest('[data-bulk-action]');

  if (!button) return;

  event.preventDefault();

  const bulkId = button.dataset.bulkId;
  const action = button.dataset.bulkAction;

  try {
    button.disabled = true;
    setBulkMessage('Đang cập nhật bulk RFQ...', 'info');

    if (action === 'request-form') {
      await SupportAPI.requestBulkForm(bulkId);
      setBulkMessage('Đã yêu cầu buyer điền form sản phẩm.', 'success');
    }

    if (action === 'send-rfq') {
      await SupportAPI.sendBulkRFQ(bulkId);
      setBulkMessage('Đã gửi RFQ đến seller.', 'success');
    }

    if (action === 'send-buyer') {
      await SupportAPI.sendBulkToBuyer(bulkId);
      setBulkMessage('Đã gửi tổng hợp báo giá về buyer.', 'success');
    }

    if (action === 'accept') {
      await SupportAPI.acceptBulkQuotation(bulkId);
      setBulkMessage('Đã đồng ý báo giá và tạo hồ sơ hợp đồng.', 'success');
    }

    emitRealtimeEvent('b2b:Hệ thống TMDT B2B Marketplace xin thông báo:changed');
    await loadBulkRequests();
  } catch (error) {
    setBulkMessage(error.message || 'Không cập nhật được bulk RFQ.', 'error');
  } finally {
    button.disabled = false;
  }
}

function focusBulkFromQuery() {
  const bulkId = new URLSearchParams(window.location.search).get('bulk_id');

  if (!bulkId) return;

  window.setTimeout(() => {
    const card = document.querySelector(`[data-bulk-id="${CSS.escape(bulkId)}"]`);
    card?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }, 500);
}

const {
  loadAdminSupportTeam,
  renderAdminSidebarList,
  checkNewMessagesNotifications,
  loadChatMessagesWithAdmin,
  handleSendSupportChat,
  handleAdminSidebarClick,
} = createInternalSupportChat({
  state,
  adminApi,
  escapeHtml,
  normalizeSupportChatMessages,
  supportCurrentUserId,
  supportCurrentUserName,
});

async function initSupportPage() {
  const page = document.getElementById('supportPage');
  if (!page || window.__B2B_SUPPORT_PAGE_BOOTED__) return;

  window.__B2B_SUPPORT_PAGE_BOOTED__ = true;
  await hydrateAccessContext();
  syncSupportNavigationLinks();
  const internal = applySupportVisibility();
  loadSupportAgents().catch(() => null);

  const btnSwitchTickets = document.getElementById('btn-switch-tickets');
  const btnSwitchInternalChat = document.getElementById('btn-switch-internal-chat');
  const btnSwitchPayouts = document.getElementById('btn-switch-payouts');
  const btnSwitchSellerChannel = document.getElementById('btn-switch-seller-channel');
  const ticketsZone = document.getElementById('workspace-tickets-zone');
  const chatZone = document.getElementById('workspace-chat-zone');
  const payoutsZone = document.getElementById('workspace-payout-zone');
  const sellerChannelZone = document.getElementById('workspace-seller-channel-zone');

  const stopChatPolling = () => {
    if (chatInterval) {
      clearInterval(chatInterval);
      chatInterval = null;
    }
  };

  const activateInternalTab = async (tab) => {
    state.currentSubTab = tab;

    btnSwitchTickets?.classList.toggle('active', tab === 'tickets');
    btnSwitchInternalChat?.classList.toggle('active', tab === 'chat');
    btnSwitchPayouts?.classList.toggle('active', tab === 'payouts');
    btnSwitchSellerChannel?.classList.toggle('active', tab === 'seller-channel');

    if (ticketsZone) ticketsZone.style.display = tab === 'tickets' ? 'block' : 'none';
    if (chatZone) chatZone.style.display = tab === 'chat' ? 'flex' : 'none';
    if (payoutsZone) payoutsZone.style.display = tab === 'payouts' ? 'block' : 'none';
    if (sellerChannelZone) sellerChannelZone.style.display = tab === 'seller-channel' ? 'block' : 'none';

    if (tab !== 'chat') {
      stopChatPolling();
    }

    if (tab === 'chat') {
      await loadAdminSupportTeam();
      stopChatPolling();
      chatInterval = setInterval(async () => {
        if (state.activeAdminId) {
          await loadChatMessagesWithAdmin(state.activeAdminId, true);
        }
        await checkNewMessagesNotifications();
      }, 3000);
    }

    if (tab === 'payouts') {
      await loadPendingPayouts();
    }

    if (tab === 'seller-channel') {
      await loadSellerChannel();
    }
  };

  document.getElementById('supportRefreshBtn')?.addEventListener('click', () => {
    if (internal) {
      loadTickets();

      if (state.currentSubTab === 'payouts') {
        loadPendingPayouts();
      }

      if (state.currentSubTab === 'chat' && state.activeAdminId) {
        loadChatMessagesWithAdmin(state.activeAdminId, false);
      }

      if (state.currentSubTab === 'seller-channel') {
        loadSellerChannel();
      }
    } else if (isPublicSellerChannelView()) {
      loadSellerChannel();
    }

    loadBulkRequests();
  });

  btnSwitchTickets?.addEventListener('click', () => {
    activateInternalTab('tickets');
  });

  btnSwitchInternalChat?.addEventListener('click', async () => {
    await activateInternalTab('chat');
  });

  btnSwitchPayouts?.addEventListener('click', async () => {
    await activateInternalTab('payouts');
  });

  btnSwitchSellerChannel?.addEventListener('click', async () => {
    await activateInternalTab('seller-channel');
  });

  document.getElementById('publicSupportTicketForm')?.addEventListener('submit', (event) => {
    handleCreate(event).catch(() => null);
  });

  document.getElementById('supportTicketList')?.addEventListener('submit', (event) => {
    handleReply(event).catch(() => null);
  });

  document.getElementById('supportTicketList')?.addEventListener('click', (event) => {
    handleClose(event).catch(() => null);
  });

  document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!form || !form.closest) {
      return;
    }

    if (form.closest('[data-bulk-product-form]') || form.closest('[data-bulk-join-form]')) {
      handleBulkSubmit(event).catch(() => null);
    }
  });

  document.addEventListener('click', (event) => {
    if (event.target?.closest?.('[data-bulk-action]')) {
      handleBulkAction(event).catch(() => null);
    }
  });

  document.addEventListener('input', (event) => {
    if (event.target?.closest?.('#supportPage form')) {
      state.formDirty = true;
    }
  });

  document.getElementById('admin-users-list')?.addEventListener('click', handleAdminSidebarClick);

  document.getElementById('support-chat-input-form')?.addEventListener('submit', handleSendSupportChat);
  document.getElementById('supportPayoutList')?.addEventListener('click', (event) => {
  handleReleasePayout(event).catch(() => null);
  handleRefundPayment(event).catch(() => null);
});

  document.querySelectorAll('[data-seller-channel-form]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      handleSellerChannelSubmit(event).catch(() => null);
    });
  });

  const loop = createRealtimeLoop({
    interval: 7000,
    maxInterval: 30000,
    eventName: 'b2b:Hệ thống TMDT B2B Marketplace xin thông báo:changed',
    run: () => Promise.all([
      isInternalSupportView() ? loadTickets({ silent: true }) : Promise.resolve(),
      isInternalSupportView() ? loadPendingPayouts({ silent: true }) : Promise.resolve(),
      (isInternalSupportView() || isPublicSellerChannelView()) ? loadSellerChannel({ silent: true }) : Promise.resolve(),
      loadBulkRequests({ silent: true }),
    ]),
    immediate: false,
    canRun: () => !isEditingSupportForm(),
  });

  Promise.all([
    internal ? loadTickets() : Promise.resolve(),
    internal ? loadPendingPayouts() : Promise.resolve(),
    (internal || isPublicSellerChannelView()) ? loadSellerChannel() : Promise.resolve(),
    loadBulkRequests(),
  ]).finally(() => {
    if (internal) {
      activateInternalTab('tickets').catch(() => null);
    } else if (isPublicSellerChannelView()) {
      state.currentSubTab = 'seller-channel';
    }

    loop.start();
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initSupportPage);
} else {
  initSupportPage();
}





