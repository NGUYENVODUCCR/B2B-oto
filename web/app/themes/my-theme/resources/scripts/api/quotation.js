import { apiJson } from './http.js';

export const QuotationAPI = {
  submit(payload) {
    return apiJson('/quotation/submit', {
      method: 'POST',
      body: payload,
    });
  },

  update(quotationId, payload) {
    return apiJson('/quotation/update', {
      method: 'POST',
      body: {
        ...payload,
        quotation_id: quotationId,
      },
    });
  },

  accept(quotationId) {
    return apiJson('/quotation/accept', {
      method: 'POST',
      body: { quotation_id: quotationId },
    });
  },

  reject(quotationId) {
    return apiJson('/quotation/reject', {
      method: 'POST',
      body: { quotation_id: quotationId },
    });
  },

  byRfq(rfqId) {
    return apiJson(`/quotation/by-rfq?rfq_id=${encodeURIComponent(rfqId)}`, {
      method: 'GET',
    });
  },
};
