import { apiJson } from './http.js';

export const ChatAPI = {
  conversations() {
    return apiJson('/chat/conversations', {
      method: 'GET',
    });
  },

  detail(rfqId) {
    return apiJson(`/chat/detail?rfq_id=${encodeURIComponent(rfqId)}`, {
      method: 'GET',
    });
  },

  messages(rfqId) {
    return apiJson(`/chat/messages?rfq_id=${encodeURIComponent(rfqId)}`, {
      method: 'GET',
    });
  },

  thread(rfqId) {
    return apiJson(`/chat/thread?rfq_id=${encodeURIComponent(rfqId)}`, {
      method: 'GET',
    });
  },

  sendMessage(payload) {
    return apiJson('/chat/send-message', {
      method: 'POST',
      body: payload,
    });
  },
};
