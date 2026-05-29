import { SupportAPI } from '../api/support.js';
import { installGuestApiFallback, installStrictGuestClickGuard } from '../utils/guest-guard.js';
import { UserAPI } from '../api/user.js';

function getHomeUrl(path = '') {
  const baseUrl = (window.B2B_CONFIG?.homeUrl || '').replace(/\/+$/, '');
  const cleanPath = String(path).replace(/^\/+/, '');

  return cleanPath ? `${baseUrl}/${cleanPath}` : baseUrl;
}

function normalizeRoles(rawRoles) {
  if (Array.isArray(rawRoles)) {
    return rawRoles
      .map((role) => {
        if (typeof role === 'object' && role !== null) {
          return String(role.role_name || role.name || '').toUpperCase().trim();
        }
        return String(role).toUpperCase().trim();
      })
      .filter(Boolean);
  }

  if (!rawRoles) {
    return [];
  }

  if (typeof rawRoles === 'string' && rawRoles.trim().startsWith('[')) {
    try {
      return normalizeRoles(JSON.parse(rawRoles));
    } catch (error) {
      return [rawRoles.toUpperCase().trim()];
    }
  }

  return [String(rawRoles).toUpperCase().trim()].filter(Boolean);
}

function isAdmin(roles) {
  return roles.some((role) => role.includes('ADMIN'));
}

function isSupport(roles) {
  return roles.some((role) => role.includes('SUPPORT'));
}

function isSeller(roles) {
  return roles.some((role) => role.includes('SELLER'));
}

function renderHomeRoleMenu(rawRoles) {
  const menu = document.getElementById('homeSubMenu');
  const arrow = document.getElementById('homeSubArrow');

  if (!menu || !arrow) return;

  const roles = normalizeRoles(rawRoles);
  arrow.style.display = 'inline-block';

  if (isAdmin(roles)) {
    menu.innerHTML = `
      <a href="${getHomeUrl('/dashboard/')}">Trang Buyer</a>
      <a href="${getHomeUrl('/seller/')}">Trang Seller</a>
      <a class="home-sub-menu-danger" href="${getHomeUrl('/admin/')}">Trang Admin</a>
      <a href="${getHomeUrl('/support-workspace/')}">Trang Support</a>
    `;
    return;
  }

  if (isSupport(roles)) {
    menu.innerHTML = `
      <a href="${getHomeUrl('/dashboard/')}">Trang Buyer</a>
      <a href="${getHomeUrl('/support-workspace/')}">Trang Support</a>
    `;
    return;
  }

  if (isSeller(roles)) {
    menu.innerHTML = `
      <a href="${getHomeUrl('/seller/')}">Trang Seller</a>
      <a href="${getHomeUrl('/dashboard/')}">Trang Buyer</a>
    `;
    return;
  }

  menu.innerHTML = `
    <a href="#" class="home-sub-menu-disabled" data-disabled-seller="true">Trang Seller</a>
    <a href="${getHomeUrl('/dashboard/')}">Trang Buyer</a>
  `;
}

function cachedRoles() {
  return normalizeRoles(localStorage.getItem('user_cached_role') || '');
}

async function syncRoleMenu() {
  renderHomeRoleMenu(cachedRoles());

  const token = localStorage.getItem('access_token');
  if (!token) {
    applySupportPermissions(cachedRoles());
    return;
  }

  try {
    const data = await UserAPI.getProfile();
    const user = data.user || data.wp_user || {};
    const roles = data.roles || data.role || user.roles || user.role || cachedRoles();

    if (roles) {
      localStorage.setItem('user_cached_role', JSON.stringify(roles));
    }

    renderHomeRoleMenu(roles);
    applySupportPermissions(roles);
  } catch (error) {
    renderHomeRoleMenu(cachedRoles());
    applySupportPermissions(cachedRoles());
  }
}

function applySupportPermissions(rawRoles) {
  const roles = normalizeRoles(rawRoles);

  if (!isSupport(roles)) {
    return;
  }

  const ordersMainLink = document.getElementById('ordersMainLink');
  const ordersSubMenu = document.getElementById('ordersSubMenu');
  const ordersWrapper = document.querySelector('.orders-dropdown-wrapper');

  if (ordersMainLink) {
    ordersMainLink.href = getHomeUrl('/orders');
    const arrow = ordersMainLink.querySelector('.orders-sub-arrow');
    if (arrow) arrow.remove();
  }

  if (ordersSubMenu) ordersSubMenu.remove();
  if (ordersWrapper) ordersWrapper.classList.remove('orders-dropdown-wrapper');

  const revenueLink = document.getElementById('navbarRevenueLink');
  if (revenueLink) revenueLink.remove();

  const hasNonSupportRole = roles.some((role) => !String(role || '').includes('SUPPORT'));

  if (!hasNonSupportRole) {
    const allLinks = document.querySelectorAll('.nav-links > a');
    allLinks.forEach((link) => {
      const href = link.getAttribute('href') || '';
      if (href.includes('/support') && !href.includes('/support-workspace')) {
        link.remove();
      }
    });
  }
}

function modal() {
  return document.getElementById('bulkPurchaseModal');
}

function messageNode() {
  return document.getElementById('bulkPurchaseModalMessage');
}

function setMessage(message, isError = false) {
  const node = messageNode();
  if (!node) return;

  node.textContent = message || '';
  node.classList.toggle('is-error', Boolean(isError));
}

function setOpen(isOpen) {
  const node = modal();
  if (!node) return;

  node.classList.toggle('is-open', isOpen);
  node.setAttribute('aria-hidden', isOpen ? 'false' : 'true');

  if (!isOpen) {
    setMessage('');
  }
}

async function startBulkPurchase() {
  const button = document.getElementById('bulkPurchaseConfirmBtn');

  if (!localStorage.getItem('access_token')) {
    window.location.href = getHomeUrl('/login');
    return;
  }

  if (button) button.disabled = true;
  setMessage('Đang tạo yêu cầu và liên hệ Support...');

  try {
    const result = await SupportAPI.startBulkPurchase();
    const bulkId = result?.bulk?.id || result?.id || '';
    setMessage('Đã tạo yêu cầu. Đang chuyển sang Tin nhắn...');

    window.setTimeout(() => {
      const query = bulkId ? `?bulk_id=${encodeURIComponent(bulkId)}` : '';
      window.location.href = `${getHomeUrl('/chat')}${query}`;
    }, 450);
  } catch (error) {
    setMessage(error.message || 'Không tạo được yêu cầu thu mua số lượng lớn.', true);
  } finally {
    if (button) button.disabled = false;
  }
}

function initNavbarBulkPurchase() {
  const link = document.getElementById('bulkPurchaseNavLink');
  const closeBtn = document.getElementById('bulkPurchaseCloseBtn');
  const cancelBtn = document.getElementById('bulkPurchaseCancelBtn');
  const confirmBtn = document.getElementById('bulkPurchaseConfirmBtn');
  const wrapper = document.querySelector('.orders-dropdown-wrapper');

  const token = localStorage.getItem('access_token');
  const loggedInZone = document.getElementById('nav-logged-in-zone');
  const guestZone = document.getElementById('nav-guest-zone');

  if (!token) {
    if (loggedInZone) loggedInZone.style.display = 'none';
    if (guestZone) guestZone.style.display = 'flex';

    installGuestApiFallback();
    installStrictGuestClickGuard();
  } else {
    if (loggedInZone) loggedInZone.style.display = 'flex';
    if (guestZone) guestZone.style.display = 'none';

    const cachedName = localStorage.getItem('user_cached_name');
    const nameNode = document.getElementById('userName');
    if (cachedName && nameNode) nameNode.textContent = cachedName;
  }


  document.addEventListener('click', (event) => {
    const logoutLink = event.target.closest('[data-navbar-action="logout"]');

    if (!logoutLink) {
      return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();
    localStorage.clear();
    alert('Đã đăng xuất thành công!');
    window.location.href = getHomeUrl('/login');
  }, true);

  syncRoleMenu();
  applySupportPermissions(cachedRoles());

  window.setTimeout(() => {
    renderHomeRoleMenu(cachedRoles());
    applySupportPermissions(cachedRoles());
  }, 350);

  if (!link || !modal()) {
    return;
  }

  document.getElementById('ordersMainLink')?.addEventListener('click', (event) => {
    if (!token) return;
    const roles = cachedRoles();
    if (isSupport(roles)) return;

    if (window.matchMedia('(hover: none)').matches) {
      event.preventDefault();
      wrapper?.classList.toggle('is-open');
    }
  });

  link.addEventListener('click', (event) => {
    if (!token) return;
    event.preventDefault();
    wrapper?.classList.remove('is-open');
    setOpen(true);
  });

  closeBtn?.addEventListener('click', () => setOpen(false));
  cancelBtn?.addEventListener('click', () => setOpen(false));
  confirmBtn?.addEventListener('click', startBulkPurchase);

  modal()?.addEventListener('click', (event) => {
    if (event.target === modal()) setOpen(false);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') setOpen(false);
  });

  document.addEventListener('click', (event) => {
    if (!wrapper?.contains(event.target)) {
      wrapper?.classList.remove('is-open');
    }
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initNavbarBulkPurchase);
} else {
  initNavbarBulkPurchase();
}
