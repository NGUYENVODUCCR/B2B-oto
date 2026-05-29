import { apiJson } from './http.js';

export const ReviewAPI = {
  create(payload) {
    return apiJson('/review/create', {
      method: 'POST',
      body: payload,
    });
  },

  byOrder(orderId) {
    return apiJson(`/review/by-order?order_id=${encodeURIComponent(orderId)}`, {
      method: 'GET',
    });
  },
};
