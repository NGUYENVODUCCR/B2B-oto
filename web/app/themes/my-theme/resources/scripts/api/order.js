import { apiJson } from './http.js';

export const OrderAPI = {
  createFromContract(contractId) {
    return apiJson('/order/create-from-contract', {
      method: 'POST',
      body: { contract_id: contractId },
    });
  },

  detail(orderId) {
    return apiJson(`/order/detail?order_id=${encodeURIComponent(orderId)}`, {
      method: 'GET',
    });
  },

  list(params = {}) {
    const query = new URLSearchParams();

    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined && value !== null && String(value).trim() !== '') {
        query.set(key, value);
      }
    });

    const suffix = query.toString() ? `?${query.toString()}` : '';

    return apiJson(`/order/list${suffix}`, {
      method: 'GET',
    });
  },

  sellerHistory(params = {}) {
    const query = new URLSearchParams();

    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined && value !== null && String(value).trim() !== '') {
        query.set(key, value);
      }
    });

    const suffix = query.toString() ? `?${query.toString()}` : '';

    return apiJson(`/order/seller-history${suffix}`, {
      method: 'GET',
    });
  },

  cancel(orderId, reason = '') {
    return apiJson('/order/cancel', {
      method: 'POST',
      body: {
        order_id: orderId,
        reason,
      },
    });
  },

  markDelivering(orderId) {
    return apiJson('/order/mark-delivering', {
      method: 'POST',
      body: { order_id: orderId },
    });
  },

  complete(orderId) {
    return apiJson('/order/complete', {
      method: 'POST',
      body: { order_id: orderId },
    });
  },
};
