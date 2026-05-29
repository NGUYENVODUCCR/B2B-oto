
import { apiFetch, apiJson } from './http.js';
import { API_ENDPOINTS } from './constants.js';

async function parseResponse(response, fallbackMessage = 'API request failed') {
  const result = await response.json().catch(() => ({}));

  if (!response.ok || result?.success === false) {
    throw new Error(result?.message || fallbackMessage);
  }

  return result?.data ?? result;
}

export const UserAPI = {
  async getProfile() {
    return apiJson(API_ENDPOINTS.USER.PROFILE, {
      method: 'GET',
    });
  },

  async getCapabilities() {
    return apiJson(API_ENDPOINTS.USER.CAPABILITIES, {
      method: 'GET',
    });
  },

  async updateProfile(data) {
    const response = await apiFetch(API_ENDPOINTS.USER.UPDATE, {
      method: 'PUT',
      body: data,
    });

    return parseResponse(response, 'Profile update failed');
  },

  async updateProfileForm(formData) {
    const response = await apiFetch(API_ENDPOINTS.USER.UPDATE, {
      method: 'POST',
      body: formData,
    });

    return parseResponse(response, 'Profile update failed');
  },

  async uploadAvatar(file) {
    if (!file || !(file instanceof File)) {
      throw new Error('File parameter is required');
    }

    const formData = new FormData();
    formData.append('avatar', file);

    const response = await apiFetch(`${API_ENDPOINTS.USER.UPDATE}/avatar`, {
      method: 'POST',
      body: formData,
    });

    return parseResponse(response, 'Avatar upload failed');
  },
};
