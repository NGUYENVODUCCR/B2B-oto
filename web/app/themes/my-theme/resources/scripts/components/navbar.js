import { createRealtimeLoop } from '../utils/realtime.js';
import { ChatAPI } from '../api/chat.js';
import { UserAPI } from '../api/user.js';

const DEFAULT_AVATAR = 'https://www.w3schools.com/howto/img_avatar.png';
let chatBadgeSyncInFlight = false;

function isSupportWorkspacePage() {
  const page = String(document.body?.dataset?.page || '').toLowerCase().trim();
  const path = String(window.location?.pathname || '').toLowerCase();

  return page === 'support' || page === 'support-workspace' || path.includes('/support-workspace');
}

function getHomeUrl(path = '') {
  const baseUrl = (window.B2B_CONFIG?.homeUrl || '').replace(/\/+$/, '');
  const cleanPath = String(path).replace(/^\/+/, '');

  return cleanPath ? `${baseUrl}/${cleanPath}` : baseUrl;
}

function isValidImageUrl(url) {
  if (!url || typeof url !== 'string') {
    return false;
  }

  const value = url.trim();

  return (
    value !== '' &&
    value !== 'undefined' &&
    value !== 'null' &&
    (
      value.startsWith('http://') ||
      value.startsWith('https://') ||
      value.startsWith('data:image/') ||
      value.startsWith('/')
    )
  );
}

function normalizeRoles(rawRoles) {
  if (Array.isArray(rawRoles)) {
    return rawRoles.map((role) => {
      if (typeof role === 'object' && role !== null) {
        return String(role.role_name || '').toUpperCase().trim();
      }

      return String(role).toUpperCase().trim();
    });
  }

  if (typeof rawRoles !== 'string' || rawRoles.trim() === '') {
    return [];
  }

  if (rawRoles.startsWith('[')) {
    try {
      return normalizeRoles(JSON.parse(rawRoles));
    } catch (error) {
      return [rawRoles.toUpperCase().trim()];
    }
  }

  return [rawRoles.toUpperCase().trim()];
}

function renderHomeMenuBasedOnRole(rawRoles) {
  const subMenuContainer = document.getElementById('homeSubMenu');
  const arrow = document.getElementById('homeSubArrow');

  if (!subMenuContainer || !arrow) {
    return;
  }

  const rolesList = normalizeRoles(rawRoles);
  arrow.style.display = 'inline-block';

  if (rolesList.includes('ROLE_ADMIN') || rolesList.includes('ADMINISTRATOR')) {
    subMenuContainer.innerHTML = `
      <a href="${getHomeUrl('/dashboard/')}">Trang mua hàng</a>
      <a href="${getHomeUrl('/seller/')}">Trang bán hàng</a>
      <a class="home-sub-menu-danger" href="${getHomeUrl('/admin/')}">Trang quản lý</a>
    `;
    return;
  }

  if (rolesList.includes('ROLE_SELLER') || rolesList.includes('SELLER')) {
    subMenuContainer.innerHTML = `
      <a href="${getHomeUrl('/seller/')}">Trang bán hàng</a>
      <a href="${getHomeUrl('/dashboard/')}">Trang mua hàng</a>
    `;
    return;
  }

  subMenuContainer.innerHTML = `
    <a href="#" class="home-sub-menu-disabled" data-disabled-seller="true">Trang bán hàng</a>
    <a href="${getHomeUrl('/dashboard/')}">Trang mua hàng</a>
  `;
}

function setChatBadge(count) {
  ['chatBadge', 'sellerChatBadge'].forEach((id) => {
    const badge = document.getElementById(id);

    if (!badge) {
      return;
    }

    if (count <= 0) {
      badge.classList.add('hidden');
      badge.innerText = '0';
      return;
    }

    badge.classList.remove('hidden');
    badge.innerText = count > 99 ? '99+' : String(count);
  });
}

function isChatNotificationRow(row) {
  const kind = String(row?.kind || '').toLowerCase().trim();

  if (kind === 'support' || kind === 'bulk') {
    return false;
  }

  if (row?.bulk_id || row?.ticket_id || row?.support_ticket_id) {
    return false;
  }

  return true;
}

async function syncChatBadges() {
  if (isSupportWorkspacePage()) {
    setChatBadge(0);
    return;
  }

  const token = localStorage.getItem('access_token');

  if (!token) {
    setChatBadge(0);
    return;
  }

  if (chatBadgeSyncInFlight) {
    return;
  }

  chatBadgeSyncInFlight = true;

  try {
    const result = await ChatAPI.conversations();
    const rows = Array.isArray(result) ? result : [];
    const activeCount = rows.filter((rfq) => (
      isChatNotificationRow(rfq) &&
      !['closed', 'cancelled'].includes(String(rfq.status || '').toLowerCase())
    )).length;

    setChatBadge(activeCount);
  } catch (error) {
    setChatBadge(0);
  } finally {
    chatBadgeSyncInFlight = false;
  }
}

async function syncNavbarUserData() {
  const token = localStorage.getItem('access_token');

  if (!token) {
    return;
  }

  try {
    const resData = await UserAPI.getProfile();

    if (!resData) {
      return;
    }

    const userData = resData.user || resData.wp_user || resData;
    const finalName = userData.fullname || userData.display_name || userData.user_login || 'User';
    const userName = document.getElementById('userName');

    if (userName) {
      userName.innerText = finalName;
    }

    localStorage.setItem('user_cached_name', finalName);

    let finalRoles = resData.role || userData.role || userData.roles || '';

    if (finalRoles) {
      if (Array.isArray(finalRoles)) {
        finalRoles = finalRoles.map((item) => (
          typeof item === 'object' && item !== null ? item.role_name || '' : item
        ));
      }

      localStorage.setItem('user_cached_role', JSON.stringify(finalRoles));
      renderHomeMenuBasedOnRole(finalRoles);
    }

    const finalAvatar = userData.user_avatar || userData.avatar || resData.user_avatar || resData.avatar || '';
    const navbarAvatar = document.getElementById('navUserAvatar');

    if (isValidImageUrl(finalAvatar)) {
      if (navbarAvatar) {
        navbarAvatar.src = finalAvatar;
      }

      localStorage.setItem('user_cached_avatar', finalAvatar);
      return;
    }

    const fallbackAvatar = localStorage.getItem('user_cached_avatar');

    if (navbarAvatar) {
      navbarAvatar.src = isValidImageUrl(fallbackAvatar) ? fallbackAvatar : DEFAULT_AVATAR;
    }
  } catch (err) {
    console.warn('[Navbar API] Lỗi kết nối profile, đang giữ dữ liệu cục bộ.');
  }
}

function setModalVisible(modalId, isVisible) {
  const modal = document.getElementById(modalId);

  if (!modal) {
    return null;
  }

  modal.style.setProperty('display', isVisible ? 'block' : 'none', 'important');
  modal.classList.toggle('is-open', isVisible);

  return modal;
}

async function openProfileModal() {
  const profileModal = setModalVisible('profileModal', true);

  if (!profileModal) {
    alert('Lỗi: Không tìm thấy #profileModal');
    return;
  }

  const profName = document.getElementById('profName');
  const profAvatar = document.getElementById('profAvatarImg');
  const localAvatar = localStorage.getItem('user_cached_avatar');

  if (profName) {
    profName.value = localStorage.getItem('user_cached_name') || '';
  }

  if (profAvatar) {
    profAvatar.src = isValidImageUrl(localAvatar) ? localAvatar : DEFAULT_AVATAR;
  }

  try {
    const resData = await UserAPI.getProfile();

    if (!resData) {
      return;
    }

    const user = resData.user || {};
    const wpUser = resData.wp_user || {};

    if (document.getElementById('profId')) {
      document.getElementById('profId').value = user.id || wpUser.id || '';
    }

    if (document.getElementById('profName')) {
      document.getElementById('profName').value = user.fullname || wpUser.display_name || '';
    }

    if (document.getElementById('profEmail')) {
      document.getElementById('profEmail').value = wpUser.email || user.email || '';
    }

    if (document.getElementById('profPhone')) {
      document.getElementById('profPhone').value = user.phone || 'Chưa cập nhật';
    }

    const serverAvatar = user.user_avatar || user.avatar || wpUser.user_avatar || resData.avatar || '';

    if (isValidImageUrl(serverAvatar) && profAvatar) {
      profAvatar.src = serverAvatar;
      localStorage.setItem('user_cached_avatar', serverAvatar);

      const navAvatar = document.getElementById('navUserAvatar');

      if (navAvatar) {
        navAvatar.src = serverAvatar;
      }
    }
  } catch (error) {
    console.error('[Modal Profile Error]', error);
  }
}

function closeNavbarMenus() {
  const userMenu = document.getElementById('userDropdownMenu');
  const homeMenu = document.getElementById('homeSubMenu');

  if (userMenu) {
    userMenu.style.display = 'none';
  }

  if (homeMenu) {
    homeMenu.style.display = 'none';
  }
}

function toggleHomeMenu(event) {
  const menu = document.getElementById('homeSubMenu');
  const arrow = document.getElementById('homeSubArrow');

  if (!menu || arrow?.style.display === 'none') {
    return;
  }

  event.preventDefault();
  event.stopPropagation();
  menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
}

function toggleUserDropdown(event) {
  const menu = document.getElementById('userDropdownMenu');

  if (!menu) {
    return;
  }

  event.preventDefault();
  event.stopPropagation();
  menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
}

async function handleNavbarAction(actionType, event) {
  event.preventDefault();
  event.stopPropagation();
  closeNavbarMenus();

  if (actionType === 'profile') {
    await openProfileModal();
    return;
  }

  if (actionType === 'setting') {
    setModalVisible('settingModal', true);
    return;
  }

  if (actionType === 'logout') {
    if (!confirm('Bạn có chắc chắn muốn đăng xuất?')) {
      return;
    }

    localStorage.clear();
    window.location.href = `${getHomeUrl('/login')}`;
  }
}

function hydrateNavbarFromCache() {
  const cachedName = localStorage.getItem('user_cached_name') || '';
  const cachedAvatar = localStorage.getItem('user_cached_avatar');
  const cachedRole = localStorage.getItem('user_cached_role') || '';

  const userName = document.getElementById('userName');
  const avatar = document.getElementById('navUserAvatar');

  if (cachedName && userName) {
    userName.innerText = cachedName;
  }

  if (avatar) {
    avatar.src = isValidImageUrl(cachedAvatar) ? cachedAvatar : DEFAULT_AVATAR;
  }

  let rolesArray = normalizeRoles(cachedRole);

  if (rolesArray.length === 0 && cachedName) {
    const nameLower = cachedName.toLowerCase();

    if (nameLower.includes('admin')) {
      rolesArray = ['ROLE_ADMIN'];
    } else if (nameLower.includes('seller') || nameLower.includes('hieulocheo')) {
      rolesArray = ['ROLE_SELLER'];
    } else {
      rolesArray = ['ROLE_BUYER'];
    }
  }

  renderHomeMenuBasedOnRole(rolesArray);
}

function initNavbar() {
  if (!document.querySelector('.b2b-navbar')) {
    return;
  }

  const isChatPage = document.body?.dataset?.page === 'chat';
  const isSupportPage = isSupportWorkspacePage();

  const navbarAvatar = document.getElementById('navUserAvatar');

  if (navbarAvatar) {
    navbarAvatar.addEventListener('error', () => {
      navbarAvatar.src = DEFAULT_AVATAR;
    });
  }

  document.getElementById('homeMainLink')?.addEventListener('click', toggleHomeMenu);
  document.getElementById('userDropdownBtn')?.addEventListener('click', toggleUserDropdown);

  document.querySelectorAll('[data-navbar-action]').forEach((link) => {
    link.addEventListener('click', (event) => handleNavbarAction(link.dataset.navbarAction, event));
  });

  document.getElementById('homeSubMenu')?.addEventListener('click', (event) => {
    const disabledSellerLink = event.target.closest('[data-disabled-seller]');

    if (!disabledSellerLink) {
      return;
    }

    event.preventDefault();
    alert('Bạn chưa đăng ký bán hàng, không thể vào trang này. Vui lòng đăng ký tài khoản bán hàng.');
  });

  document.addEventListener('click', closeNavbarMenus);

  hydrateNavbarFromCache();

  if (!isChatPage && !isSupportPage) {
    const chatBadgeLoop = createRealtimeLoop({
      interval: 45000,
      maxInterval: 90000,
      eventName: 'b2b:chat:changed',
      run: syncChatBadges,
    });

    syncNavbarUserData();
    chatBadgeLoop.start();
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initNavbar);
} else {
  initNavbar();
}
