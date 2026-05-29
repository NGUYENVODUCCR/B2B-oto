import { forgotPassword } from '../api/auth.js';

document.addEventListener('DOMContentLoaded', () => {
  const forgotForm = document.getElementById('forgot-form');

  if (!forgotForm) {
    return;
  }

  const homeUrl = window.B2B_CONFIG?.homeUrl || '';

  forgotForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    const formData = new FormData(forgotForm);
    const data = {
      email: formData.get('email'),
    };

    try {
      await forgotPassword(data);
      alert('OTP da gui qua email');
      window.location.href = `${homeUrl}/reset-password`;
    } catch (error) {
      alert(error.message || 'Co loi xay ra');
    }
  });
});

