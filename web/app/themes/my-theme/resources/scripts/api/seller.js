import { apiFetch, apiJson } from './http.js';

function toQuery(params = {}) {
  const query = new URLSearchParams();

  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null) {
      return;
    }

    const normalized = String(value).trim();

    if (!normalized) {
      return;
    }

    query.set(key, normalized);
  });

  const suffix = query.toString();
  return suffix ? `?${suffix}` : '';
}

async function parseResponse(response) {
  const result = await response.json().catch(() => ({}));

  if (!response.ok || result?.success === false) {
    throw new Error(result?.message || `API error ${response.status}`);
  }

  return result?.data ?? result;
}

export const SellerAPI = {
  profile() {
    return apiJson('/user/profile', {
      method: 'GET',
    });
  },

  myProducts(params = {}) {
    return apiJson(`/product/my-products${toQuery(params)}`, {
      method: 'GET',
    });
  },

  productDetail(productId) {
    return apiJson('/product/detail', {
      method: 'GET',
      headers: {
        'X-Product-Id': String(productId),
      },
    });
  },

  updateProductStatus(productId, status) {
    return apiJson(`/product/update?id=${encodeURIComponent(productId)}`, {
      method: 'PUT',
      body: {
        status,
      },
    });
  },

  updateProductQuantity(productId, quantity) {
    return apiJson(`/product/update?id=${encodeURIComponent(productId)}`, {
      method: 'PUT',
      body: {
        quantity: Number(quantity),
      },
    });
  },

  deleteProduct(productId) {
    return apiJson(`/product/delete?id=${encodeURIComponent(productId)}`, {
      method: 'DELETE',
    });
  },

  async saveProduct(formData, productId = null) {
    const updateMode = Number(productId || 0) > 0;
    const endpoint = updateMode
      ? `/product/update?id=${encodeURIComponent(productId)}`
      : '/product/create';

    const response = await apiFetch(endpoint, {
      method: 'POST',
      body: formData,
    });

    return parseResponse(response);
  },
};
