import { OrderAPI } from '../api/order.js';

const statusText = {
  pending: 'Chờ thanh toán',
  paid: 'Đã thanh toán',
  delivering: 'Đang giao hàng',
  completed: 'Hoàn tất',
  cancelled: 'Đã hủy',
};

const paymentStatusText = {
  pending: 'Chờ thanh toán',
  paid: 'Đã thanh toán',
  released: 'Đã giải ngân',
  failed: 'Thất bại',
  refunded: 'Đã hoàn tiền',
  unpaid: 'Chưa thanh toán',
};

const state = {
  search: '',
  loading: false,
  requestId: 0,
};

let searchDebounce = null;

function escapeHtml(value) {
  const div = document.createElement('div');
  div.textContent = value ?? '';
  return div.innerHTML;
}

function formatCurrency(value) {
  return `${Number(value || 0).toLocaleString('vi-VN')} VND`;
}

function formatDate(value) {
  if (!value) return '-';

  const date = new Date(String(value).replace(' ', 'T'));

  if (Number.isNaN(date.getTime())) {
    return String(value);
  }

  return date.toLocaleString('vi-VN');
}

function currentCompanyId() {
  return Number(
    window.B2B_PROFILE?.company?.id ||
    window.B2B_PROFILE?.company_id ||
    window.B2B_PROFILE?.company_member?.company_id ||
    localStorage.getItem('company_id') ||
    0,
  );
}

function normalizeRoles(raw) {
  if (!raw) return [];

  const pushRole = (target, value) => {
    const role = String(value || '').toUpperCase().trim();

    if (!role) return;

    target.add(role);

    if (role.startsWith('ROLE_')) {
      target.add(role.slice(5));
    } else {
      target.add(`ROLE_${role}`);
    }
  };

  const output = new Set();

  const walk = (value) => {
    if (Array.isArray(value)) {
      value.forEach(walk);
      return;
    }

    if (value && typeof value === 'object') {
      walk(value.role_name || value.name || value.slug || value.role || '');
      return;
    }

    const str = String(value || '').trim();

    if (!str) return;

    if (str.startsWith('[')) {
      try {
        walk(JSON.parse(str));
        return;
      } catch (error) {
      }
    }

    str.split(',').map((item) => item.trim()).filter(Boolean).forEach((item) => {
      pushRole(output, item);
    });
  };

  walk(raw);

  return Array.from(output);
}

function hasSupportRole() {
  const rawRoles = localStorage.getItem('user_cached_role') || '';
  const roles = normalizeRoles(rawRoles);

  return roles.includes('SUPPORT') || roles.includes('ROLE_SUPPORT') || roles.includes('ADMIN') || roles.includes('ROLE_ADMIN') || roles.includes('ADMINISTRATOR');
}

function paymentStatusLabel(status) {
  const key = String(status || 'unpaid').toLowerCase();
  return paymentStatusText[key] || key;
}

function renderOrder(order) {
  const status = String(order.status || order.order_status || 'pending').toLowerCase();

  const companyId = currentCompanyId();
  const buyerCompanyId = Number(order.buyer_company_id || 0);
  const sellerCompanyId = Number(order.seller_company_id || 0);

  const isCurrentBuyer = companyId > 0 && companyId === buyerCompanyId;
  const isCurrentSeller = companyId > 0 && companyId === sellerCompanyId;

  const buyerName = order.buyer_company_name || `Company #${buyerCompanyId || '-'}`;
  const sellerName = order.seller_company_name || `Company #${sellerCompanyId || '-'}`;
  const rfqId = Number(order.rfq_id || 0);
  const bulkId = Number(order.bulk_id || 0);
  const contractId = Number(order.contract_id || 0);
  const paymentStatus = String(order.payment_status || 'unpaid').toLowerCase();

  return `
    <article class="order-card">
      <div class="order-head">
        <h3>Đơn hàng #${escapeHtml(order.id)}</h3>
        <span class="order-status" data-status="${escapeHtml(status)}">${escapeHtml(statusText[status] || status)}</span>
      </div>

      <div class="order-grid">
        <div><span>RFQ</span><strong>${rfqId > 0 ? `#${escapeHtml(rfqId)}` : '-'}</strong></div>
        <div><span>Bulk</span><strong>${bulkId > 0 ? `#${escapeHtml(bulkId)}` : '-'}</strong></div>
        <div><span>Contract</span><strong>${contractId > 0 ? `#${escapeHtml(contractId)}` : '-'}</strong></div>
        <div><span>Tổng tiền</span><strong>${escapeHtml(formatCurrency(order.total_amount || 0))}</strong></div>
        <div><span>Buyer</span><strong>${escapeHtml(buyerName)} (ID ${escapeHtml(buyerCompanyId || '-')})</strong></div>
        <div><span>Seller</span><strong>${escapeHtml(sellerName)} (ID ${escapeHtml(sellerCompanyId || '-')})</strong></div>
        <div><span>Payment</span><strong>${escapeHtml(paymentStatusLabel(paymentStatus))}</strong></div>
        <div><span>Số tiền thanh toán</span><strong>${escapeHtml(formatCurrency(order.payment_amount || order.total_amount || 0))}</strong></div>
        <div><span>Ngày tạo</span><strong>${escapeHtml(formatDate(order.created_at || order.order_created_at))}</strong></div>
        <div><span>Cập nhật</span><strong>${escapeHtml(formatDate(order.updated_at || order.order_updated_at))}</strong></div>
      </div>

      <p class="order-products"><strong>Sản phẩm:</strong> ${escapeHtml(order.product_names || '-')}</p>

      <div class="order-actions">
        ${isCurrentSeller && status === 'paid'
          ? `<button data-action="deliver" data-id="${escapeHtml(order.id)}">Xác nhận giao hàng</button>`
          : ''}

        ${isCurrentBuyer && status === 'delivering'
          ? `<button data-action="complete" data-id="${escapeHtml(order.id)}">Đã nhận hàng</button>`
          : ''}
      </div>
    </article>
  `;
}

function setSearchMeta(total) {
  const meta = document.getElementById('ordersSearchMeta');
  if (!meta) return;

  const search = state.search.trim();

  if (!search) {
    const roleText = hasSupportRole() ? 'Đang hiển thị toàn bộ đơn hàng.' : 'Đang hiển thị đơn hàng của công ty bạn.';
    meta.textContent = roleText;
    return;
  }

  meta.textContent = `Tìm thấy ${total} đơn hàng với từ khóa: "${search}"`;
}

async function loadOrders(options = {}) {
  const box = document.getElementById('ordersTrackingList');
  if (!box || state.loading) return;

  state.loading = true;
  const requestId = ++state.requestId;

  if (!options.silent) {
    box.innerHTML = '<div class="empty-order">Đang tải đơn hàng...</div>';
  }

  try {
    const orders = await OrderAPI.list({ search: state.search.trim() });

    if (requestId !== state.requestId) {
      return;
    }

    const rows = Array.isArray(orders) ? orders : [];

    setSearchMeta(rows.length);

    if (rows.length === 0) {
      box.innerHTML = state.search.trim()
        ? '<div class="empty-order">Không tìm thấy đơn hàng phù hợp.</div>'
        : '<div class="empty-order">Chưa có đơn hàng nào.</div>';
      return;
    }

    box.innerHTML = rows.map(renderOrder).join('');
  } catch (error) {
    box.innerHTML = `<div class="text-danger">Không tải được đơn hàng: ${escapeHtml(error.message || 'Lỗi hệ thống')}</div>`;
  } finally {
    state.loading = false;
  }
}

function applySearchFromInput() {
  const input = document.getElementById('ordersSearchInput');
  state.search = String(input?.value || '').trim();
  loadOrders();
}

document.addEventListener('click', async (event) => {
  const actionButton = event.target.closest('[data-action]');

  if (actionButton) {
    const id = actionButton.dataset.id;
    const action = actionButton.dataset.action;

    try {
      if (action === 'deliver') {
        await OrderAPI.markDelivering(id);
      }

      if (action === 'complete') {
        await OrderAPI.complete(id);
      }

      await loadOrders();
    } catch (error) {
      alert(error.message || 'Không cập nhật được đơn hàng.');
    }

    return;
  }

  const searchBtn = event.target.closest('#ordersSearchBtn');
  if (searchBtn) {
    applySearchFromInput();
    return;
  }

  const resetBtn = event.target.closest('#ordersSearchResetBtn');
  if (resetBtn) {
    const input = document.getElementById('ordersSearchInput');
    if (input) input.value = '';
    state.search = '';
    await loadOrders();
  }
});

document.getElementById('ordersSearchInput')?.addEventListener('keydown', (event) => {
  if (event.key === 'Enter') {
    event.preventDefault();
    applySearchFromInput();
  }
});

document.getElementById('ordersSearchInput')?.addEventListener('input', () => {
  if (searchDebounce) {
    clearTimeout(searchDebounce);
  }

  searchDebounce = setTimeout(() => {
    applySearchFromInput();
  }, 280);
});

setSearchMeta(0);
loadOrders();
