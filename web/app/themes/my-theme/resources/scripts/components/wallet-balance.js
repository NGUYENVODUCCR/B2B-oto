import { createRealtimeLoop } from '../utils/realtime.js';
import { WalletAPI } from '../api/wallet.js';
let walletFailureCount = 0;
let walletCooldownUntil = 0;

function isSupportWorkspacePage() {
  const page = String(document.body?.dataset?.page || '').toLowerCase().trim();
  const path = String(window.location?.pathname || '').toLowerCase();

  return page === 'support' || page === 'support-workspace' || path.includes('/support-workspace');
}

function canSyncWalletBalance() {
  const node = document.getElementById('navWalletBalance');
  const token = localStorage.getItem('access_token');
  const walletLink = node?.closest('.navbar-wallet-link');

  if (isSupportWorkspacePage()) {
    return false;
  }

  if (!node || !token) {
    return false;
  }

  if (walletLink?.hidden || walletLink?.hasAttribute('hidden')) {
    return false;
  }

  return true;
}

function walletFormatCurrency(value) {
  return `${Number(value || 0).toLocaleString('vi-VN')} VND`;
}

async function syncDirectWalletBalance() {
  if (Date.now() < walletCooldownUntil) {
    return;
  }

  const node = document.getElementById('navWalletBalance');
  const token = localStorage.getItem('access_token');

  if (!node || !token || !canSyncWalletBalance()) {
    return;
  }

  try {
    const result = await WalletAPI.balance();
    node.textContent = walletFormatCurrency(result?.balance || 0);
    walletFailureCount = 0;
    walletCooldownUntil = 0;
  } catch (error) {
    walletFailureCount += 1;

    if (
      Number(error?.status || 0) >= 500 &&
      walletFailureCount >= 3
    ) {
      walletCooldownUntil = Date.now() + (5 * 60 * 1000);
    }

    if (!node.textContent.trim()) {
      node.textContent = '0 VND';
    }

    throw error;
  }
}

if (!window.__B2B_WALLET_BALANCE_BOOTED__) {
  window.__B2B_WALLET_BALANCE_BOOTED__ = true;
  const loop = createRealtimeLoop({
    interval: 20000,
    maxInterval: 120000,
    eventName: 'b2b:wallet:changed',
    canRun: canSyncWalletBalance,
    run: syncDirectWalletBalance,
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      if (canSyncWalletBalance()) {
        loop.start();
      }
    });
  } else {
    if (canSyncWalletBalance()) {
      loop.start();
    }
  }
}
