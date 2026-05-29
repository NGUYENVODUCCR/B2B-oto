import { apiFetch, apiJson } from './http.js';

async function parseResponse(response) {
  const result = await response.json().catch(() => ({}));

  if (!response.ok || result?.success === false) {
    throw new Error(result?.message || `API error ${response.status}`);
  }

  return result;
}

async function post(path, data = {}) {
  const response = await apiFetch(path, {
    method: 'POST',
    body: data,
  });

  return parseResponse(response);
}

export async function register(data) {
  const result = await post('/auth/register', data);
  return result.data;
}

export async function verifyOtp(data) {
  const result = await post('/auth/verify', data);
  return result.data;
}

export async function forgotPassword(data) {
  return post('/auth/forgot-password', data);
}

export async function resetPassword(data) {
  return post('/auth/reset-password', data);
}

export async function logout() {
  const refreshToken = localStorage.getItem('refresh_token');
  const response = await apiFetch('/auth/logout', {
    method: 'POST',
    body: {
      refresh_token: refreshToken,
    },
  });

  let result = {};

  try {
    result = await parseResponse(response);
  } finally {
    localStorage.removeItem('access_token');
    localStorage.removeItem('refresh_token');
  }

  return result;
}

export async function getProfile() {
  return apiJson('/user/profile', {
    method: 'GET',
  });
}

export async function updateProfile(data) {
  return apiJson('/user/update', {
    method: 'PUT',
    body: data,
  });
}

export async function login(data) {
  const result = await post('/auth/login', data);
  return result.data;
}

