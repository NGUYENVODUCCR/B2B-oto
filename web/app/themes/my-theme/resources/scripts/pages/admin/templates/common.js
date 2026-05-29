export const PLACEHOLDER_IMAGE = 'https://placeholder.com';

export function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

export function formatMoney(value) {
  return `${Number(value || 0).toLocaleString()} VNĐ`;
}

export function getProductFilterStatus(filter) {
  const map = {
    deleted: 'deleted',
    pending: 'inactive',
    verified: 'active',
    unverified: 'blocked',
  };

  return map[filter] || '';
}

