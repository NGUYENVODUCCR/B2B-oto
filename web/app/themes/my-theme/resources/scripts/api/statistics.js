import { apiJson } from './http.js';

export const StatisticsAPI = {
  revenue(params = {}) {
    const query = new URLSearchParams();

    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined && value !== null && String(value).trim() !== '') {
        query.set(key, value);
      }
    });

    const suffix = query.toString() ? `?${query.toString()}` : '';

    return apiJson(`/statistics/revenue${suffix}`, {
      method: 'GET',
    });
  },

  productReviewCounts() {
    return apiJson('/statistics/product-review-counts', {
      method: 'GET',
    });
  },
};
