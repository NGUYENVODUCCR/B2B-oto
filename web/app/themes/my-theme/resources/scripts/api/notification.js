import { apiJson } from './http.js';

export const NotificationAPI = {
  list(limit = 20) {
    return apiJson(`/notifications?limit=${encodeURIComponent(limit)}`, {
      method: 'GET',
    });
  },

  unreadCount() {
    return apiJson('/notifications/unread-count', {
      method: 'GET',
    });
  },

  markRead(notificationId) {
    return apiJson('/notifications/mark-read', {
      method: 'POST',
      body: {
        notification_id: notificationId,
      },
    });
  },

  markAllRead() {
    return apiJson('/notifications/mark-all-read', {
      method: 'POST',
      body: {},
    });
  },
};