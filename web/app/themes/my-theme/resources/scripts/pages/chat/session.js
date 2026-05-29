import { BULK_SELLER_CHANNEL_STATUSES } from './state.js';

export function createChatSession({
  state,
  qs,
  getHomeUrl,
  getProfileCompanyId,
  getProfileWpUserId,
}) {
  function companyId() {
    return getProfileCompanyId(state.profile);
  }

  function wpUserId() {
    return getProfileWpUserId(state.profile);
  }

  function roleForRfq(rfq = state.rfqDetail?.rfq) {
    if (!rfq) return 'buyer';
    if (['buyer', 'seller'].includes(rfq.participant_role)) {
      return rfq.participant_role;
    }

    return Number(rfq.buyer_company_id) === companyId() ? 'buyer' : 'seller';
  }

  function supportMessageSenderType(message, ticket = state.activeTicket) {
    const ticketOwnerId =
      ticket?.user_id ||
      ticket?.created_by ||
      ticket?.buyer_user_id ||
      ticket?.wp_user_id ||
      0;

    return String(message.sender_id) === String(ticketOwnerId)
      ? 'buyer'
      : 'support';
  }

  function activeRole() {
    if (state.activeSupportTicketId) {
      return String(state.activeTicket?.user_id) === String(wpUserId()) ? 'buyer' : 'support';
    }

    if (state.activeBulk) {
      return state.activeBulk.viewer_role || 'buyer';
    }

    return roleForRfq();
  }

  function preferredBulkChannel(request = state.activeBulk) {
    const role = request?.viewer_role || activeRole();

    if (role === 'seller') {
      return 'seller';
    }

    if (role === 'support') {
      const status = String(request?.status || '').toLowerCase();

      if (request?.seller_ticket_id && BULK_SELLER_CHANNEL_STATUSES.has(status)) {
        return 'seller';
      }
    }

    return 'buyer';
  }

  function isBulkActive() {
    return Boolean(state.activeBulkId);
  }

  function isEditingDealForm() {
    const panel = qs('chatDealPanel');
    const active = document.activeElement;
    const modalOpen = Boolean(document.querySelector('#chatBulkFormModal:not(.hidden)'));

    if (!panel || !active) {
      return state.formDirty || state.formSubmitInFlight || modalOpen;
    }

    return state.formDirty
      || state.formSubmitInFlight
      || modalOpen
      || Boolean(active.closest?.('#chatDealPanel form'));
  }

  function activeQuotation() {
    return state.quotations.find((item) => (item.quotation || item).status === 'accepted') || null;
  }

  function ownQuotation() {
    return state.quotations.find((item) => Number((item.quotation || item).seller_company_id) === companyId()) || null;
  }

  function currentPartySigned(party) {
    return Boolean(state.contractDetail?.contract_data?.signatures?.[party]?.signed);
  }

  function bulkById(bulkId) {
    const id = String(bulkId);

    return state.conversations.find((row) => row.kind === 'bulk' && String(row.bulk_id) === id)?.bulk || null;
  }

  function handleChatError(error, fallback) {
    const message = error?.message || fallback;

    if (String(message).includes('INSUFFICIENT_BALANCE') || String(message).includes('Số dư không đủ')) {
      const amount = state.orderDetail?.order?.total_amount || '';
      alert('Số dư không đủ để thanh toán order. Bạn sẽ được chuyển sang trang nạp tiền.');
      window.location.href = `${getHomeUrl('/wallet')}?amount=${encodeURIComponent(amount)}`;
      return;
    }

    alert(message || fallback);
  }

  return {
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
  };
}
