export function parseJwtPayload(token) {
  if (!token || typeof token !== 'string') {
    return {};
  }

  const parts = token.split('.');

  if (parts.length < 2) {
    return {};
  }

  try {
    const base64 = parts[1].replace(/-/g, '+').replace(/_/g, '/');
    const padded = base64.padEnd(base64.length + ((4 - (base64.length % 4)) % 4), '=');
    const json = window.atob(padded);

    return JSON.parse(json || '{}');
  } catch (error) {
    return {};
  }
}

export function extractUserIdFromJwtPayload(payload) {
  const extractedId =
    payload?.id
    || payload?.userId
    || payload?.sub
    || payload?.user?.id
    || payload?.data?.user?.id
    || payload?.data?.user_id
    || payload?.usr_id;

  return extractedId ? Number(extractedId) : null;
}

export function extractUserIdFromToken(token) {
  const payload = parseJwtPayload(token);
  return extractUserIdFromJwtPayload(payload);
}

