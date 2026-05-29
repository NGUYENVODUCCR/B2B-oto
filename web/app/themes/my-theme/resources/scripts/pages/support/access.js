import { UserAPI } from '../../api/user.js';

function hasRoleAlias(roleValue, expectedRole) {
  const normalizedRole = String(roleValue || '').toUpperCase().trim();
  const normalizedExpected = String(expectedRole || '').toUpperCase().trim();

  return normalizedRole === normalizedExpected || normalizedRole === `ROLE_${normalizedExpected}`;
}

function normalizeRoleList(value) {
  const splitRoles = (input) => String(input || '')
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean);

  const normalizeRole = (role) => {
    if (role && typeof role === 'object') {
      return String(
        role.role_name
        || role.name
        || role.slug
        || role.role
        || role.code
        || ''
      ).toUpperCase().trim();
    }

    return String(role || '').toUpperCase().trim();
  };

  const flattenRoles = (entry, output = []) => {
    if (Array.isArray(entry)) {
      entry.forEach((item) => flattenRoles(item, output));
      return output;
    }

    const normalized = normalizeRole(entry);
    if (!normalized) {
      return output;
    }

    if (normalized.startsWith('[')) {
      try {
        flattenRoles(JSON.parse(normalized), output);
        return output;
      } catch (error) {
        // fall through
      }
    }

    splitRoles(normalized).forEach((role) => output.push(role));
    return output;
  };

  try {
    return Array.from(new Set(flattenRoles(JSON.parse(String(value || '')))));
  } catch (error) {
    return Array.from(new Set(flattenRoles(value)));
  }
}

function deriveFlagsFromRoles(roles = []) {
  const normalizedRoles = Array.from(new Set((Array.isArray(roles) ? roles : []).map((role) => (
    String(role || '').toUpperCase().trim()
  )).filter(Boolean)));

  const hasRole = (expectedRole) => normalizedRoles.some((role) => (
    hasRoleAlias(role, expectedRole) || role.includes(String(expectedRole || '').toUpperCase())
  ));

  const isAdmin = hasRole('ADMIN') || hasRole('ADMINISTRATOR');
  const isSupport = hasRole('SUPPORT');
  const isSeller = hasRole('SELLER');
  const isBuyer = hasRole('BUYER');

  return {
    is_admin: isAdmin,
    is_support: isSupport,
    is_seller: isSeller,
    is_buyer: isBuyer,
    can_manage_support_workspace: isAdmin || isSupport,
    can_open_seller_channel: isAdmin || isSupport || isSeller,
  };
}

export function createSupportAccess({ state, getHomeUrl }) {
  function cachedRoles() {
    const raw = localStorage.getItem('user_cached_role') || '';
    return raw ? normalizeRoleList(raw) : [];
  }

  function setAccessContext({ roles = [], flags = null, supportDefaultView = 'help' } = {}) {
    const normalizedRoles = Array.from(new Set((Array.isArray(roles) ? roles : []).map((role) => (
      String(role || '').toUpperCase().trim()
    )).filter(Boolean)));
    const fallbackFlags = deriveFlagsFromRoles(normalizedRoles);

    state.access.roles = normalizedRoles;
    state.access.flags = {
      ...fallbackFlags,
      ...(flags && typeof flags === 'object' ? flags : {}),
    };
    state.access.defaultView = String(supportDefaultView || 'help').toLowerCase();
    state.access.hydrated = true;
  }

  async function hydrateAccessContext() {
    const tokenValue = localStorage.getItem('access_token');
    const defaultRoles = cachedRoles();

    if (!tokenValue) {
      setAccessContext({
        roles: defaultRoles,
        flags: deriveFlagsFromRoles(defaultRoles),
        supportDefaultView: 'help',
      });
      return;
    }

    try {
      const capabilities = await UserAPI.getCapabilities();
      const roles = Array.isArray(capabilities?.roles) && capabilities.roles.length
        ? capabilities.roles
        : defaultRoles;
      const flags = capabilities?.flags || deriveFlagsFromRoles(roles);
      const supportDefaultView = capabilities?.support_default_view || 'help';

      localStorage.setItem('user_cached_role', JSON.stringify(roles));

      setAccessContext({
        roles,
        flags,
        supportDefaultView,
      });
      return;
    } catch (error) {
      // Continue to profile fallback.
    }

    try {
      const profile = await UserAPI.getProfile();
      const roles = profile?.roles || profile?.role || profile?.user?.roles || profile?.user?.role || defaultRoles;
      localStorage.setItem('user_cached_role', JSON.stringify(roles));
      const fallbackRoles = cachedRoles();
      const fallbackFlags = deriveFlagsFromRoles(fallbackRoles);

      setAccessContext({
        roles: fallbackRoles,
        flags: fallbackFlags,
        supportDefaultView: fallbackFlags.can_manage_support_workspace ? 'workspace' : 'help',
      });
    } catch (error) {
      const fallbackFlags = deriveFlagsFromRoles(defaultRoles);
      setAccessContext({
        roles: defaultRoles,
        flags: fallbackFlags,
        supportDefaultView: fallbackFlags.can_manage_support_workspace ? 'workspace' : 'help',
      });
    }
  }

  function canManageTickets() {
    return Boolean(state.access.flags?.can_manage_support_workspace);
  }

  function hasSellerRole() {
    return Boolean(state.access.flags?.is_seller);
  }

  function hasBuyerRole() {
    return Boolean(state.access.flags?.is_buyer);
  }

  function canOpenPublicSellerChannel() {
    return Boolean(state.access.flags?.can_open_seller_channel);
  }

  function canAccessSellerChannel() {
    return canOpenPublicSellerChannel();
  }

  function getSupportViewMode() {
    const pageNode = document.getElementById('supportPage');
    const initialView = String(
      pageNode?.dataset?.defaultView
      || state.access.defaultView
      || 'help'
    ).toLowerCase().trim();
    const params = new URLSearchParams(window.location.search);
    const view = String(params.get('view') || '').toLowerCase().trim();
    const forcePublicSellerChannel = String(params.get('force_public') || '') === '1';

    if (view === 'workspace') {
      return canManageTickets() ? 'workspace' : 'help';
    }

    if (view === 'seller-channel') {
      if (forcePublicSellerChannel && canOpenPublicSellerChannel()) {
        return 'seller-channel';
      }

      if (canManageTickets()) {
        return 'workspace';
      }

      return canOpenPublicSellerChannel() ? 'seller-channel' : 'help';
    }

    if (view === 'help') {
      return 'help';
    }

    if (initialView === 'workspace') {
      return canManageTickets() ? 'workspace' : 'help';
    }

    if (initialView === 'seller-channel') {
      if (forcePublicSellerChannel && canOpenPublicSellerChannel()) {
        return 'seller-channel';
      }

      if (canManageTickets()) {
        return 'workspace';
      }

      if (canOpenPublicSellerChannel()) {
        return 'seller-channel';
      }
    }

    if (initialView === 'help') {
      return 'help';
    }

    if (canOpenPublicSellerChannel()) {
      return 'seller-channel';
    }

    return 'help';
  }

  function isHelpMode() {
    return getSupportViewMode() === 'help';
  }

  function isSellerChannelMode() {
    return getSupportViewMode() === 'seller-channel';
  }

  function isInternalSupportView() {
    return getSupportViewMode() === 'workspace' && canManageTickets();
  }

  function isPublicSellerChannelView() {
    return isSellerChannelMode() && canOpenPublicSellerChannel() && !isInternalSupportView();
  }

  function syncSupportNavigationLinks() {
    const openSellerChannelBtn = document.getElementById('supportOpenSellerChannelBtn');
    const backToHelpBtn = document.getElementById('supportBackToHelpBtn');
    const supportUrl = getHomeUrl('/support/');
    const sellerUrl = getHomeUrl('/seller/');
    const showPublicSellerChannel = canOpenPublicSellerChannel();

    if (openSellerChannelBtn) {
      openSellerChannelBtn.hidden = !showPublicSellerChannel;
      openSellerChannelBtn.href = `${supportUrl}?view=seller-channel&force_public=1`;
    }

    if (backToHelpBtn) {
      backToHelpBtn.hidden = !showPublicSellerChannel;
      backToHelpBtn.href = sellerUrl;
      backToHelpBtn.textContent = 'Quay về trang Seller';
    }
  }

  function applySupportVisibility() {
    const internal = isInternalSupportView();
    const adminPage = document.getElementById('supportAdminPage');
    const publicPage = document.getElementById('supportPublicPage');
    const sellerChannelPage = document.getElementById('supportSellerChannelPage');
    const sellerMode = isPublicSellerChannelView();

    if (adminPage) {
      adminPage.hidden = !internal;
    }

    if (publicPage) {
      publicPage.hidden = internal || sellerMode;
    }

    if (sellerChannelPage) {
      sellerChannelPage.hidden = !sellerMode;
    }

    return internal;
  }

  return {
    hydrateAccessContext,
    canManageTickets,
    hasSellerRole,
    hasBuyerRole,
    canOpenPublicSellerChannel,
    canAccessSellerChannel,
    getSupportViewMode,
    isHelpMode,
    isSellerChannelMode,
    isInternalSupportView,
    isPublicSellerChannelView,
    syncSupportNavigationLinks,
    applySupportVisibility,
  };
}
