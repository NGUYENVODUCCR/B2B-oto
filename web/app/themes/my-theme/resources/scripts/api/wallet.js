import { apiJson } from './http.js';

export const WalletAPI = {
  balance() {
    return apiJson('/wallet/balance', {
      method: 'GET',
    });
  },

  deposit(payload) {
    return apiJson('/wallet/deposit', {
      method: 'POST',
      body: payload,
    });
  },

  withdraw(payload) {
    return apiJson('/wallet/withdraw', {
      method: 'POST',
      body: payload,
    });
  },
};
