import { getHomeUrl } from '../api/http.js';
import { WalletAPI } from '../api/wallet.js';
import { escapeHtml, formatCurrency } from '../utils/format.js';
import { createRealtimeLoop, emitRealtimeEvent } from '../utils/realtime.js';

const state = {
  bank: 'vietcombank',
  loading: false,
  activeDepositCode: '',
};

function qs(id) {
  return document.getElementById(id);
}

function setMessage(message, isError = false) {
  const node = qs('walletMessage');
  if (!node) return;

  node.textContent = message || '';
  node.classList.toggle('is-error', Boolean(isError));
}

function updateBalance(balance, notify = false) {
  const text = formatCurrency(balance);

  if (qs('walletBalance')) qs('walletBalance').textContent = text;
  if (qs('navWalletBalance')) qs('navWalletBalance').textContent = text;

  if (notify) {
    emitRealtimeEvent('b2b:wallet:changed', { balance });
  }
}

function depositStatusLabel(status) {
  const labels = {
    pending: 'Chờ chuyển khoản',
    paid: 'Đã ghi nhận',
    expired: 'Đã hết hạn',
  };

  return labels[String(status || 'pending').toLowerCase()] || status || 'Chờ chuyển khoản';
}

function transactionTitle(type) {
  const labels = {
    deposit: 'Nạp tiền',
    withdraw: 'Rút tiền',
    escrow_hold: 'Thanh toán escrow',
    escrow_release: 'Giải ngân',
    refund: 'Hoàn tiền',
  };

  return labels[type] || type || 'Giao dịch';
}

function formatTransactionDate(value) {
  if (!value) return '';

  const date = new Date(String(value).replace(' ', 'T'));
  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return date.toLocaleString('vi-VN', {
    hour: '2-digit',
    minute: '2-digit',
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  });
}

function clearDepositRequest() {
  const wrap = qs('walletDepositQr');
  if (!wrap) return;

  wrap.hidden = true;
  wrap.innerHTML = '';
}

function depositRow(label, value, copyable = false, extraClass = '', copyValue = value) {
  const cleanValue = String(value || '');
  const cleanCopyValue = String(copyValue || cleanValue);
  const copyButton = copyable && cleanValue
    ? `<button type="button" class="wallet-deposit-copy" data-wallet-copy="${escapeHtml(cleanCopyValue)}">Copy</button>`
    : '';

  return `
    <div class="wallet-deposit-row">
      <div>
        <dt>${escapeHtml(label)}</dt>
        <dd class="${escapeHtml(extraClass)}">${escapeHtml(cleanValue)}</dd>
      </div>
      ${copyButton}
    </div>
  `;
}

function renderDepositRequest(request) {
  const wrap = qs('walletDepositQr');
  if (!wrap || !request) return;

  const status = String(request.status || 'pending').toLowerCase();
  const paidAmount = Number(request.paid_amount || 0);
  const requestedAmount = Number(request.requested_amount || request.amount || 0);
  const displayAmount = status === 'paid' && paidAmount > 0 ? paidAmount : requestedAmount;
  const transferContent = request.transfer_content || request.code || '';
  const qrImage = request.vietqr_url || request.qr_image || '';

  wrap.hidden = false;
  wrap.innerHTML = `
    <div class="wallet-deposit-status" data-status="${escapeHtml(status)}">${escapeHtml(depositStatusLabel(status))}</div>
    <div class="wallet-deposit-qr-grid">
      ${qrImage ? `<img src="${escapeHtml(qrImage)}" alt="QR nạp tiền">` : ''}
      <dl class="wallet-deposit-details">
        ${depositRow('Ngân hàng', request.bank_name || request.bank || '', false)}
        ${depositRow('Số tài khoản', request.bank_account_number || '', true)}
        ${depositRow('Chủ tài khoản', request.bank_account_name || '', false)}
        ${depositRow('Số tiền', formatCurrency(displayAmount), true, '', String(Math.round(displayAmount)))}
        ${depositRow('Nội dung chuyển khoản', transferContent, true, 'wallet-deposit-code')}
      </dl>
    </div>
    <div class="wallet-copy-toast" hidden></div>
  `;
}

function renderDepositFromSummary(requests = []) {
  if (!state.activeDepositCode) {
    clearDepositRequest();
    return;
  }

  const request = requests.find((item) => item.code === state.activeDepositCode);

  if (request) {
    state.activeDepositCode = request.code || '';
    renderDepositRequest(request);
  }
}

function renderTransactions(rows = []) {
  const wrap = qs('walletTransactions');
  if (!wrap) return;

  if (!rows.length) {
    wrap.innerHTML = '<div class="wallet-empty">Chưa có giao dịch ví.</div>';
    return;
  }

  wrap.innerHTML = rows.map((row) => {
    const amount = Number(row.amount || 0);
    const negative = amount < 0;
    const note = row.note ? `${escapeHtml(row.note)} · ` : '';

    return `
      <div class="wallet-transaction">
        <div>
          <strong>${escapeHtml(transactionTitle(row.type))}</strong>
          <small>${note}${escapeHtml(formatTransactionDate(row.created_at))}</small>
        </div>
        <div class="wallet-transaction-amount${negative ? ' is-negative' : ''}">
          ${negative ? '-' : '+'}${formatCurrency(Math.abs(amount))}
        </div>
      </div>
    `;
  }).join('');
}

async function loadWallet() {
  if (state.loading) {
    return;
  }

  state.loading = true;

  try {
    const data = await WalletAPI.balance();
    updateBalance(data.balance || 0);
    renderTransactions(data.transactions || []);
    renderDepositFromSummary(data.deposit_requests || []);
  } finally {
    state.loading = false;
  }
}

function selectBank(event) {
  const button = event.target.closest('[data-bank]');
  if (!button) return;

  state.bank = button.dataset.bank;

  const bankInput = qs('walletDepositBank');
  if (bankInput) {
    bankInput.value = state.bank;
  }

  document.querySelectorAll('#walletBankGrid [data-bank]').forEach((node) => {
    node.classList.toggle('is-active', node === button);
  });
}

function selectQuickAmount(event) {
  const button = event.target.closest('[data-wallet-amount]');
  if (!button) return;

  const input = qs('walletDepositAmount');
  if (!input) return;

  input.value = button.dataset.walletAmount || '';
  document.querySelectorAll('[data-wallet-amount]').forEach((node) => {
    node.classList.toggle('is-active', node === button);
  });
  input.focus();
}

async function submitDeposit(event) {
  event.preventDefault();

  const form = event.currentTarget;
  const amount = Number(form.elements.amount.value || 0);
  const bank = form.elements.bank.value || state.bank;
  const submitButton = form.querySelector('button[type="submit"]');

  if (amount < 10000) {
    setMessage('Số tiền nạp tối thiểu là 10,000 VND.', true);
    return;
  }

  submitButton.disabled = true;
  setMessage('Đang tạo QR nạp tiền...');

  try {
    const request = await WalletAPI.deposit({ amount, bank });
    state.activeDepositCode = request.code || '';
    renderDepositRequest(request);
    form.reset();

    const bankInput = qs('walletDepositBank');
    if (bankInput) {
      bankInput.value = state.bank;
    }

    document.querySelectorAll('[data-wallet-amount]').forEach((node) => node.classList.remove('is-active'));
    setMessage('Quét QR hoặc chuyển khoản đúng nội dung. Ví sẽ tự cộng khi Casso gửi webhook.');
  } finally {
    submitButton.disabled = false;
  }
}

async function submitWithdraw(event) {
  event.preventDefault();

  const form = event.currentTarget;
  const submitButton = form.querySelector('button[type="submit"]');
  const payload = {
    bank: form.elements.bank.value,
    account_number: form.elements.account_number.value,
    amount: Number(form.elements.amount.value || 0),
  };

  if (payload.amount < 10000) {
    setMessage('Số tiền rút tối thiểu là 10,000 VND.', true);
    return;
  }

  submitButton.disabled = true;
  setMessage('Đang gửi yêu cầu rút tiền...');

  try {
    const data = await WalletAPI.withdraw(payload);
    updateBalance(data.balance || 0, true);
    renderTransactions(data.transactions || []);
    form.reset();
    setMessage('Đã ghi nhận yêu cầu rút tiền.');
  } finally {
    submitButton.disabled = false;
  }
}

async function copyValue(value) {
  if (!value) return;

  if (navigator.clipboard?.writeText) {
    await navigator.clipboard.writeText(value);
    return;
  }

  const textarea = document.createElement('textarea');
  textarea.value = value;
  textarea.setAttribute('readonly', '');
  textarea.style.position = 'fixed';
  textarea.style.opacity = '0';
  document.body.appendChild(textarea);
  textarea.select();
  document.execCommand('copy');
  textarea.remove();
}

function showCopyToast(button) {
  const wrap = button.closest('.wallet-deposit-qr');
  const toast = wrap?.querySelector('.wallet-copy-toast');
  if (!toast) return;

  toast.hidden = false;
  toast.textContent = 'Đã copy thông tin chuyển khoản.';
  window.clearTimeout(showCopyToast.timer);
  showCopyToast.timer = window.setTimeout(() => {
    toast.hidden = true;
  }, 1600);
}

function bindCopyAction(event) {
  const button = event.target.closest('[data-wallet-copy]');
  if (!button || !button.closest('.wallet-page')) return;

  copyValue(button.dataset.walletCopy || '')
    .then(() => showCopyToast(button))
    .catch(() => setMessage('Không thể copy. Bạn hãy copy thủ công.', true));
}

function hydrateAmountFromQuery() {
  const amount = new URLSearchParams(window.location.search).get('amount');
  const input = qs('walletDepositAmount');

  if (amount && input) {
    input.value = Math.ceil(Number(amount) || 0);
    setMessage('Số dư không đủ để thanh toán đơn hàng. Vui lòng nạp thêm tiền.');
  }
}

function bindWalletPage() {
  qs('walletBankGrid')?.addEventListener('click', selectBank);
  document.querySelector('.wallet-quick-amounts')?.addEventListener('click', selectQuickAmount);
  document.addEventListener('click', bindCopyAction);

  qs('walletDepositForm')?.addEventListener('submit', (event) => {
    submitDeposit(event).catch((error) => setMessage(error.message || 'Không thể tạo QR nạp tiền.', true));
  });

  qs('walletWithdrawForm')?.addEventListener('submit', (event) => {
    submitWithdraw(event).catch((error) => setMessage(error.message || 'Không thể rút tiền.', true));
  });

  qs('walletRefreshBtn')?.addEventListener('click', () => {
    setMessage('Đang làm mới ví...');
    loadWallet()
      .then(() => setMessage('Đã cập nhật ví.'))
      .catch((error) => setMessage(error.message || 'Không thể tải ví.', true));
  });
}

async function initWalletPage() {
  if (!qs('walletBalance') || window.__B2B_WALLET_PAGE_BOOTED__) return;
  window.__B2B_WALLET_PAGE_BOOTED__ = true;

  bindWalletPage();
  hydrateAmountFromQuery();

  const loop = createRealtimeLoop({
    interval: 5000,
    maxInterval: 30000,
    eventName: 'b2b:wallet:changed',
    run: loadWallet,
    immediate: false,
    canRun: () => !document.activeElement?.closest?.('#walletDepositForm, #walletWithdrawForm'),
  });

  try {
    await loadWallet();
  } catch (error) {
    setMessage('Bạn cần đăng nhập và có công ty để sử dụng ví.', true);
    window.setTimeout(() => {
      if (!localStorage.getItem('access_token')) {
        window.location.href = getHomeUrl('/login');
      }
    }, 900);
  }

  loop.start();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initWalletPage);
} else {
  initWalletPage();
}
