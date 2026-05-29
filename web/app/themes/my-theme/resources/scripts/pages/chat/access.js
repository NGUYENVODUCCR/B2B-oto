import { UserAPI } from '../../api/user.js';

function flattenRoles(raw, output = []) {
  if (Array.isArray(raw)) {
    raw.forEach((item) => flattenRoles(item, output));
    return output;
  }

  if (raw && typeof raw === 'object') {
    flattenRoles(raw.role_name || raw.name || raw.slug || raw.code || raw.role || raw.wp_role || '', output);
    return output;
  }

  if (typeof raw !== 'string') {
    return output;
  }

  const value = raw.trim();

  if (!value) {
    return output;
  }

  if (value.startsWith('[')) {
    try {
      flattenRoles(JSON.parse(value), output);
      return output;
    } catch (error) {
   
    }
  }

  if (value.includes(',')) {
    value.split(',').forEach((item) => flattenRoles(item, output));
    return output;
  }

  output.push(value.toUpperCase());

  return output;
}

function expandRoleAliases(role) {
  const value = String(role || '').trim().toUpperCase();

  if (!value) {
    return [];
  }

  const aliases = new Set([value]);

  if (value.startsWith('ROLE_')) {
    aliases.add(value.replace(/^ROLE_/, ''));
  } else {
    aliases.add(`ROLE_${value}`);
  }

  if (['ADMIN', 'ADMINISTRATOR', 'ROLE_ADMIN'].includes(value)) {
    aliases.add('ADMIN');
    aliases.add('ADMINISTRATOR');
    aliases.add('ROLE_ADMIN');
  }

  if (['SUPPORT', 'ROLE_SUPPORT'].includes(value)) {
    aliases.add('SUPPORT');
    aliases.add('ROLE_SUPPORT');
  }

  if (['SELLER', 'ROLE_SELLER'].includes(value)) {
    aliases.add('SELLER');
    aliases.add('ROLE_SELLER');
  }

  if (['BUYER', 'ROLE_BUYER'].includes(value)) {
    aliases.add('BUYER');
    aliases.add('ROLE_BUYER');
  }

  return Array.from(aliases).filter(Boolean);
}

function normalizeRoles(...sources) {
  const values = [];

  sources.forEach((source) => {
    flattenRoles(source, values);
  });

  const merged = new Set();

  values.forEach((role) => {
    expandRoleAliases(role).forEach((alias) => merged.add(alias));
  });

  return Array.from(merged);
}

function deriveFlagsFromRoles(roles = []) {
  const roleSet = new Set((Array.isArray(roles) ? roles : []).map((role) => (
    String(role || '').toUpperCase().trim()
  )));

  const hasRole = (needle) => Array.from(roleSet).some((role) => (
    role === needle || role === `ROLE_${needle}` || role.includes(needle)
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

export function createChatAccess({ state }) {
  const access = {
    hydrated: false,
    roles: [],
    flags: deriveFlagsFromRoles([]),
  };

  function rolesFromProfile() {
    return normalizeRoles(
      state.profile?.roles,
      state.profile?.role,
      state.profile?.user?.roles,
      state.profile?.user?.role,
      state.profile?.wp_user?.wp_roles,
      state.profile?.wp_roles,
    );
  }

  function setAccessContext({ roles = [], flags = null } = {}) {
    const normalized = normalizeRoles(roles);
    const fallbackFlags = deriveFlagsFromRoles(normalized);

    access.roles = normalized;
    access.flags = {
      ...fallbackFlags,
      ...(flags && typeof flags === 'object' ? flags : {}),
    };
    access.hydrated = true;

    if (normalized.length) {
      localStorage.setItem('user_cached_role', JSON.stringify(normalized));
    }
  }

  async function hydrateAccessContext() {
    const cachedRoles = normalizeRoles(localStorage.getItem('user_cached_role') || '');
    const profileRoles = rolesFromProfile();
    const token = localStorage.getItem('access_token') || '';
    const baseRoles = profileRoles.length ? profileRoles : cachedRoles;

    if (!token) {
      setAccessContext({
        roles: baseRoles,
        flags: deriveFlagsFromRoles(baseRoles),
      });
      return;
    }

    try {
      const capabilities = await UserAPI.getCapabilities();
      const roles = normalizeRoles(capabilities?.roles, baseRoles);
      const flags = capabilities?.flags || deriveFlagsFromRoles(roles);

      setAccessContext({ roles, flags });
      return;
    } catch (error) {
    }

    setAccessContext({
      roles: baseRoles,
      flags: deriveFlagsFromRoles(baseRoles),
    });
  }

  function canManageSupportTickets() {
    return Boolean(access.flags?.can_manage_support_workspace);
  }

  function canAccessSellerArea() {
    return Boolean(access.flags?.is_admin || access.flags?.is_seller);
  }

  function isOnlyBuyer() {
    const hasBuyer = Boolean(access.flags?.is_buyer);
    const hasOtherRole = Boolean(
      access.flags?.is_admin
      || access.flags?.is_support
      || access.flags?.is_seller
    );

    return hasBuyer && !hasOtherRole;
  }

  return {
    hydrateAccessContext,
    canManageSupportTickets,
    canAccessSellerArea,
    isOnlyBuyer,
    getAccess: () => access,
  };
}
