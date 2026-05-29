import { apiJson } from './http.js';

export const ProductAPI = {
  listForDashboard() {
    return apiJson('/products/filter', {
      method: 'GET',
    });
  },

  detail(productId) {
    return apiJson('/product/detail', {
      method: 'GET',
      headers: {
        'X-Product-Id': String(productId),
      },
    });
  },
};
