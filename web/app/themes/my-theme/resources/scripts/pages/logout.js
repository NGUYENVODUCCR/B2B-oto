import { logout } from '../api/auth.js';


document.addEventListener('DOMContentLoaded', () => {
  const logoutBtn = document.getElementById('logoutBtn');

  if (!logoutBtn) {
    return;
  }


  logoutBtn.addEventListener('click', async function (e) {
    e.preventDefault();
    
    const confirmLogout = confirm('Bạn có chắc chắn muốn đăng xuất khỏi hệ thống?');
    if (!confirmLogout) return;


    try {

      const result = await logout();
      void result;
      
      alert('Đăng xuất thành công');
    } catch (err) {
  
      console.warn('API logout failed or token expired, cleaning up client anyway:', err);
      localStorage.removeItem('access_token');
      localStorage.removeItem('refresh_token');
    } finally {
      window.location.href = `${window.B2B_CONFIG?.homeUrl || ''}/login`;
    }
  });
});
