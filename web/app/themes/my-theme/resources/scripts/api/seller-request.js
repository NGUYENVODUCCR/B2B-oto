import { apiFetch } from './http.js';

function buildError(message, payload = null) {
  const error = new Error(message || 'API request failed');
  error.payload = payload;
  return error;
}

async function parseResponse(response) {
  const payload = await response.json().catch(() => ({}));

  if (!response.ok || payload?.success === false) {
    throw buildError(payload?.message || `API error ${response.status}`, payload);
  }

  return payload;
}

export const SellerRequestAPI = {
  async create(formData) {
    const response = await apiFetch('/seller-request/create', {
      method: 'POST',
      body: formData,
    });

    return parseResponse(response);
  },
};

