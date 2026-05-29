import { createRealtimeLoop } from '../utils/realtime.js';
import { NotificationAPI } from '../api/notification.js';
import { escapeHtml } from '../utils/format.js';

let notificationSyncInFlight = false;

function setNotificationBadge(count) {
  const badge = document.getElementById('notificationBadge');

  if (!badge) return;

  if (count <= 0) {
    badge.classList.add('hidden');
    badge.textContent = '0';
    return;
  }

  badge.classList.remove('hidden');
  badge.textContent = count > 99 ? '99+' : String(count);
}

function notificationTime(value) {
  if (!value) return '';

  const date = new Date(String(value).replace(' ', 'T'));

  if (Number.isNaN(date.getTime())) {
    return String(value);
  }

  return date.toLocaleString('vi-VN', {
    hour: '2-digit',
    minute: '2-digit',
    day: '2-digit',
    month: '2-digit',
  });
}

function renderNotificationList(items = []) {
  const list = document.getElementById('notificationList');

  if (!list) return;

  if (!Array.isArray(items) || items.length === 0) {
    list.innerHTML = '<div class="notification-empty">Chưa có thông báo.</div>';
    return;
  }

  list.innerHTML = items.map((item) => `
    <button type="button" class="notification-row ${Number(item.is_read || 0) ? '' : 'is-unread'}" data-notification-id="${escapeHtml(item.id)}">
      <span class="notification-row-title">${escapeHtml(item.title || 'Thông báo')}</span>
      <span class="notification-row-content">${escapeHtml(item.content || '')}</span>
      <small>${escapeHtml(notificationTime(item.created_at))}</small>
    </button>
  `).join('');
}

async function syncNotifications() {
  const token = localStorage.getItem('access_token');

  if (!token) {
    setNotificationBadge(0);
    renderNotificationList([]);
    return;
  }

  if (notificationSyncInFlight) return;

  notificationSyncInFlight = true;

  try {
    const result = await NotificationAPI.list(20);
    renderNotificationList(result?.items || []);
    setNotificationBadge(Number(result?.unread_count || 0));
  } catch (error) {
    setNotificationBadge(0);
  } finally {
    notificationSyncInFlight = false;
  }
}

function setNotificationDropdownOpen(isOpen) {
  const button = document.getElementById('notificationBtn');
  const dropdown = document.getElementById('notificationDropdown');

  if (!button || !dropdown) return;

  button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  dropdown.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
  dropdown.classList.toggle('is-open', isOpen);

  if (isOpen) {
    syncNotifications();
  }
}

function initNotifications() {
  const button = document.getElementById('notificationBtn');
  const dropdown = document.getElementById('notificationDropdown');
  const markAllBtn = document.getElementById('notificationMarkAllReadBtn');

  if (!button || !dropdown) return;

  button.addEventListener('click', (event) => {
    event.preventDefault();
    event.stopPropagation();

    setNotificationDropdownOpen(!dropdown.classList.contains('is-open'));
  });

  button.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter' && event.key !== ' ') return;

    event.preventDefault();
    setNotificationDropdownOpen(!dropdown.classList.contains('is-open'));
  });

  dropdown.addEventListener('click', async (event) => {
    event.stopPropagation();

    const row = event.target.closest('[data-notification-id]');

    if (!row || !row.classList.contains('is-unread')) return;

    try {
      const result = await NotificationAPI.markRead(row.dataset.notificationId);
      row.classList.remove('is-unread');
      setNotificationBadge(Number(result?.unread_count || 0));
    } catch (error) {

    }
  });

  markAllBtn?.addEventListener('click', async (event) => {
    event.preventDefault();
    event.stopPropagation();

    try {
      const result = await NotificationAPI.markAllRead();
      document.querySelectorAll('.notification-row.is-unread').forEach((row) => {
        row.classList.remove('is-unread');
      });
      setNotificationBadge(Number(result?.unread_count || 0));
    } catch (error) {
      // Ignore.
    }
  });

  document.addEventListener('click', () => setNotificationDropdownOpen(false));

  createRealtimeLoop({
    interval: 30000,
    maxInterval: 90000,
    eventName: 'b2b:notifications:changed',
    run: syncNotifications,
  }).start();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initNotifications);
} else {
  initNotifications();
}