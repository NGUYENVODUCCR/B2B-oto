import { ProductAPI } from '../../api/product.js';
import { StatisticsAPI } from '../../api/statistics.js';
import { dashboardProductCardTemplate } from './templates.js';

let originalProducts = [];
let filteredProducts = [];
let lastDatasetSignature = '';
let lastRenderedSummarySignature = '';
let lastRenderedGridSignature = '';

function setText(id, value) {
  const element = document.getElementById(id);

  if (element) {
    element.textContent = value;
  }
}

function normalizeProduct(product) {
  return {
    id: product.id,
    name: product.name || 'Sản phẩm chưa có tên',
    description: product.description || '',
    brand: String(product.brand || product.brand_name || '').trim(),
    color: String(product.color || product.color_name || '').trim(),
    years: product.years ? String(product.years).trim() : '',
    price_from: product.price_from || 0,
    quantity: product.quantity || 1,
    status: product.status || 'active',
    company_id: product.company_id || product.company_info?.id || null,
    company_name: product.company_name || product.company_info?.company_name || 'Công ty đối tác',
    company_info: product.company_info || null,
    images: product.images || [],
  };
}

function setOptions(id, label, values, sorter = undefined) {
  const select = document.getElementById(id);

  if (!select) {
    return;
  }

  const options = Array.from(values)
    .sort(sorter)
    .map((value) => `<option value="${value}">${value}</option>`)
    .join('');

  const nextHtml = `<option value="">${label}</option>${options}`;

  if (select.innerHTML !== nextHtml) {
    select.innerHTML = nextHtml;
  }
}

function buildDynamicSelectors(list) {
  const brands = new Set();
  const colors = new Set();
  const years = new Set();
  const companies = new Set();

  list.forEach((product) => {
    if (product.brand) brands.add(product.brand);
    if (product.color) colors.add(product.color);
    if (product.years) years.add(product.years);
    if (product.company_name) companies.add(product.company_name);
  });

  setOptions('filterBrand', '-- Tất cả hãng --', brands);
  setOptions('filterColor', '-- Tất cả màu --', colors);
  setOptions('filterYears', '-- Tất cả năm --', years, (a, b) => Number(b) - Number(a));
  setOptions('filterCompany', '-- Tất cả công ty --', companies);
}

function getFilterValue(id) {
  return (document.getElementById(id)?.value || '').toLowerCase().trim();
}

function productSignature(product) {
  const firstImage = Array.isArray(product.images) && product.images.length
    ? (typeof product.images[0] === 'object' ? product.images[0].url || product.images[0].image_url || '' : product.images[0])
    : '';

  return [
    product.id ?? '',
    product.name ?? '',
    product.description ?? '',
    product.brand ?? '',
    product.color ?? '',
    product.years ?? '',
    Number(product.price_from || 0),
    Number(product.quantity || 0),
    String(product.status || ''),
    product.company_id ?? '',
    product.company_name ?? '',
    Number(product.review_count || 0),
    Number(product.avg_rating || 0),
    String(firstImage || ''),
  ].join('||');
}

function listSignature(list) {
  return list.map(productSignature).join('@@');
}

function datasetSignature(list) {
  const sorted = [...list].sort((a, b) => {
    const left = String(a.id ?? '');
    const right = String(b.id ?? '');

    return left.localeCompare(right, 'en');
  });

  return listSignature(sorted);
}

export function getDashboardProduct(productId) {
  return originalProducts.find((product) => String(product.id) === String(productId));
}

export function renderDashboardProducts() {
  const grid = document.getElementById('productGrid');

  if (!grid) {
    return;
  }

  const activeProducts = filteredProducts.filter((product) => (
    String(product.status || '').toLowerCase() === 'active'
  ));
  const activeOriginalProducts = originalProducts.filter((product) => (
    String(product.status || '').toLowerCase() === 'active'
  ));
  const companies = new Set(activeOriginalProducts.map((product) => product.company_name).filter(Boolean));

  const summarySignature = [
    activeOriginalProducts.length,
    companies.size,
    activeProducts.length,
  ].join('|');

  if (summarySignature !== lastRenderedSummarySignature) {
    setText('dashboardProductCount', activeOriginalProducts.length.toLocaleString('vi-VN'));
    setText('dashboardCompanyCount', companies.size.toLocaleString('vi-VN'));
    setText('dashboardFilteredCount', activeProducts.length.toLocaleString('vi-VN'));
    lastRenderedSummarySignature = summarySignature;
  }

  if (activeProducts.length === 0) {
    const emptySignature = 'EMPTY';

    if (lastRenderedGridSignature !== emptySignature) {
      grid.innerHTML = '<div class="empty-product dashboard-empty-state">Không tìm thấy xe phù hợp với bộ lọc.</div>';
      lastRenderedGridSignature = emptySignature;
    }

    return;
  }

  const signature = listSignature(activeProducts);

  if (signature === lastRenderedGridSignature) {
    return;
  }

  grid.innerHTML = activeProducts.map(dashboardProductCardTemplate).join('');
  lastRenderedGridSignature = signature;
}

export function executeDashboardFilter() {
  const searchVal = getFilterValue('searchInput');
  const nameVal = getFilterValue('filterName');
  const brandVal = document.getElementById('filterBrand')?.value.trim() || '';
  const colorVal = document.getElementById('filterColor')?.value.trim() || '';
  const yearsVal = document.getElementById('filterYears')?.value.trim() || '';
  const companyVal = document.getElementById('filterCompany')?.value.trim() || '';

  const priceMinVal = document.getElementById('filterPriceMin')?.value ? Number(document.getElementById('filterPriceMin').value) : null;
  const priceMaxVal = document.getElementById('filterPriceMax')?.value ? Number(document.getElementById('filterPriceMax').value) : null;

  filteredProducts = originalProducts.filter((product) => {
    if (searchVal) {
      const nameMatch = product.name.toLowerCase().includes(searchVal);
      const descMatch = product.description.toLowerCase().includes(searchVal);

      if (!nameMatch && !descMatch) return false;
    }

    if (nameVal && !product.name.toLowerCase().includes(nameVal)) return false;
    if (brandVal && product.brand !== brandVal) return false;
    if (colorVal && product.color !== colorVal) return false;
    if (yearsVal && product.years !== yearsVal) return false;
    if (companyVal && product.company_name !== companyVal) return false;

    const productPrice = Number(product.price_from || 0);

    if (priceMinVal !== null && productPrice < priceMinVal) return false;
    if (priceMaxVal !== null && productPrice > priceMaxVal) return false;

    return true;
  });

  renderDashboardProducts();
}

function currentFilterState() {
  return {
    searchInput: document.getElementById('searchInput')?.value || '',
    filterName: document.getElementById('filterName')?.value || '',
    filterBrand: document.getElementById('filterBrand')?.value || '',
    filterColor: document.getElementById('filterColor')?.value || '',
    filterYears: document.getElementById('filterYears')?.value || '',
    filterCompany: document.getElementById('filterCompany')?.value || '',
    filterPriceMin: document.getElementById('filterPriceMin')?.value || '',
    filterPriceMax: document.getElementById('filterPriceMax')?.value || '',
  };
}

function restoreFilterState(values) {
  Object.entries(values).forEach(([id, value]) => {
    const element = document.getElementById(id);

    if (element) {
      element.value = value;
    }
  });
}

export async function loadDashboardProducts(options = {}) {
  const { silent = false, throwOnError = false } = options;
  const loading = document.getElementById('loading');
  const filters = currentFilterState();

  try {
    if (loading && !silent) loading.style.display = 'block';

    const [rawProducts, reviewMap] = await Promise.all([
      ProductAPI.listForDashboard(),
      StatisticsAPI.productReviewCounts().catch(() => ({})),
    ]);

    const nextProducts = (Array.isArray(rawProducts) ? rawProducts : [])
      .map(normalizeProduct)
      .map((product) => {
        const stat = reviewMap?.[product.id] || {};

        return {
          ...product,
          review_count: Number(stat.review_count || 0),
          avg_rating: Number(stat.avg_rating || 0),
        };
      });

    const nextSignature = datasetSignature(nextProducts);

    if (nextSignature === lastDatasetSignature) {
      return;
    }

    originalProducts = nextProducts;
    filteredProducts = [...originalProducts];
    lastDatasetSignature = nextSignature;
    lastRenderedSummarySignature = '';

    buildDynamicSelectors(originalProducts);
    restoreFilterState(filters);
    executeDashboardFilter();
  } catch (error) {
    if (!silent) {
      console.error('[Dashboard] Product load failed', error);
    }

    const grid = document.getElementById('productGrid');

    if (grid && !silent) {
      grid.innerHTML = '<div class="empty-product dashboard-error-state">Không tải được sản phẩm từ hệ thống.</div>';
      lastRenderedGridSignature = 'ERROR';
    }

    if (throwOnError) {
      throw error;
    }
  } finally {
    if (loading && !silent) loading.style.display = 'none';
  }
}

export function initDashboardFilters() {
  const filterFields = [
    'searchInput',
    'filterName',
    'filterBrand',
    'filterColor',
    'filterYears',
    'filterCompany',
    'filterPriceMin',
    'filterPriceMax',
  ];

  filterFields.forEach((id) => {
    const element = document.getElementById(id);

    if (!element) {
      return;
    }

    element.addEventListener('input', executeDashboardFilter);
    element.addEventListener('change', executeDashboardFilter);
  });
}

export function initFilterPanelToggle() {
  const btn = document.getElementById('filterToggleBtn');
  const panel = document.getElementById('filterPanel');

  if (!btn || !panel) {
    return;
  }

  const setPanelOpen = (isOpen) => {
    panel.style.display = isOpen ? 'grid' : 'none';
    btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  };

  btn.addEventListener('click', (event) => {
    event.stopPropagation();
    setPanelOpen(panel.style.display !== 'grid');
  });

  document.addEventListener('click', (event) => {
    if (!panel.contains(event.target) && event.target !== btn) {
      setPanelOpen(false);
    }
  });
}
