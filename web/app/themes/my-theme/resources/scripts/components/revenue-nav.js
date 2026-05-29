import { UserAPI } from '../api/user.js';

const ALLOWED_REVENUE_ROLES = new Set([
  'ROLE_ADMIN',
  'ADMIN',
  'ADMINISTRATOR',
  'ROLE_SELLER',
  'SELLER',
  'ROLE_SUPPORT',
  'SUPPORT',
]);

function normalizeRoles(rawRoles) {
  if (Array.isArray(rawRoles)) {
    return rawRoles.flatMap(normalizeRoles);
  }

  if (rawRoles && typeof rawRoles === 'object') {
    return normalizeRoles(rawRoles.role_name || rawRoles.name || rawRoles.role || '');
  }

  if (typeof rawRoles !== 'string' || rawRoles.trim() === '') {
    return [];
  }

  if (rawRoles.trim().startsWith('[')) {
    try {
      return normalizeRoles(JSON.parse(rawRoles));
    } catch (error) {
      return [rawRoles.toUpperCase().trim()];
    }
  }

  return [rawRoles.toUpperCase().trim()];
}

function canSeeRevenue(rawRoles) {
  return normalizeRoles(rawRoles).some((role) => ALLOWED_REVENUE_ROLES.has(role));
}

function setRevenueLinkVisible(isVisible) {
  const link = document.getElementById('navbarRevenueLink');

  if (!link) {
    return;
  }

  link.hidden = !isVisible;
  link.style.display = isVisible ? '' : 'none';
}

function rolesFromProfile(profile) {
  const data = profile?.data || profile || {};
  const user = data.user || data.wp_user || data;

  return data.roles || data.role || user.roles || user.role || [];
}

async function refreshRevenueLink() {
  const token = localStorage.getItem('access_token');

  if (!token) {
    localStorage.removeItem('user_cached_role');
    setRevenueLinkVisible(false);
    return;
  }

  setRevenueLinkVisible(canSeeRevenue(localStorage.getItem('user_cached_role')));

  try {
    const profile = await UserAPI.getProfile();
    const roles = rolesFromProfile(profile);

    if (roles) {
      localStorage.setItem('user_cached_role', JSON.stringify(roles));
    }

    setRevenueLinkVisible(canSeeRevenue(roles));
  } catch (error) {
    setRevenueLinkVisible(canSeeRevenue(localStorage.getItem('user_cached_role')));
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', refreshRevenueLink);
} else {
  refreshRevenueLink();
}
