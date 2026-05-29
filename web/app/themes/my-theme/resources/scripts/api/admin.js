import { apiFetch } from './http.js';

async function parseJsonResponse(response) {
  const json = await response.json().catch(() => ({}));

  if (!response.ok || json?.success === false) {
    throw new Error(
      json?.message
      || json?.msg
      || `Server returned HTTP code ${response.status}`
    );
  }

  return json;
}

async function request(path, { method = 'GET', body = null } = {}) {
  const response = await apiFetch(path, {
    method,
    ...(body === null ? {} : { body }),
  });

  return parseJsonResponse(response);
}

function query(params = {}) {
  const search = new URLSearchParams();

  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null) {
      return;
    }

    const normalized = String(value).trim();

    if (!normalized) {
      return;
    }

    search.set(key, normalized);
  });

  const suffix = search.toString();
  return suffix ? `?${suffix}` : '';
}

export function createAdminApi() {
  return {
    async fetchSellerRequests() {
      const json = await request('/seller-request/list');
      return json?.data || [];
    },

    updateSellerRequest(requestId, endpoint) {
      return request(`/seller-request/${endpoint}`, {
        method: 'POST',
        body: {
          request_id: requestId,
        },
      });
    },

    async fetchProducts() {
      const json = await request('/product/list');
      return json?.data || json || [];
    },

    fetchProductDetail(productId) {
      return request(`/product/detail?id=${encodeURIComponent(productId)}`);
    },

    updateProduct(productId, payload) {
      return request(`/product/update?id=${encodeURIComponent(productId)}`, {
        method: 'PUT',
        body: payload,
      });
    },

    fetchUsers(page = 1, role = 'all') {
      const finalRole = (role === '' || role === undefined || role === null)
        ? 'all'
        : role;

      return request(`/admin/users${query({
        page,
        per_page: 10,
        role: finalRole,
      })}`);
    },

    async fetchCompanies() {
      const json = await request('/admin/companies');
      return json?.data || [];
    },

    createUser(userData) {
      return request('/admin/users', {
        method: 'POST',
        body: userData,
      });
    },

    updateUser(userData) {
      return request('/admin/users/update', {
        method: 'POST',
        body: userData,
      });
    },

    deleteUser(userId) {
      return request(`/admin/users${query({ user_id: userId })}`, {
        method: 'DELETE',
      });
    },

    toggleUserStatus(userId, status, companyId = null) {
      return request('/admin/users/toggle-status', {
        method: 'POST',
        body: {
          user_id: Number(userId),
          status,
          company_id: Number(companyId || 0),
        },
      });
    },

    async fetchSupportTeamList() {
      const json = await request('/admin/support/list');
      return json?.data || [];
    },

    async fetchSupportMessages(supportId) {
      const json = await request(`/admin/support/messages${query({ support_id: supportId })}`);
      return json?.data || [];
    },

    sendToSupport(supportId, message) {
      return request('/admin/support/send', {
        method: 'POST',
        body: {
          support_id: supportId,
          message,
        },
      });
    },

    async fetchAdminMessages(adminId) {
      const json = await request(`/admin/admin/messages${query({ admin_id: adminId })}`);
      return json?.data || [];
    },

    verifyUserOtp(phone, otp) {
      return request('/auth/verify', {
        method: 'POST',
        body: {
          phone,
          otp,
        },
      });
    },

    sendToAdmin(receiverId, message) {
      return request('/admin/admin/send', {
        method: 'POST',
        body: {
          receiver_id: receiverId,
          message,
        },
      });
    },

    async revenue(params = {}) {
      const json = await request(`/statistics/revenue${query(params)}`);
      return json?.data || {};
    },
  };
}
