const BLOCKED_PATTERNS = [
  '/user/profile',
  '/statistics/',
  '/wallet/',
  '/product-review-counts',
];

function normalizeText(value = '') {
  try {
    return String(value)
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .trim();
  } catch (error) {
    return String(value || '').toLowerCase().trim();
  }
}

function isBlockedRequestUrl(url = '') {
  const normalized = String(url || '');
  return BLOCKED_PATTERNS.some((pattern) => normalized.includes(pattern));
}

function guestResponsePayload() {
  return JSON.stringify({
    success: false,
    data: [],
    message: 'Guest mode safe fallback',
  });
}

export function installGuestApiFallback() {
  if (window.REST_API_OVERRIDE_SET) {
    return;
  }

  window.REST_API_OVERRIDE_SET = true;

  const originalFetch = window.fetch;
  window.fetch = async function guestFetchOverride(...args) {
    const target = args[0];
    const url = typeof target === 'string'
      ? target
      : (target instanceof URL ? target.href : '');

    if (isBlockedRequestUrl(url)) {
      return new Response(guestResponsePayload(), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      });
    }

    return originalFetch.apply(this, args);
  };

  const originalOpen = XMLHttpRequest.prototype.open;
  const originalSend = XMLHttpRequest.prototype.send;

  XMLHttpRequest.prototype.open = function guestOpen(method, url, ...rest) {
    this._url = url;
    return originalOpen.apply(this, [method, url, ...rest]);
  };

  XMLHttpRequest.prototype.send = function guestSend(body) {
    if (typeof this._url === 'string' && isBlockedRequestUrl(this._url)) {
      Object.defineProperty(this, 'status', { writable: true, value: 200 });
      Object.defineProperty(this, 'readyState', { writable: true, value: 4 });
      Object.defineProperty(this, 'responseText', { writable: true, value: guestResponsePayload() });

      if (typeof this.onreadystatechange === 'function') {
        this.onreadystatechange();
      }
      if (typeof this.onload === 'function') {
        this.onload();
      }
      return;
    }

    return originalSend.apply(this, [body]);
  };
}

export function installStrictGuestClickGuard() {
  if (window.__B2B_STRICT_GUEST_CLICK_GUARD__) {
    document.removeEventListener('click', window.__B2B_STRICT_GUEST_CLICK_GUARD__, true);
  }

  const handler = function strictGuestClickHandler(event) {
    const targetLink = event.target.closest('a, button, [role="button"], .btn-nav-auth-custom');
if (!targetLink) {
  return;
}


if (
  targetLink.closest('#aiBox')
  || targetLink.closest('.ai-box')
  || targetLink.closest('#aiBoxPanel')
  || targetLink.closest('#aiGuidedMenu')
  || targetLink.closest('#aiAskForm')
) {
  return;
}

    const href = String(targetLink.getAttribute('href') || '');
    const text = normalizeText(targetLink.innerText || '');
    const id = String(targetLink.id || '');
    const className = String(targetLink.className || '');

    const isSellerRegister = text.includes('dang ky ban xe');
    const isOrdersAction = (
      text.includes('don hang')
      || text.includes('thu mua so luong lon')
      || text.includes('quan ly don hang')
      || href.includes('/orders')
      || Boolean(targetLink.closest('.orders-dropdown-wrapper'))
    );

    if (isSellerRegister) {
      event.preventDefault();
      event.stopPropagation();
      alert('Vui long dang nhap hoac dang ky tai khoan de su dung chuc nang Dang ky ban xe.');
      return;
    }

    if (isOrdersAction) {
      event.preventDefault();
      event.stopPropagation();
      alert('Vui long dang nhap hoac dang ky tai khoan de xem va quan ly Don hang.');
      return;
    }

    const isWhitelisted = (
    id === 'nav-guest-zone'
    || Boolean(targetLink.closest('#nav-guest-zone'))
    || className.includes('btn-nav-auth-custom')
    || href.includes('login')
    || href.includes('register')
    || text === 'dang nhap'
    || text === 'dang ky'

    /* AI Assistant public */
    || Boolean(targetLink.closest('#aiBox'))
    || Boolean(targetLink.closest('.ai-box'))
    || Boolean(targetLink.closest('#aiBoxPanel'))
    || Boolean(targetLink.closest('#aiGuidedMenu'))
    || Boolean(targetLink.closest('#aiAskForm'))
    || Boolean(targetLink.closest('.ai-guided-menu'))
    || Boolean(targetLink.closest('.ai-layer'))
    || id.toLowerCase().includes('ai')
    || className.toLowerCase().includes('ai-')

    || targetLink.hasAttribute('data-bs-dismiss')
    || targetLink.hasAttribute('data-dismiss')
    || id.toLowerCase().includes('filter')
    || className.toLowerCase().includes('filter')
    || Boolean(targetLink.closest('.search-filter-container'))
    || Boolean(targetLink.closest('[class*="filter"]'))
  );

    if (isWhitelisted) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();
    alert('Vui long dang nhap hoac dang ky tai khoan de su dung cac chuc nang he thong.');
  };

  window.__B2B_STRICT_GUEST_CLICK_GUARD__ = handler;
  document.addEventListener('click', handler, true);
}

