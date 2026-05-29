import { resetPassword } from '../api/auth.js';

document.addEventListener('DOMContentLoaded', () => {
  const resetForm = document.getElementById('reset-form');

  if (!resetForm) {
    return;
  }

  const homeUrl = window.B2B_CONFIG?.homeUrl || '';

  resetForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    const formData = new FormData(resetForm);
    const password = formData.get('password');
    const confirmPassword = formData.get('confirm_password');

    if (password !== confirmPassword) {
      alert('Mat khau xac nhan khong khop');
      return;
    }

    const data = {
      email: formData.get('email'),
      otp: formData.get('otp'),
      password,
    };

    try {
      await resetPassword(data);
      alert('Doi mat khau thanh cong');
      window.location.href = `${homeUrl}/login`;
    } catch (error) {
      alert(error.message || 'Co loi xay ra');
    }
  });
});

