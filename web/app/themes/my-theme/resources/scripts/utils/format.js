export const EMPTY_IMAGE = `data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><rect width="100%" height="100%" fill="%23eee"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="sans-serif" font-size="10" fill="%23aaa">No Image</text></svg>`;

export function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

export function formatCurrency(value) {
  return `${Number(value || 0).toLocaleString('vi-VN')} VND`;
}

export function normalizeRoles(rawRoles) {
  if (Array.isArray(rawRoles)) {
    return rawRoles.map((role) => {
      if (typeof role === 'object' && role !== null) {
        return String(role.role_name || role.name || '').toUpperCase().trim();
      }

      return String(role).toUpperCase().trim();
    });
  }

  if (!rawRoles) {
    return [];
  }

  if (typeof rawRoles === 'string' && rawRoles.startsWith('[')) {
    try {
      return normalizeRoles(JSON.parse(rawRoles));
    } catch (error) {
      return [rawRoles.toUpperCase().trim()];
    }
  }

  return [String(rawRoles).toUpperCase().trim()];
}

export function getProfileCompanyId(profile) {
  return Number(
    profile?.company?.id ||
    profile?.company_member?.company_id ||
    profile?.company_id ||
    0
  );
}

export function getProfileWpUserId(profile) {
  return Number(
    profile?.wp_user?.id ||
    profile?.wp_user?.ID ||
    profile?.user?.wp_user_id ||
    0
  );
}

export function getProductImage(product) {
  if (!product?.images) {
    return EMPTY_IMAGE;
  }

  if (Array.isArray(product.images) && product.images.length > 0) {
    const first = product.images[0];

    return typeof first === 'object' ? first.image_url || first.url || EMPTY_IMAGE : first;
  }

  return product.images.image_url || product.images.url || EMPTY_IMAGE;
}
