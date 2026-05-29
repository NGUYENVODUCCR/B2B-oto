import { apiJson } from './http.js';

export function manualCheckout(orderId) {
  return apiJson('/payment/manual-checkout', {
    method: 'POST',
    body: {
      order_id: orderId,
    },
  });
}

export function getPaymentByOrder(orderId) {
  return apiJson(`/payment/by-order?order_id=${encodeURIComponent(orderId)}`, {
    method: 'GET',
  });
}

export function manualConfirm(paymentId) {
  return apiJson('/payment/manual-confirm', {
    method: 'POST',
    body: {
      payment_id: paymentId,
    },
  });
}

export function releasePayment(paymentId, force = false) {
  return apiJson('/payment/release', {
    method: 'POST',
    body: {
      payment_id: paymentId,
      force,
    },
  });
}

export function pendingReleases(limit = 200) {
  return apiJson(`/payment/pending-releases?limit=${encodeURIComponent(limit)}`, {
    method: 'GET',
  });
}

export const PaymentAPI = {
  create(orderId, paymentMethod = 'vnpay') {
    return apiJson('/payment/create', {
      method: 'POST',
      body: {
        order_id: orderId,
        payment_method: paymentMethod,
      },
    });
  },

  pay(paymentId, gatewayRef = '') {
    return apiJson('/payment/pay', {
      method: 'POST',
      body: {
        payment_id: paymentId,
        gateway_ref: gatewayRef,
      },
    });
  },

  release(paymentId, force = false) {
    return apiJson('/payment/release', {
      method: 'POST',
      body: {
        payment_id: paymentId,
        force,
      },
    });
  },

  byOrder(orderId) {
    return getPaymentByOrder(orderId);
  },

  manualCheckout,

  manualConfirm,

  releasePayment,

  pendingReleases,

  payManual(orderId) {
    return manualCheckout(orderId);
  },

  supportSettle(payload) {
    return apiJson('/payment/support-settle', {
      method: 'POST',
      body: payload,
    });
  },
};
