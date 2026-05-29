import { apiJson } from './http.js';

export const SupportAPI = {
  createTicket(payload) {
    return apiJson('/support/create-ticket', {
      method: 'POST',
      body: payload,
    });
  },

  sendMessage(payload) {
    return apiJson('/support/send-message', {
      method: 'POST',
      body: payload,
    });
  },

  close(ticketId, status = 'closed') {
    return apiJson('/support/close', {
      method: 'POST',
      body: {
        ticket_id: ticketId,
        status,
      },
    });
  },

  list(options = {}) {
    const query = new URLSearchParams();

    if (options.all) {
      query.set('all', '1');
    }

    if (options.include_messages) {
      query.set('include_messages', '1');
    }

    return apiJson(`/support/list${query.toString() ? `?${query.toString()}` : ''}`, {
      method: 'GET',
    });
  },

  detail(ticketId) {
    const query = new URLSearchParams();
    query.set('ticket_id', ticketId);

    return apiJson(`/support/detail?${query.toString()}`, {
      method: 'GET',
    });
  },

  ticketContext(ticketId) {
    const query = new URLSearchParams();
    query.set('ticket_id', ticketId);

    return apiJson(`/support/ticket-context?${query.toString()}`, {
      method: 'GET',
    });
  },

  supportAgents() {
    return apiJson('/support/agents', {
      method: 'GET',
    });
  },

  startBulkPurchase() {
    return apiJson('/support/bulk/start', {
      method: 'POST',
      body: {},
    });
  },

  listBulkPurchases(options = {}) {
    const query = new URLSearchParams();

    if (options.channel) {
      query.set('channel', options.channel);
    }

    return apiJson(`/support/bulk/list${query.toString() ? `?${query.toString()}` : ''}`, {
      method: 'GET',
    });
  },

  requestBulkForm(bulkId) {
    return apiJson('/support/bulk/request-form', {
      method: 'POST',
      body: {
        bulk_id: bulkId,
      },
    });
  },

  submitBulkProductForm(payload) {
    return apiJson('/support/bulk/buyer-form', {
      method: 'POST',
      body: payload,
    });
  },

  sendBulkRFQ(bulkId) {
    return apiJson('/support/bulk/send-rfq', {
      method: 'POST',
      body: {
        bulk_id: bulkId,
      },
    });
  },

  joinBulkRFQ(payload) {
    return apiJson('/support/bulk/join', {
      method: 'POST',
      body: payload,
    });
  },

  sendBulkToBuyer(bulkId) {
    return apiJson('/support/bulk/send-buyer', {
      method: 'POST',
      body: {
        bulk_id: bulkId,
      },
    });
  },

  acceptBulkQuotation(bulkId) {
    return apiJson('/support/bulk/accept', {
      method: 'POST',
      body: {
        bulk_id: bulkId,
      },
    });
  },

  sendBulkMessage(payload) {
    return apiJson('/support/bulk/message', {
      method: 'POST',
      body: payload,
    });
  },

  sellerChannel() {
    return apiJson('/support/seller-channel', {
      method: 'GET',
    });
  },

  sendSellerChannelMessage(message) {
    return apiJson('/support/seller-channel/message', {
      method: 'POST',
      body: {
        message,
      },
    });
  },
};
