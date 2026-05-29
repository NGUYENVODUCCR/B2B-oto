export function escapeHtml(value) {
  const div = document.createElement('div');
  div.textContent = value ?? '';

  return div.innerHTML;
}

export function normalizeMojibakeText(value) {
  const raw = String(value ?? '');

  if (!raw) {
    return '';
  }

  if (!/(Ã.|Ä.|á»|áº|Â|Æ|�)/.test(raw)) {
    return raw;
  }

  try {
    const bytes = Uint8Array.from(Array.from(raw).map((char) => char.charCodeAt(0) & 0xff));
    const decoded = new TextDecoder('utf-8').decode(bytes);

    return decoded || raw;
  } catch (_) {
    return raw;
  }
}

export function formatDate(value) {
  if (!value) return '';

  const date = new Date(String(value).replace(' ', 'T'));

  if (Number.isNaN(date.getTime())) {
    return String(value);
  }

  return date.toLocaleString('vi-VN');
}

export function formatCurrency(value) {
  return `${Number(value || 0).toLocaleString('vi-VN')} VND`;
}

export function extractUserIdFromToken(token) {
  const raw = String(token || '').trim();

  if (!raw) {
    return 0;
  }

  const parts = raw.split('.');

  if (parts.length < 2) {
    return 0;
  }

  try {
    const base64 = parts[1].replace(/-/g, '+').replace(/_/g, '/');
    const padded = base64 + '='.repeat((4 - (base64.length % 4)) % 4);
    const payload = JSON.parse(atob(padded));

    return Number(payload?.user_id || payload?.uid || payload?.id || payload?.sub || 0);
  } catch (_) {
    return 0;
  }
}

export function supportCurrentUserId() {
  return Number(
    window.currentWordPressUserId
    || extractUserIdFromToken(localStorage.getItem('access_token'))
    || localStorage.getItem('userId')
    || 0
  );
}

export function supportCurrentUserName() {
  const fromUi = document.querySelector('.custom-dropdown-toggle, #admin-profile-name, .navbar-user-name')?.innerText?.trim();
  const fromConfig = window.B2B_CONFIG?.currentUserTitle;

  return fromUi || fromConfig || 'Support';
}

export function normalizeSupportChatMessages(payload) {
  if (Array.isArray(payload)) {
    return payload;
  }

  if (Array.isArray(payload?.messages)) {
    return payload.messages;
  }

  if (Array.isArray(payload?.data)) {
    return payload.data;
  }

  return [];
}

export const SUPPORT_META_START = '[[B2B_SUPPORT_META]]';
export const SUPPORT_META_END = '[[/B2B_SUPPORT_META]]';

export function buildSupportMetaBlock(meta = {}) {
  const lines = [
    SUPPORT_META_START,
    `kind=${meta.kind || ''}`,
    `rfq_id=${meta.rfq_id || ''}`,
    `bulk_id=${meta.bulk_id || ''}`,
    `support_user_id=${meta.support_user_id || ''}`,
    `support_reference=${meta.support_reference || ''}`,
    SUPPORT_META_END,
  ];

  return lines.join('\n');
}

export function ticketStatusLabel(status) {
  const value = String(status || 'open').toLowerCase();

  return {
    open: 'Mở',
    processing: 'Đang xử lý',
    resolved: 'Đã giải quyết',
    closed: 'Đã đóng',
  }[value] || value;
}

export function bulkStatusLabel(status) {
  const value = String(status || 'started').toLowerCase();

  return {
    started: 'Buyer vừa yêu cầu',
    form_requested: 'Chờ buyer điền form',
    buyer_submitted: 'Buyer đã gửi form',
    published: 'Đang mở cho seller',
    fulfilled: 'Đã đủ số lượng',
    buyer_notified: 'Đã gửi lại buyer',
    accepted: 'Buyer đã đồng ý',
  }[value] || value;
}
