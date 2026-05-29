import { UserAPI } from '../api/user.js';
import { normalizeRoles } from '../utils/format.js';
import { createRealtimeLoop } from '../utils/realtime.js';
import {
  getDashboardProduct,
  initDashboardFilters,
  initFilterPanelToggle,
  loadDashboardProducts,
} from './dashboard/catalog.js';
import { initDashboardDetailModal } from './dashboard/detail-modal.js';
import { initContactSeller } from './dashboard/contact-seller.js';

let currentProfile = null;
let productRefreshRunning = false;
let productFailureCount = 0;
let productCooldownUntil = 0;

async function loadProfile() {
  if (currentProfile) {
    return currentProfile;
  }

  try {
    currentProfile = await UserAPI.getProfile();
    hydrateSellerButtons(currentProfile);
    return currentProfile;
  } catch (error) {
    currentProfile = null;
    return null;
  }
}

function hydrateSellerButtons(profile) {
  const roles = normalizeRoles(profile?.roles || profile?.role || profile?.user?.roles || []);

  if (roles.includes('ROLE_SELLER') || roles.includes('SELLER')) {
    document.getElementById('createProductBtn')?.classList.remove('hidden');
    document.getElementById('registerSellerBtn')?.classList.add('hidden');
  }
}

async function initDashboardPage() {
  const forceBoot = window.__B2B_DASHBOARD_FORCE_BOOT__ === true;

  if (window.__B2B_DASHBOARD_BOOTED__ && !forceBoot) {
    return;
  }

  const productGrid = document.getElementById('productGrid');

  if (!productGrid) {
    return;
  }

  window.__B2B_DASHBOARD_BOOTED__ = true;
  window.__B2B_DASHBOARD_FORCE_BOOT__ = false;

  initFilterPanelToggle();
  initDashboardFilters();
  initDashboardDetailModal({
  getProduct: getDashboardProduct,
  getProfile: loadProfile,
});
  initContactSeller({
    getProfile: loadProfile,
    getProduct: getDashboardProduct,
  });

  await loadProfile();
  await loadDashboardProducts();

  const productLoop = createRealtimeLoop({
    interval: 15000,
    maxInterval: 45000,
    eventName: 'b2b:products:changed',
    immediate: false,
    run: async () => {
      if (Date.now() < productCooldownUntil) {
        return;
      }

      if (productRefreshRunning) {
        return;
      }

      productRefreshRunning = true;

      try {
        await loadDashboardProducts({
          silent: true,
          throwOnError: true,
        });
        productFailureCount = 0;
        productCooldownUntil = 0;
      } catch (error) {
        productFailureCount += 1;

        if (
          Number(error?.status || 0) >= 500 &&
          productFailureCount >= 3
        ) {
          productCooldownUntil = Date.now() + (5 * 60 * 1000);
        }

        throw error;
      } finally {
        productRefreshRunning = false;
      }
    },
  });

  productLoop.start();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initDashboardPage);
} else {
  initDashboardPage();
}
