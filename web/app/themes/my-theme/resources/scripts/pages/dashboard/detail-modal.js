import { ProductAPI } from '../../api/product.js';
import { formatCurrency } from '../../utils/format.js';
import { getHomeUrl } from '../../api/http.js';
import {
  companyLogoTemplate,
  dashboardDetailModalTemplate,
  productImagesTemplate,
} from './templates.js';

function setText(id, value) {
  const element = document.getElementById(id);

  if (element) {
    element.innerText = value ?? '';
  }
}

function closeDetailModal() {
  const modal = document.getElementById('dashboard-detail-modal');

  if (modal) {
    modal.style.display = 'none';
  }
}

function ensureDetailModal() {
  if (document.getElementById('dashboard-detail-modal')) {
    return;
  }

  document.body.insertAdjacentHTML('beforeend', dashboardDetailModalTemplate());
  document.getElementById('close-dashboard-modal-btn')?.addEventListener('click', closeDetailModal);
  document.getElementById('dashboard-modal-close-btn')?.addEventListener('click', closeDetailModal);
}

function renderCompany(companyObj = {}) {
  setText('dashboard-company-name', companyObj.company_name || companyObj.name || 'Công ty đối tác');
  setText('dashboard-company-rep', companyObj.representative_name || companyObj.contact_name || 'Chưa cập nhật');
  setText('dashboard-company-tax', companyObj.tax_code || companyObj.tax_no || 'Chưa cập nhật');
  setText('dashboard-company-address', companyObj.address || 'Chưa cập nhật');

  const logoContainer = document.getElementById('dashboard-company-logo-container');
  const logoBox = document.getElementById('dashboard-company-logo-box');
  const docs = Array.isArray(companyObj.documents) ? companyObj.documents : [];
  const logoDoc = docs.find((doc) => {
    const value = typeof doc === 'object' ? `${doc.label || ''} ${doc.url || doc.image_url || ''}` : doc;

    return String(value || '').toLowerCase().includes('logo');
  }) || docs[docs.length - 1];

  if (!logoContainer || !logoBox) {
    return;
  }

  if (!logoDoc) {
    logoContainer.innerHTML = '';
    logoBox.style.display = 'none';
    return;
  }

  const logoUrl = typeof logoDoc === 'object' ? logoDoc.url || logoDoc.image_url : logoDoc;
  logoContainer.innerHTML = logoUrl ? companyLogoTemplate(logoUrl) : '';
  logoBox.style.display = logoUrl ? 'block' : 'none';
}

async function openDetailModal(productId, backupProduct = null) {
  ensureDetailModal();

  const modal = document.getElementById('dashboard-detail-modal');
  const loadingBox = document.getElementById('dashboard-modal-loading-box');
  const mainContent = document.getElementById('dashboard-modal-main-content');
  const gallerySection = document.getElementById('dashboard-modal-gallery-section');

  if (!modal || !loadingBox || !mainContent || !gallerySection) {
    return;
  }

  loadingBox.style.display = 'block';
  mainContent.style.display = 'none';
  gallerySection.style.display = 'none';
  modal.style.display = 'block';

  try {
    const result = await ProductAPI.detail(productId);
    const product = result.product || backupProduct || {};
    const productImages = result.images || [];
    const companyObj = result.company || product.company_info || backupProduct?.company_info || {};

    setText('dashboard-modal-prod-name', product.name || 'Không có tên');
    setText('dashboard-modal-prod-price', formatCurrency(product.price_from));
    setText('modal-prod-brand', product.brand || product.brand_name || 'Chưa cập nhật');
    setText('modal-prod-color', product.color || product.color_name || 'Chưa cập nhật');
    setText('dashboard-modal-prod-years', product.years || 'Chưa cập nhật');
    setText('dashboard-modal-prod-quantity', `${product.quantity || 0} xe`);
    setText('dashboard-modal-prod-desc', product.description || '');

    renderCompany(companyObj);

    const imagesGrid = document.getElementById('dashboard-images-grid');

    if (imagesGrid) {
      imagesGrid.innerHTML = productImages.length > 0 ? productImagesTemplate(productImages) : '';
    }

    loadingBox.style.display = 'none';
    mainContent.style.display = 'block';
    gallerySection.style.display = 'block';
  } catch (error) {
    console.error('[Dashboard] Product detail failed', error);
    loadingBox.style.display = 'none';
    mainContent.style.display = 'block';
  }
}

export function initDashboardDetailModal({ getProduct, getProfile }) {
  const grid = document.getElementById('productGrid');

  if (!grid) {
    return;
  }

  grid.addEventListener('click', async (event) => {
    if (event.target.closest('[data-flow-contact]')) {
      return;
    }

    const target = event.target.closest('[data-dashboard-detail], .dashboard-clickable-detail');

    if (!target) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();

    const productId = target.dataset.id || target.closest('.dashboard-clickable-detail')?.dataset.id;

    if (!productId) {
      return;
    }

    const profile = typeof getProfile === 'function'
      ? await getProfile()
      : null;

    if (!profile) {
      if (confirm('Bạn cần đăng nhập để xem chi tiết sản phẩm. Chuyển tới trang đăng nhập?')) {
        window.location.href = getHomeUrl('/login');
      }

      return;
    }

    ensureDetailModal();
    openDetailModal(productId, getProduct(productId));
  });
}