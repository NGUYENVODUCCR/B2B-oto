import { getProfile, updateProfile, logout } from '../api/auth.js';

function normalizeProfileResponse(result) {
  return result?.data || result || null;
}


(async () => {
  const token = localStorage.getItem('access_token');
  if (token && document.getElementById('userName')) {
    const cachedName = localStorage.getItem('user_cached_name');
    if (cachedName) {
      document.getElementById('userName').innerText = cachedName;
    }
    const cachedAvatar = localStorage.getItem('user_cached_avatar');
    if (cachedAvatar && document.getElementById('navUserAvatar')) {
      document.getElementById('navUserAvatar').src = cachedAvatar;
    }

    try {
      const result = await getProfile();
      const resData = normalizeProfileResponse(result);
      if (resData) {
        const userData = resData.user || resData.wp_user || resData;
        const displayName = userData.fullname || userData.display_name || userData.user_login || 'User';
        
        document.getElementById('userName').innerText = displayName;
        localStorage.setItem('user_cached_name', displayName);
        
        let avatar = resData.user_avatar || userData.user_avatar || resData.avatar || '';
        if (avatar && typeof avatar === 'string' && avatar.startsWith('http')) {
          if (document.getElementById('navUserAvatar')) {
            document.getElementById('navUserAvatar').src = avatar;
          }
          localStorage.setItem('user_cached_avatar', avatar);
        }
      }
    } catch (err) {
      console.warn("Lỗi đồng bộ nạp tên hiển thị Navbar:", err.message);
    }
  }
})();


window.addEventListener('click', async (e) => {
  if (!e.target) return;

  const target = e.target.closest('a') || e.target;
  if (!target || !target.id) return;

  const targetId = target.id;


  if (targetId === 'viewProfileItem') {
    e.preventDefault();
    e.stopPropagation();
    console.log('👉 Đã nhận lệnh Click Xem Profile');

    const profileModal = document.getElementById('profileModal');
    if (!profileModal) {
      console.error('❌ Không tìm thấy khối giao diện #profileModal trên DOM!');
      return alert('Lỗi: Giao diện Form Profile chưa được nhúng vào trang này.');
    }

   
    profileModal.style.setProperty('display', 'block', 'important');
    profileModal.style.setProperty('z-index', '1000000', 'important');

  
    if (document.getElementById('profName')) {
      document.getElementById('profName').value = localStorage.getItem('user_cached_name') || 'Đang tải...';
    }
    

    const cachedAvatar = localStorage.getItem('user_cached_avatar');
    if (document.getElementById('profAvatarImg')) {
      document.getElementById('profAvatarImg').src = cachedAvatar || 'https://www.w3schools.com/howto/img_avatar.png';
    }

    try {
      const result = await getProfile();
      console.log('✅ Dữ liệu gốc nhận được từ API:', result);
      
      const resData = normalizeProfileResponse(result);
      if (resData) {
        const uObj = resData.user || {};
        const wpObj = resData.wp_user || {};

  
        if (document.getElementById('profId')) document.getElementById('profId').value = uObj.id || wpObj.id || '5';
        if (document.getElementById('profName')) {
          document.getElementById('profName').value = uObj.fullname || wpObj.display_name || '';
          localStorage.setItem('user_cached_name', document.getElementById('profName').value);
        }
        if (document.getElementById('profEmail')) document.getElementById('profEmail').value = wpObj.email || uObj.email || '';
        if (document.getElementById('profPhone')) document.getElementById('profPhone').value = uObj.phone || 'Chưa cập nhật';
        
   
        let avatar = resData.user_avatar || uObj.user_avatar || wpObj.user_avatar || '';
        const avatarImgElement = document.getElementById('profAvatarImg');
        
        if (avatarImgElement) {
          if (avatar && typeof avatar === 'string' && avatar.startsWith('http')) {
            avatarImgElement.src = avatar;
            localStorage.setItem('user_cached_avatar', avatar);
          } else {
       
            avatarImgElement.src = 'https://www.w3schools.com/howto/img_avatar.png';
          }
        }
      }
    } catch (error) {
      console.error('❌ Lỗi gọi API Profile:', error.message);
    }
  }


  if (targetId === 'viewSettingItem') {
    e.preventDefault();
    e.stopPropagation();
    const settingModal = document.getElementById('settingModal');
    if (settingModal) {
      settingModal.style.setProperty('display', 'block', 'important');
      settingModal.style.setProperty('z-index', '1000000', 'important');
    }
  }


  if (targetId === 'logoutBtn') {
    e.preventDefault();
    e.stopPropagation();
    if (!confirm('Bạn có chắc chắn muốn đăng xuất khỏi tài khoản này?')) return;

    try {
      await logout();
    } catch (err) {
      console.warn('Hệ thống xóa token tại Client dù API gặp sự cố:', err.message);
    } finally {
      localStorage.removeItem('access_token');
      localStorage.removeItem('refresh_token');
      localStorage.removeItem('user_cached_name');
      localStorage.removeItem('user_cached_avatar');
      window.location.href = `${window.B2B_CONFIG?.homeUrl || ''}/login`;
    }
  }
});
