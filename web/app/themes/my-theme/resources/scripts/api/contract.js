import { apiJson } from './http.js';

export const ContractAPI = {
  createFromQuotation(quotationId) {
    return apiJson('/contract/create-from-quotation', {
      method: 'POST',
      body: { quotation_id: quotationId },
    });
  },

  byQuotation(quotationId) {
    return apiJson(`/contract/by-quotation?quotation_id=${encodeURIComponent(quotationId)}`, {
      method: 'GET',
    });
  },

  sign(payload) {
    return apiJson('/contract/sign', {
      method: 'POST',
      body: payload,
    });
  },

  cancel(contractId, reason = '') {
    return apiJson('/contract/cancel', {
      method: 'POST',
      body: {
        contract_id: contractId,
        reason,
      },
    });
  },

  detail(contractId) {
    return apiJson(`/contract/detail?contract_id=${encodeURIComponent(contractId)}`, {
      method: 'GET',
    });
  },
};
