import { UserAPI } from '../api/user.js';

let selectedAvatarFile = null;

function setModalVisible(modalId, isVisible) {
  const modal = document.getElementById(modalId);

  if (!modal) {
    return;
  }

  modal.style.setProperty('display', isVisible ? 'block' : 'none', 'important');
  modal.classList.toggle('is-open', isVisible);
}

function isValidImageUrl(url) {
  return (
    typeof url === 'string' &&
    (
      url.startsWith('http://') ||
      url.startsWith('https://') ||
      url.startsWith('data:image/') ||
      url.startsWith('/')
    )
  );
}

function previewSelectedAvatar(event) {
  const files = event.target.files;

  if (!files || files.length === 0) {
    return;
  }

  selectedAvatarFile = files[0];

  const reader = new FileReader();
  reader.onload = function onLoad(loadEvent) {
    const avatarPreview = document.getElementById('profAvatarImg');

    if (avatarPreview) {
      avatarPreview.src = loadEvent.target.result;
    }
  };

  reader.readAsDataURL(selectedAvatarFile);
}

async function handleProfileUpdateSubmit(event) {
  event.preventDefault();

  const displayName = document.getElementById('profName')?.value || '';
  const userId = document.getElementById('profId')?.value || '';

  if (!displayName || displayName.trim() === '' || displayName === 'Dang tai...') {
    alert('Vui long dien ten hien thi hop le.');
    return;
  }

  const formData = new FormData();
  formData.append('display_name', displayName);
  formData.append('auth_user_id', userId);
  formData.append('id', userId);

  if (selectedAvatarFile) {
    formData.append('avatar', selectedAvatarFile);
  }

  try {
    const profile = await UserAPI.updateProfileForm(formData);

    alert('Cap nhat thong tin tai khoan thanh cong!');

    const userName = document.getElementById('userName');
    if (userName) {
      userName.innerText = displayName;
    }
    localStorage.setItem('user_cached_name', displayName);

    const avatarUrl = profile?.user_avatar || profile?.avatar || '';

    if (isValidImageUrl(avatarUrl)) {
      const profileAvatar = document.getElementById('profAvatarImg');
      const navAvatar = document.getElementById('navUserAvatar');

      if (profileAvatar) {
        profileAvatar.src = avatarUrl;
      }

      if (navAvatar) {
        navAvatar.src = avatarUrl;
      }

      localStorage.setItem('user_cached_avatar', avatarUrl);
    }

    setModalVisible('profileModal', false);
    selectedAvatarFile = null;
  } catch (error) {
    alert(`Loi ket noi may chu khi cap nhat: ${error.message}`);
  }
}

function initProfileModal() {
  if (!document.getElementById('profileModal') && !document.getElementById('settingModal')) {
    return;
  }

  const profileAvatar = document.getElementById('profAvatarImg');

  if (profileAvatar) {
    profileAvatar.addEventListener('error', () => {
      profileAvatar.src = 'https://www.w3schools.com/howto/img_avatar.png';
    });
  }

  document.getElementById('avatarUploadInput')?.addEventListener('change', previewSelectedAvatar);
  document.getElementById('profileForm')?.addEventListener('submit', handleProfileUpdateSubmit);

  document.getElementById('closeProfile')?.addEventListener('click', () => {
    setModalVisible('profileModal', false);
  });

  document.getElementById('closeSetting')?.addEventListener('click', () => {
    setModalVisible('settingModal', false);
  });

  document.getElementById('saveSettingBtn')?.addEventListener('click', () => {
    alert('Da luu cau hinh cai dat thanh cong!');
    setModalVisible('settingModal', false);
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initProfileModal);
} else {
  initProfileModal();
}

