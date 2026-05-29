import { StatisticsAPI } from '../../api/statistics.js';

const fallbackImage = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="220" height="140" viewBox="0 0 220 140"><rect width="220" height="140" fill="%23e8edf4"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="Arial" font-size="13" fill="%23718499">No image</text></svg>';

const elements = {
  form: document.getElementById('revenueFilterForm'),
  brand: document.getElementById('revenueBrandFilter'),
  dateFrom: document.getElementById('revenueDateFrom'),
  dateTo: document.getElementById('revenueDateTo'),
  scopeLabel: document.getElementById('revenueScopeLabel'),
  totalLabel: document.getElementById('revenueTotalLabel'),
  secondLabel: document.getElementById('revenueSecondLabel'),
  thirdLabel: document.getElementById('revenueThirdLabel'),
  fourthLabel: document.getElementById('revenueFourthLabel'),
  totalAmount: document.getElementById('revenueTotalAmount'),
  carsSold: document.getElementById('revenueCarsSold'),
  completedOrders: document.getElementById('revenueCompletedOrders'),
  commission: document.getElementById('revenueCommission'),
  chartTitle: document.getElementById('revenueChartTitle'),
  chartTotal: document.getElementById('revenueChartTotal'),
  chart: document.getElementById('revenueChart'),
  ordersCount: document.getElementById('revenueOrdersCount'),
  ordersList: document.getElementById('revenueOrdersList'),
};

let brandsLoaded = false;

function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function money(value) {
  return `${Number(value || 0).toLocaleString('vi-VN')} VND`;
}

function shortDate(value) {
  if (!value) {
    return '-';
  }

  const [date] = String(value).split(' ');
  const parts = date.split('-');

  return parts.length === 3 ? `${parts[2]}/${parts[1]}/${parts[0]}` : date;
}

function filterParams() {
  return {
    brand: elements.brand?.value || '',
    date_from: elements.dateFrom?.value || '',
    date_to: elements.dateTo?.value || '',
  };
}

function normalizeBundle(bundle) {
  if (!bundle) {
    return null;
  }

  return {
    summary: bundle.summary || bundle,
    chart: Array.isArray(bundle.chart) ? bundle.chart : [],
    orders: Array.isArray(bundle.orders) ? bundle.orders : [],
    brands: Array.isArray(bundle.brands) ? bundle.brands : [],
  };
}

function pickRevenueScope(payload) {
  if (!payload?.can_view_revenue) {
    return null;
  }

  if (payload.scope === 'seller' && payload.seller) {
    return {
      label: 'Doanh thu Seller',
      kind: 'seller',
      bundle: normalizeBundle(payload.seller),
    };
  }

  if (payload.admin) {
    return {
      label: payload.scope === 'admin' ? 'Doanh thu hệ thống' : 'Doanh thu',
      kind: 'admin',
      bundle: normalizeBundle(payload.admin),
    };
  }

  if (payload.seller) {
    return {
      label: 'Doanh thu Seller',
      kind: 'seller',
      bundle: normalizeBundle(payload.seller),
    };
  }

  return null;
}

function renderBrands(brands) {
  if (!elements.brand || brandsLoaded) {
    return;
  }

  const current = elements.brand.value;
  elements.brand.innerHTML = '<option value="">Tất cả</option>';

  brands.forEach((brand) => {
    const option = document.createElement('option');
    option.value = brand;
    option.textContent = brand;
    elements.brand.appendChild(option);
  });

  elements.brand.value = current;
  brandsLoaded = true;
}

function renderSummary(summary = {}, kind = 'seller') {
  const gmv = Number(summary.total_gmv || summary.total_revenue || 0);
  const platformFee = Number(summary.platform_fee_amount || summary.platform_commission || 0);
  const sellerPayout = Number(summary.seller_payout_amount || Math.max(0, gmv - platformFee));
  const carsSold = Number(summary.cars_sold || 0);
  const orders = Number(summary.total_orders || 0);

  if (kind === 'admin') {
    elements.totalLabel.textContent = 'Tổng GMV';
    elements.secondLabel.textContent = 'Xe đã bán';
    elements.thirdLabel.textContent = 'Đơn thành công';
    elements.fourthLabel.textContent = 'Hoa hồng nền tảng';
    elements.chartTitle.textContent = 'Biểu đồ doanh thu nền tảng';
    elements.totalAmount.textContent = money(gmv);
    elements.carsSold.textContent = `${carsSold.toLocaleString('vi-VN')} xe`;
    elements.completedOrders.textContent = `${orders.toLocaleString('vi-VN')} đơn`;
    elements.commission.textContent = money(platformFee);
    elements.chartTotal.textContent = money(platformFee);
    return;
  }

  elements.totalLabel.textContent = 'Doanh thu gộp';
  elements.secondLabel.textContent = 'Phí sàn đã trừ';
  elements.thirdLabel.textContent = 'Thực nhận';
  elements.fourthLabel.textContent = 'Đơn / xe đã bán';
  elements.chartTitle.textContent = 'Biểu đồ thực nhận';
  elements.totalAmount.textContent = money(gmv);
  elements.carsSold.textContent = money(platformFee);
  elements.completedOrders.textContent = money(sellerPayout);
  elements.commission.textContent = `${orders.toLocaleString('vi-VN')} đơn · ${carsSold.toLocaleString('vi-VN')} xe`;
  elements.chartTotal.textContent = money(sellerPayout);
}

function chartValue(row, kind) {
  const gmv = Number(row.total_gmv || row.total_revenue || 0);
  const platformFee = Number(row.platform_fee_amount || row.platform_commission || 0);

  if (kind === 'admin') {
    return platformFee;
  }

  return Number(row.seller_payout_amount || Math.max(0, gmv - platformFee));
}

function renderChart(rows, kind = 'seller') {
  if (!elements.chart) {
    return;
  }

  if (!rows.length) {
    elements.chart.innerHTML = '<div class="revenue-empty">Chưa có dữ liệu doanh thu trong khoảng lọc.</div>';
    return;
  }

  const width = 720;
  const height = 260;
  const pad = { top: 22, right: 24, bottom: 34, left: 52 };
  const chartWidth = width - pad.left - pad.right;
  const chartHeight = height - pad.top - pad.bottom;
  const values = rows.map((row) => chartValue(row, kind));
  const maxValue = Math.max(...values, 1);
  const points = rows.map((row, index) => {
    const x = pad.left + (rows.length === 1 ? chartWidth : (index / (rows.length - 1)) * chartWidth);
    const y = pad.top + chartHeight - ((chartValue(row, kind) / maxValue) * chartHeight);

    return { x, y, row };
  });
  const pointList = points.map((point) => `${point.x},${point.y}`).join(' ');
  const areaPath = `M ${pad.left} ${pad.top + chartHeight} L ${pointList} L ${pad.left + chartWidth} ${pad.top + chartHeight} Z`;
  const labelStep = Math.max(1, Math.ceil(rows.length / 5));
  const xLabels = points
    .filter((point, index) => index % labelStep === 0 || index === rows.length - 1)
    .map((point) => `<text class="axis-label" x="${point.x}" y="${height - 8}" text-anchor="middle">${shortDate(point.row.sale_day)}</text>`)
    .join('');
  const grid = [0, 1, 2, 3, 4].map((item) => {
    const y = pad.top + (chartHeight / 4) * item;
    const labelValue = maxValue - ((maxValue / 4) * item);

    return `
      <line class="grid-line" x1="${pad.left}" y1="${y}" x2="${width - pad.right}" y2="${y}"></line>
      <text class="axis-label" x="8" y="${y + 4}">${Math.round(labelValue / 1000000)}tr</text>
    `;
  }).join('');

  elements.chart.innerHTML = `
    <svg viewBox="0 0 ${width} ${height}" role="img" aria-label="Biểu đồ doanh thu">
      ${grid}
      <path d="${areaPath}" fill="rgba(37, 99, 235, .12)"></path>
      <polyline points="${pointList}" fill="none" stroke="#2563eb" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></polyline>
      ${points.map((point) => `<circle cx="${point.x}" cy="${point.y}" r="4" fill="#2563eb"></circle>`).join('')}
      ${xLabels}
    </svg>
  `;
}

function renderOrders(orders, kind = 'seller') {
  if (!elements.ordersList) {
    return;
  }

  elements.ordersCount.textContent = `${orders.length.toLocaleString('vi-VN')} đơn`;

  if (!orders.length) {
    elements.ordersList.innerHTML = '<div class="revenue-empty">Chưa có đơn hàng thành công.</div>';
    return;
  }

  elements.ordersList.innerHTML = orders.map((order) => {
    const name = order.product_name || `Đơn hàng #${order.id}`;
    const gmv = Number(order.gross_release_amount || order.paid_amount || order.total_amount || 0);
    const platformFee = Number(order.platform_fee_amount || 0);
    const sellerPayout = Number(order.seller_payout_amount || Math.max(0, gmv - platformFee));
    const amount = kind === 'admin' ? platformFee : sellerPayout;
    const image = order.image_url || fallbackImage;
    const metaLine = kind === 'admin'
      ? `GMV: ${money(gmv)} · Seller nhận: ${money(sellerPayout)}`
      : `Doanh thu gộp: ${money(gmv)} · Phí sàn: ${money(platformFee)}`;

    return `
      <article class="revenue-order-card">
        <img src="${escapeHtml(image)}" alt="${escapeHtml(name)}">
        <div>
          <div class="revenue-order-code">#DH${String(order.id).padStart(4, '0')}</div>
          <h3>${escapeHtml(name)}</h3>
          <p>Ngày mở: ${shortDate(order.revenue_date || order.created_at)}</p>
          <p>Năm xe: ${escapeHtml(order.years || '-')} · ${Number(order.quantity || 0).toLocaleString('vi-VN')} xe</p>
          <p>${escapeHtml(metaLine)}</p>
        </div>
        <div class="revenue-order-amount">${money(amount)}</div>
      </article>
    `;
  }).join('');
}

async function loadRevenue() {
  elements.ordersList.innerHTML = '<div class="revenue-empty">Đang tải doanh thu...</div>';

  try {
    const payload = await StatisticsAPI.revenue(filterParams());
    const scope = pickRevenueScope(payload);

    if (!scope?.bundle) {
      elements.scopeLabel.textContent = 'Không có quyền truy cập';
      elements.ordersList.innerHTML = '<div class="revenue-error">Tài khoản này chưa có quyền xem doanh thu.</div>';
      return;
    }

    elements.scopeLabel.textContent = scope.label;
    renderBrands(scope.bundle.brands);
    renderSummary(scope.bundle.summary, scope.kind);
    renderChart(scope.bundle.chart, scope.kind);
    renderOrders(scope.bundle.orders, scope.kind);
  } catch (error) {
    elements.scopeLabel.textContent = 'Lỗi dữ liệu';
    elements.ordersList.innerHTML = `<div class="revenue-error">Không tải được doanh thu: ${escapeHtml(error.message)}</div>`;
  }
}

function setRange(days) {
  if (!elements.dateFrom || !elements.dateTo) {
    return;
  }

  const today = new Date();
  const from = new Date();
  from.setDate(today.getDate() - Number(days) + 1);

  elements.dateTo.value = today.toISOString().slice(0, 10);
  elements.dateFrom.value = from.toISOString().slice(0, 10);
}

document.querySelectorAll('[data-revenue-range]').forEach((button) => {
  button.addEventListener('click', () => {
    document.querySelectorAll('[data-revenue-range]').forEach((item) => item.classList.remove('is-active'));
    button.classList.add('is-active');

    if (button.dataset.revenueRange !== '365') {
      setRange(button.dataset.revenueRange);
    }

    loadRevenue();
  });
});

elements.form?.addEventListener('submit', (event) => {
  event.preventDefault();
  loadRevenue();
});

loadRevenue();
