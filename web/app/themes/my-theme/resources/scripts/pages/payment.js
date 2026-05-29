import { manualCheckout, getPaymentByOrder } from '../api/payment.js';
import { createRealtimeLoop, emitRealtimeEvent } from '../utils/realtime.js';

function formatMoney(value) {
  return `${Number(value || 0).toLocaleString('vi-VN')} VND`;
}

function escapeHtml(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function paymentStatusLabel(status) {
  const normalized = String(status || 'pending').toLowerCase();
  const labels = {
    pending: 'Chờ xác nhận',
    escrow: 'Đã vào escrow',
    paid: 'Đã thanh toán',
    released: 'Đã giải ngân',
    failed: 'Thất bại/đã hoàn tiền',
  };

  return labels[normalized] || status || 'Chờ xác nhận';
}

function bankLogos() {
  return `
    <div class="payment-bank-logos" aria-label="Ngân hàng hỗ trợ">
      <span>MB Bank</span>
      <span>Vietcombank</span>
      <span>MB Bank</span>
      <span>ACB</span>
    </div>
  `;
}

function renderStatus(payment) {
  const statusBox = document.getElementById('payment-status');

  if (!statusBox) {
    return;
  }

  if (!payment) {
    statusBox.innerHTML = '<p class="payment-status pending">Chưa có thanh toán cho order này.</p>';
    return;
  }

  const status = payment.payment_status || payment.status || 'pending';
  statusBox.innerHTML = `
    <p class="payment-status ${escapeHtml(status)}">
      <b>Trạng thái:</b> ${escapeHtml(paymentStatusLabel(status))}
    </p>
  `;

  if (['escrow', 'paid', 'released', 'failed'].includes(String(status).toLowerCase())) {
    emitRealtimeEvent('b2b:payment:changed', {
      order_id: Number(payment.order_id || 0),
      payment_id: Number(payment.id || payment.payment_id || 0),
      status,
    });
    emitRealtimeEvent('b2b:wallet:changed', {
      source: 'payment_page',
      order_id: Number(payment.order_id || 0),
    });
    emitRealtimeEvent('b2b:chat:changed', {
      order_id: Number(payment.order_id || 0),
      type: 'payment_status',
    });
  }
}

async function refreshPaymentStatus(orderId) {
  const payment = await getPaymentByOrder(orderId);
  renderStatus(payment);

  return payment;
}

function renderCheckout(data) {
  const box = document.getElementById('payment-box');
  const qrImage = data.vietqr_url || data.qr_image || '';

  if (!box) {
    return;
  }

  box.innerHTML = `
    <section class="payment-card">
      <div class="payment-card-head">
        <div>
          <p class="payment-kicker">Thanh toán escrow</p>
          <h1>Đơn hàng #${escapeHtml(data.order_id)}</h1>
        </div>
        <strong>${formatMoney(data.amount)}</strong>
      </div>

      ${bankLogos()}

      <dl class="payment-info">
        <div><dt>Ngân hàng</dt><dd>${escapeHtml(data.bank_name)}</dd></div>
        <div><dt>Số tài khoản</dt><dd>${escapeHtml(data.bank_account_number)}</dd></div>
        <div><dt>Chủ tài khoản</dt><dd>${escapeHtml(data.bank_account_name)}</dd></div>
        <div><dt>Nội dung chuyển khoản</dt><dd class="payment-code">${escapeHtml(data.transfer_content)}</dd></div>
      </dl>

      ${qrImage ? `
        <div class="payment-qr">
          <img src="${escapeHtml(qrImage)}" alt="QR thanh toán">
        </div>
      ` : ''}

      <p class="payment-note">${escapeHtml(data.note || 'Sau khi chuyển khoản, admin/support xác nhận để tiền vào escrow.')}</p>

      <div class="payment-actions">
        <button type="button" id="check-payment-status">Kiểm tra trạng thái</button>
      </div>

      <div id="payment-status" class="payment-status-box"></div>
    </section>
  `;
}

async function initPaymentPage() {
  if (window.__B2B_PAYMENT_PAGE_BOOTED__) {
    return;
  }

  window.__B2B_PAYMENT_PAGE_BOOTED__ = true;

  const paymentPage = document.getElementById('payment-page');

  if (!paymentPage) {
    return;
  }

  const orderId = new URLSearchParams(window.location.search).get('order_id');
  const box = document.getElementById('payment-box');

  if (!box) {
    return;
  }

  if (!orderId) {
    box.innerHTML = '<p class="payment-error">Thiếu order_id.</p>';
    return;
  }

  try {
    box.innerHTML = '<p class="payment-loading">Đang tạo thông tin thanh toán...</p>';
    renderCheckout(await manualCheckout(orderId));

    document.getElementById('check-payment-status')?.addEventListener('click', () => {
      refreshPaymentStatus(orderId).catch((error) => {
        renderStatus({ payment_status: error.message || 'pending' });
      });
    });

    await refreshPaymentStatus(orderId).catch(() => null);

    createRealtimeLoop({
      interval: 5000,
      maxInterval: 20000,
      eventName: 'b2b:payment:changed',
      immediate: false,
      run: () => refreshPaymentStatus(orderId),
    }).start();
  } catch (error) {
    const paid = await refreshPaymentStatus(orderId).catch(() => null);

    if (paid) {
      renderCheckout({
        order_id: orderId,
        amount: paid.amount,
        bank_name: 'Đã thanh toán',
        bank_account_number: '',
        bank_account_name: '',
        transfer_content: paid.gateway_ref || '',
        note: error.message || '',
      });
      renderStatus(paid);
      return;
    }

    box.innerHTML = `<p class="payment-error">${escapeHtml(error.message || 'Không thể tạo thông tin thanh toán.')}</p>`;
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initPaymentPage);
} else {
  initPaymentPage();
}
