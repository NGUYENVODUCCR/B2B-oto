import { apiJson } from './http.js';

export const RfqAPI = {
  create(payload) {
    return apiJson('/rfq/create', {
      method: 'POST',
      body: payload,
    });
  },

  addItem(payload) {
    return apiJson('/rfq/add-item', {
      method: 'POST',
      body: payload,
    });
  },

  send(rfqId) {
    return apiJson('/rfq/send', {
      method: 'POST',
      body: { rfq_id: rfqId },
    });
  },

  list() {
    return apiJson('/rfq/list', {
      method: 'GET',
    });
  },

  detail(rfqId) {
    return apiJson(`/rfq/detail?rfq_id=${encodeURIComponent(rfqId)}`, {
      method: 'GET',
    });
  },

  sendNegotiation(payload) {
    return apiJson('/rfq/negotiation/send', {
      method: 'POST',
      body: payload,
    });
  },

  negotiationList(rfqId) {
    return apiJson(`/rfq/negotiation/list?rfq_id=${encodeURIComponent(rfqId)}`, {
      method: 'GET',
    });
  },
};
