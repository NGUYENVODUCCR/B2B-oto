import { login } from '../api/auth.js';

function normalizeRoles(rawRoleData) {
  if (Array.isArray(rawRoleData)) {
    return rawRoleData
      .map((item) => String(item?.role_name || item?.name || item || '').toUpperCase().trim())
      .filter(Boolean);
  }

  if (typeof rawRoleData === 'string' && rawRoleData.trim()) {
    return [rawRoleData.toUpperCase().trim()];
  }

  return [];
}

function redirectByRoles(rolesList = []) {
  const homeUrl = window.B2B_CONFIG?.homeUrl || '';

  if (rolesList.includes('ROLE_ADMIN') || rolesList.includes('ADMINISTRATOR') || rolesList.includes('ADMIN')) {
    window.location.href = `${homeUrl}/admin`;
    return;
  }

  if (rolesList.includes('ROLE_SUPPORT') || rolesList.includes('SUPPORT')) {
    window.location.href = `${homeUrl}/support-workspace`;
    return;
  }

  if (rolesList.includes('ROLE_SELLER') || rolesList.includes('SELLER')) {
    window.location.href = `${homeUrl}/seller`;
    return;
  }

  window.location.href = `${homeUrl}/dashboard`;
}

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('login-form');
  const msg = document.getElementById('login-message');

  if (!form) {
    return;
  }

  form.addEventListener('submit', async (event) => {
    event.preventDefault();

    const formData = new FormData(form);
    const data = {
      phone: formData.get('phone'),
      password: formData.get('password'),
    };

    try {
      const payload = await login(data);

      localStorage.setItem('access_token', payload?.access_token || '');
      localStorage.setItem('refresh_token', payload?.refresh_token || '');

      if (payload?.user) {
        localStorage.setItem('user_cached_name', payload.user);
      }

      const rolesList = normalizeRoles(payload?.role);
      localStorage.setItem('user_cached_role', JSON.stringify(rolesList));

      alert('Đăng nhập thành công');
      redirectByRoles(rolesList);
    } catch (error) {
      if (msg) {
        msg.innerHTML = `<span style="color: red;">${error.message || 'Ten dang nhap hoac mat khau khong chinh xac!'}</span>`;
      }
    }
  });
});

