import { register } from '../api/auth.js';

const registerForm = document.getElementById('register-form');

if (registerForm) {
  registerForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    const formData = new FormData(registerForm);
    const data = Object.fromEntries(formData.entries());
    const message = document.getElementById('register-message');

    if (data.password !== data.confirm_password) {
      if (message) {
        message.innerHTML = '<p style="color:red;">Mat khau xac nhan khong khop</p>';
      }
      return;
    }

    try {
      await register(data);

      if (message) {
        message.innerHTML = '<p style="color:green;">Dang ky thanh cong</p>';
      }

      window.setTimeout(() => {
        window.location.href = `${window.B2B_CONFIG?.homeUrl || ''}/verify-otp?phone=${encodeURIComponent(data.phone || '')}`;
      }, 1000);
    } catch (error) {
      if (message) {
        message.innerHTML = `<p style="color:red;">${error.message || 'Dang ky that bai'}</p>`;
      }
    }
  });
}

