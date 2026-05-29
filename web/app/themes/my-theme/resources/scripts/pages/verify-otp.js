import { verifyOtp } from '../api/auth.js';

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('verify-form');

  if (!form) {
    return;
  }

  const homeUrl = window.B2B_CONFIG?.homeUrl || '';

  form.addEventListener('submit', async (event) => {
    event.preventDefault();

    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    try {
      await verifyOtp(data);
      alert('Xac thuc thanh cong');
      window.location.href = `${homeUrl}/login`;
    } catch (error) {
      alert(error.message || 'OTP khong dung');
    }
  });
});

