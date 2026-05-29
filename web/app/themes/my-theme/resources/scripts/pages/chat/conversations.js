export function bulkConversationFrom(request) {
  const messages = Array.isArray(request.messages) ? request.messages : [];
  const last = messages[messages.length - 1];
  const productName = request.product_info?.product_name || 'Thu mua số lượng lớn';

  return {
    id: `bulk:${request.id}`,
    kind: 'bulk',
    bulk_id: request.id,
    participant_role: request.viewer_role || 'buyer',
    buyer_company_name: request.buyer_company_name || '',
    counterparty_name: request.viewer_role === 'support'
      ? (request.buyer_company_name || 'Buyer thu mua số lượng lớn')
      : 'Support - Thu mua số lượng lớn',
    seller_company_names: 'Kênh seller chung',
    product_names: productName,
    message: request.initial_message || '',
    last_message: last?.message || request.broadcast_message || request.initial_message || 'Thu mua số lượng lớn',
    last_message_at: last?.created_at || request.updated_at || request.created_at || '',
    status: request.status || 'started',
    bulk: request,
  };
}

const HIDDEN_SUPPORT_TICKET_TYPES = new Set([
  'bulk_seller_channel',
  'seller_broadcast_channel',
]);

export function isHiddenSupportTicketType(type) {
  return HIDDEN_SUPPORT_TICKET_TYPES.has(String(type || '').toLowerCase());
}

export function isHiddenSupportTicketInTradeChat(ticket) {
  return isHiddenSupportTicketType(ticket?.type);
}

export function isBulkPurchaseTicket(ticket) {
  const type = String(ticket?.type || '').toLowerCase();
  const message = String(ticket?.message || ticket?.initial_message || '').toLowerCase();

  return (
    type.includes('bulk') ||
    type === 'bulk_purchase' ||
    type === 'bulk' ||
    message.includes('thu mua số lượng lớn') ||
    message.includes('bulk')
  );
}

export function supportConversationFrom(ticket) {
  const messages = Array.isArray(ticket.messages) ? ticket.messages : [];
  const last = messages[messages.length - 1];
  const supportName = ticket.support_user_name || '';

  return {
    id: `support:${ticket.id}`,
    kind: 'support',
    ticket_id: ticket.id,
    title: ticket.order_id ? `Order #${ticket.order_id}` : `Ticket #${ticket.id}`,
    order_id: ticket.order_id || null,
    message: String(ticket.message || ticket.initial_message || ''),
    last_message: last?.message || String(ticket.message || ticket.initial_message || ''),
    last_message_at: last?.created_at || ticket.updated_at || ticket.created_at || '',
    status: ticket.status || 'open',
    support_user_name: supportName,
    counterparty_name: supportName,
    user_id: ticket.user_id,
    ticket,
  };
}

