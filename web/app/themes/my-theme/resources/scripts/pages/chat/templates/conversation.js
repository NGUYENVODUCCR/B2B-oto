import { escapeHtml } from '../../../utils/format.js';
import { normalizeMojibakeText, statusLabel } from './text.js';

export function conversationTitle(rfq, role) {
  if (rfq?.kind === 'support') {
    const supportName = rfq.support_user_name || rfq.counterparty_name || '';

    if (supportName) {
      return `Hỗ trợ - ${supportName}`;
    }

    if (rfq.order_id) {
      return `Hỗ trợ Order #${escapeHtml(rfq.order_id)}`;
    }

    return 'Hỗ trợ hệ thống';
  }

  if (rfq?.kind === 'bulk') {
    if (role === 'support') {
      return rfq.buyer_company_name || 'Buyer thu mua số lượng lớn';
    }

    if (role === 'seller') {
      return 'Kênh RFQ chung với Support';
    }

    return 'Support - Thu mua số lượng lớn';
  }

  if (rfq?.counterparty_name) {
    return rfq.counterparty_name;
  }

  if (role === 'buyer') {
    return rfq.seller_company_names || 'Người bán';
  }

  return rfq.buyer_company_name || 'Người mua';
}

export function conversationRow(rfq, role, activeId) {
  const activeClass = String(activeId || '') === String(rfq.id) ? ' is-active' : '';
  const title = normalizeMojibakeText(conversationTitle(rfq, role || rfq.participant_role));
  const products = normalizeMojibakeText(
    rfq.product_names || (rfq.kind === 'support' ? (rfq.title || 'Ticket support') : 'Sản phẩm trong RFQ')
  );
  const lastMessage = normalizeMojibakeText(rfq.last_message || rfq.message || 'Chưa có tin nhắn');
  const isBulk = rfq.kind === 'bulk';
  const isSupport = rfq.kind === 'support';
  const dataAttr = isBulk
    ? `data-chat-bulk="${escapeHtml(rfq.bulk_id)}"`
    : isSupport
      ? `data-chat-support="${escapeHtml(rfq.ticket_id)}"`
      : `data-chat-rfq="${escapeHtml(rfq.id)}"`;
  const footerLabel = isBulk
    ? `Bulk #${escapeHtml(rfq.bulk_id)}`
    : isSupport
      ? `Ticket #${escapeHtml(rfq.ticket_id)}`
      : `RFQ #${escapeHtml(rfq.id)}`;

  return `
    <button type="button" class="chat-conversation${activeClass}${isBulk ? ' is-bulk' : ''}${isSupport ? ' is-support' : ''}" ${dataAttr}>
      <span class="chat-conversation-title">${escapeHtml(title)}</span>
      <span class="chat-conversation-product">${escapeHtml(products)}</span>
      <span class="chat-conversation-preview">${escapeHtml(lastMessage)}</span>
      <span class="chat-conversation-footer">
        <span>${escapeHtml(statusLabel(rfq.status))}</span>
        <span>${footerLabel}</span>
      </span>
    </button>
  `;
}

export function messageBubble(message, currentRole) {
  const mine = message.sender_type === currentRole;
  const cssClass = mine ? ' is-mine' : '';

  const senderLabel = message.sender_type === 'support'
    ? 'Người hỗ trợ'
    : statusLabel(message.sender_type || 'buyer');

  let messageText = normalizeMojibakeText(String(message.message || ''));

messageText = messageText
  .replace(/\[\[B2B_SUPPORT_META\]\][\s\S]*?\[\[\/B2B_SUPPORT_META\]\]/g, '')
  .trim();

  if (message.sender_type === 'support') {
  messageText = messageText
    .replace(/^Support\s+vừa\s+có\s+yêu\s+cầu/i, 'Hệ thống TMDT B2B Marketplace xin thông báo: vừa có yêu cầu')
    .replace(/^Support\s*/i, '')
    .trim();

  if (!messageText.startsWith('Hệ thống TMDT B2B Marketplace xin thông báo:')) {
    messageText = messageText.replace(/^[:：]\s*/, '').trim();
    messageText = `Hệ thống TMDT B2B Marketplace xin thông báo: ${messageText}`;
  }

  messageText = messageText.replace(
    /^Hệ thống TMDT B2B Marketplace xin thông báo:\s*[:：]\s*/i,
    'Hệ thống TMDT B2B Marketplace xin thông báo: '
  );
}

  return `
    <div class="chat-message${cssClass}">
      <div class="chat-message-meta">${escapeHtml(senderLabel)} · ${escapeHtml(message.created_at || '')}</div>
      <div class="chat-message-text">${escapeHtml(messageText)}</div>
    </div>
  `;
}
