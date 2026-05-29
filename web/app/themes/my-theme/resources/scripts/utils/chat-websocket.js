import { getToken } from '../api/http.js';

function normalizeUrl(url) {
  return String(url || '').trim();
}

export function getChatWebSocketUrl() {
  return normalizeUrl(window.B2B_CONFIG?.chatWsUrl || localStorage.getItem('b2b_chat_ws_url') || '');
}

function withQuery(url, params = {}) {
  try {
    const parsed = new URL(url, window.location.href);

    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') {
        parsed.searchParams.set(key, value);
      }
    });

    return parsed.toString();
  } catch (error) {
    return url;
  }
}

function parseMessage(event) {
  if (!event?.data) {
    return null;
  }

  if (typeof event.data === 'object') {
    return event.data;
  }

  try {
    return JSON.parse(event.data);
  } catch (error) {
    return null;
  }
}

export function createChatWebSocket(options = {}) {
  const {
    getActiveId = () => null,
    onEvent = () => {},
    onStatus = () => {},
  } = options;

  let socket = null;
  let stopped = true;
  let reconnectTimer = null;
  let reconnectDelay = 1000;
  let subscribedRfqId = null;

  const clearReconnect = () => {
    if (reconnectTimer) {
      window.clearTimeout(reconnectTimer);
      reconnectTimer = null;
    }
  };

  const sendJson = (payload) => {
    if (!socket || socket.readyState !== WebSocket.OPEN) {
      return false;
    }

    socket.send(JSON.stringify(payload));
    return true;
  };

  const subscribeActive = () => {
    const rfqId = subscribedRfqId || getActiveId();

    if (!rfqId) {
      return;
    }

    sendJson({
      type: 'subscribe',
      channel: 'chat',
      rfq_id: rfqId,
    });
  };

  const scheduleReconnect = () => {
    if (stopped) {
      return;
    }

    clearReconnect();
    reconnectTimer = window.setTimeout(() => {
      connect();
    }, reconnectDelay);
    reconnectDelay = Math.min(reconnectDelay * 2, 15000);
  };

  function connect() {
    const url = getChatWebSocketUrl();

    if (!url || !('WebSocket' in window)) {
      return false;
    }

    clearReconnect();

    try {
      socket = new WebSocket(withQuery(url, { rfq_id: getActiveId() || '' }));
    } catch (error) {
      scheduleReconnect();
      return false;
    }

    socket.addEventListener('open', () => {
      reconnectDelay = 1000;
      onStatus('open');

      const token = getToken();

      if (token) {
        sendJson({ type: 'auth', token });
      }

      subscribeActive();
    });

    socket.addEventListener('message', (event) => {
      const payload = parseMessage(event);

      if (payload) {
        onEvent(payload);
      }
    });

    socket.addEventListener('close', () => {
      onStatus('closed');
      scheduleReconnect();
    });

    socket.addEventListener('error', () => {
      onStatus('error');
    });

    return true;
  }

  function start() {
    stopped = false;

    return connect();
  }

  function stop() {
    stopped = true;
    clearReconnect();

    if (socket) {
      socket.close();
      socket = null;
    }
  }

  function subscribe(rfqId) {
    subscribedRfqId = rfqId || null;
    subscribeActive();
  }

  function notify(payload = {}) {
    return sendJson({
      type: payload.type || 'chat.changed',
      channel: 'chat',
      rfq_id: payload.rfq_id || getActiveId(),
      payload,
    });
  }

  return {
    start,
    stop,
    subscribe,
    notify,
    isConnected: () => socket?.readyState === WebSocket.OPEN,
  };
}
