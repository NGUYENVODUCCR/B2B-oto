const SOURCE_ID = `${Date.now()}-${Math.random().toString(36).slice(2)}`;
const STORAGE_PREFIX = 'b2b:rt:';
const channels = new Map();

function getChannel(eventName) {
  if (!('BroadcastChannel' in window)) {
    return null;
  }

  if (!channels.has(eventName)) {
    channels.set(eventName, new BroadcastChannel(`b2b:${eventName}`));
  }

  return channels.get(eventName);
}

function normalizedDetail(detail = {}) {
  if (detail && typeof detail === 'object' && !Array.isArray(detail)) {
    return detail;
  }

  return { value: detail };
}

export function emitRealtimeEvent(eventName, detail = {}) {
  if (!eventName) {
    return;
  }

  const payload = {
    ...normalizedDetail(detail),
    source_id: SOURCE_ID,
    sent_at: Date.now(),
  };

  window.dispatchEvent(new CustomEvent(eventName, { detail: payload }));

  try {
    getChannel(eventName)?.postMessage(payload);
  } catch (error) {
  
  }

  try {
    localStorage.setItem(`${STORAGE_PREFIX}${eventName}`, JSON.stringify(payload));
  } catch (error) {
   
  }
}

export function listenRealtimeEvent(eventName, handler) {
  if (!eventName || typeof handler !== 'function') {
    return () => {};
  }

  const localHandler = (event) => handler(event.detail || {});
  window.addEventListener(eventName, localHandler);

  const channel = getChannel(eventName);
  const channelHandler = (event) => {
    const payload = event.data || {};

    if (payload.source_id === SOURCE_ID) {
      return;
    }

    handler(payload);
  };

  if (channel) {
    channel.addEventListener('message', channelHandler);
  }

  const storageHandler = (event) => {
    if (event.key !== `${STORAGE_PREFIX}${eventName}` || !event.newValue) {
      return;
    }

    try {
      const payload = JSON.parse(event.newValue);

      if (payload.source_id !== SOURCE_ID) {
        handler(payload);
      }
    } catch (error) {
     
    }
  };

  window.addEventListener('storage', storageHandler);

  return () => {
    window.removeEventListener(eventName, localHandler);
    window.removeEventListener('storage', storageHandler);

    if (channel) {
      channel.removeEventListener('message', channelHandler);
    }
  };
}

export function createRealtimeLoop(options = {}) {
  const {
    run,
    interval = 10000,
    maxInterval = Math.max(interval * 4, 30000),
    visibleOnly = true,
    immediate = true,
    canRun = () => true,
    eventName = '',
    onError = null,
  } = options;

  if (typeof run !== 'function') {
    throw new Error('createRealtimeLoop requires a run function.');
  }

  let stopped = true;
  let timer = null;
  let inFlight = false;
  let failureCount = 0;
  let unlistenEvent = null;

  const clearTimer = () => {
    if (timer) {
      window.clearTimeout(timer);
      timer = null;
    }
  };

  const isVisible = () => !visibleOnly || document.visibilityState === 'visible';

  const nextDelay = () => {
    if (!failureCount) {
      return interval;
    }

    return Math.min(interval * (2 ** Math.min(failureCount, 4)), maxInterval);
  };

  const schedule = () => {
    clearTimer();

    if (stopped) {
      return;
    }

    timer = window.setTimeout(() => {
      refresh('interval');
    }, nextDelay());
  };

  async function refresh(reason = 'manual', force = false) {
    if (stopped || inFlight) {
      return;
    }

    if (!force && !isVisible()) {
      schedule();
      return;
    }

    if (!canRun(reason)) {
      schedule();
      return;
    }

    inFlight = true;

    try {
      await run({ reason });
      failureCount = 0;
    } catch (error) {
      failureCount += 1;

      if (typeof onError === 'function') {
        onError(error);
      }
    } finally {
      inFlight = false;
      schedule();
    }
  }

  const handleVisible = () => {
    if (document.visibilityState === 'visible') {
      refresh('visible', true);
    }
  };

  const handleFocus = () => refresh('focus', true);

  function start() {
    if (!stopped) {
      return;
    }

    stopped = false;
    document.addEventListener('visibilitychange', handleVisible);
    window.addEventListener('focus', handleFocus);

    if (eventName) {
      unlistenEvent = listenRealtimeEvent(eventName, () => refresh('event', true));
    }

    if (immediate) {
      refresh('start', true);
      return;
    }

    schedule();
  }

  function stop() {
    stopped = true;
    clearTimer();
    document.removeEventListener('visibilitychange', handleVisible);
    window.removeEventListener('focus', handleFocus);

    if (unlistenEvent) {
      unlistenEvent();
      unlistenEvent = null;
    }
  }

  return {
    start,
    stop,
    refresh,
    isInFlight: () => inFlight,
  };
}
