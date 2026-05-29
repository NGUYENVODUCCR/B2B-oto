import { formatDateValue, todayDateValue } from './date.js';

export function createBulkPanelRenderers({
  state,
  qs,
  escapeHtml,
  formatCurrency,
  statusLabel,
  companyId,
  activeRole,
}) {
  function bulkProductInfoHtml(product = {}) {
    if (!product.product_name && !product.quantity) {
      return '<div class="chat-empty">Chưa có form thông tin sản phẩm.</div>';
    }

    return `
      <div class="chat-bulk-info">
        <div><span>Sản phẩm</span><b>${escapeHtml(product.product_name || '-')}</b></div>
        <div><span>Hãng xe</span><b>${escapeHtml(product.brand || '-')}</b></div>
        <div><span>Năm/màu</span><b>${escapeHtml([product.year, product.color].filter(Boolean).join(' ') || '-')}</b></div>
        <div><span>Số lượng</span><b>${Number(product.quantity || 0).toLocaleString('vi-VN')}</b></div>
        <div><span>Giá mong muốn</span><b>${formatCurrency(product.target_price || 0)}</b></div>
        <div class="wide"><span>Mô tả</span><b>${escapeHtml(product.description || '-')}</b></div>
      </div>
    `;
  }

  function validateBulkDeadline(form) {
    if (form.dataset.chatForm !== 'bulk-product') {
      return true;
    }

    const input = form.elements.deadline;
    const value = input?.value || '';
    const today = todayDateValue();

    if (value && value < today) {
      alert(`Hạn cần hàng phải từ ngày hôm nay (${formatDateValue(today)}) trở đi.`);
      input.focus();
      return false;
    }

    return true;
  }

  function handleBulkDeadlineChange(event) {
    const input = event.target.closest?.('input[name="deadline"]');

    if (!input || input.closest('[data-chat-form]')?.dataset.chatForm !== 'bulk-product') {
      return;
    }

    const today = todayDateValue();

    if (input.value && input.value < today) {
      alert(`Hạn cần hàng phải từ ngày hôm nay (${formatDateValue(today)}) trở đi.`);
      input.value = '';
      input.focus();
    }
  }

  function renderBulkBuyerForm(request) {
    const product = request.product_info || {};
    const today = todayDateValue();

    return `
      <section class="chat-panel chat-bulk-form-panel">
        <div class="chat-bulk-form-head">
          <div>
            <span>Buyer RFQ</span>
            <h3>Thông tin sản phẩm cần thu mua</h3>
          </div>
          <small>Support sẽ dùng form này để mở kênh RFQ chung cho seller.</small>
        </div>
        <form class="chat-bulk-form" data-chat-form="bulk-product" data-bulk-id="${escapeHtml(request.id)}">
          <div class="chat-bulk-fieldset">
            <p>Thông tin xe</p>
            <div class="chat-bulk-form-grid">
              <label class="wide"><span>Tên sản phẩm</span><input name="product_name" value="${escapeHtml(product.product_name || '')}" placeholder="VD: Honda CR-V" required></label>
              <label><span>Hãng xe</span><input name="brand" value="${escapeHtml(product.brand || '')}" placeholder="Honda, Hyundai..."></label>
              <label><span>Năm</span><input name="year" value="${escapeHtml(product.year || '')}" placeholder="2021"></label>
              <label><span>Màu</span><input name="color" value="${escapeHtml(product.color || '')}" placeholder="Đen, trắng..."></label>
            </div>
          </div>
          <div class="chat-bulk-fieldset">
            <p>Số lượng và giao nhận</p>
            <div class="chat-bulk-form-grid">
              <label><span>Số lượng cần mua</span><input type="number" min="1" name="quantity" value="${escapeHtml(product.quantity || '')}" placeholder="10" required></label>
              <label><span>Giá mong muốn / xe</span><input type="number" min="0" step="1000" name="target_price" value="${escapeHtml(product.target_price || '')}" placeholder="750000000"></label>
              <label><span>Hạn cần hàng</span><input type="date" name="deadline" min="${escapeHtml(today)}" value="${escapeHtml(product.deadline || '')}"></label>
            </div>
          </div>
          <label class="chat-bulk-textarea"><span>Mô tả thêm</span><textarea name="description" rows="4" placeholder="Tình trạng xe, phiên bản, yêu cầu giấy tờ, thời gian giao từng đợt...">${escapeHtml(product.description || '')}</textarea></label>
          <button type="submit" class="chat-bulk-submit">Gửi form cho Support</button>
        </form>
      </section>
    `;
  }

  function renderBulkSellerForm(request) {
    const product = request.product_info || {};

    return `
      <section class="chat-panel chat-bulk-form-panel">
        <div class="chat-bulk-form-head">
          <div>
            <span>Seller RFQ</span>
            <h3>Tham gia cung cấp</h3>
          </div>
          <small>Chỉ tham gia nếu sản phẩm trùng khớp với form buyer yêu cầu.</small>
        </div>
        <form class="chat-bulk-form" data-chat-form="bulk-join" data-bulk-id="${escapeHtml(request.id)}">
          <div class="chat-bulk-fieldset">
            <p>Sản phẩm có thể cung cấp</p>
            <div class="chat-bulk-form-grid">
              <label class="wide"><span>Tên sản phẩm</span><input name="product_name" value="${escapeHtml(product.product_name || '')}" required></label>
              <label><span>Hãng xe</span><input name="brand" value="${escapeHtml(product.brand || '')}"></label>
              <label><span>Năm</span><input name="year" value="${escapeHtml(product.year || '')}"></label>
              <label><span>Màu</span><input name="color" value="${escapeHtml(product.color || '')}"></label>
            </div>
          </div>
          <div class="chat-bulk-fieldset">
            <p>Báo số lượng và giá</p>
            <div class="chat-bulk-form-grid">
              <label><span>Số lượng có thể cung cấp</span><input type="number" min="1" name="available_quantity" placeholder="5" required></label>
              <label><span>Đơn giá / xe</span><input type="number" min="1" step="1" name="unit_price" placeholder="760000000" required></label>
            </div>
          </div>
          <label class="chat-bulk-textarea"><span>Ghi chú</span><textarea name="note" rows="3" placeholder="Xác nhận đúng thông tin sản phẩm, thời gian giao hàng, số lượng từng đợt..."></textarea></label>
          <button type="submit" class="chat-bulk-submit">Tham Gia</button>
        </form>
      </section>
    `;
  }

  function renderBulkFormLauncher(request, kind) {
    const isSellerForm = kind === 'seller';
    const title = isSellerForm ? 'Tham gia cung cấp' : 'Điền thông tin sản phẩm';
    const note = isSellerForm
      ? 'Mở form để nhập số lượng, đơn giá và ghi chú cung cấp.'
      : 'Mở form rộng hơn để nhập đầy đủ thông tin sản phẩm cần thu mua.';
    const buttonText = isSellerForm ? 'Tham gia' : 'Mở form nhập';

    return `
      <section class="chat-panel chat-bulk-form-launcher">
        <div>
          <span>${escapeHtml(isSellerForm ? 'Seller RFQ' : 'Buyer RFQ')}</span>
          <h3>${escapeHtml(title)}</h3>
          <p>${escapeHtml(note)}</p>
        </div>
        <button type="button" data-chat-action="bulk-open-form" data-bulk-form="${escapeHtml(kind)}" data-bulk-id="${escapeHtml(request.id)}">
          ${escapeHtml(buttonText)}
        </button>
      </section>
    `;
  }

  function ensureBulkFormModal() {
    if (qs('chatBulkFormModal')) {
      return;
    }

    document.body.insertAdjacentHTML('beforeend', `
      <div id="chatBulkFormModal" class="chat-bulk-modal hidden" aria-hidden="true">
        <div class="chat-bulk-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="chatBulkFormModalTitle">
          <header class="chat-bulk-modal-head">
            <div>
              <span>Bulk RFQ</span>
              <h2 id="chatBulkFormModalTitle">Form thông tin</h2>
            </div>
            <button type="button" class="secondary" data-bulk-modal-close>Đóng</button>
          </header>
          <div id="chatBulkFormModalContent" class="chat-bulk-modal-content"></div>
        </div>
      </div>
    `);
  }

  function closeBulkFormModal() {
    const modal = qs('chatBulkFormModal');

    if (!modal) return;

    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');
    qs('chatBulkFormModalContent').innerHTML = '';
    state.formDirty = false;
  }

  function openBulkFormModal(kind) {
    if (!state.activeBulk) {
      return;
    }

    ensureBulkFormModal();
    const modal = qs('chatBulkFormModal');
    const title = qs('chatBulkFormModalTitle');
    const content = qs('chatBulkFormModalContent');
    const isSellerForm = kind === 'seller';

    if (title) {
      title.innerText = isSellerForm ? 'Tham gia cung cấp' : 'Thông tin sản phẩm cần thu mua';
    }

    if (content) {
      content.innerHTML = isSellerForm
        ? renderBulkSellerForm(state.activeBulk)
        : renderBulkBuyerForm(state.activeBulk);
    }

    modal?.classList.remove('hidden');
    modal?.setAttribute('aria-hidden', 'false');
    state.formDirty = true;

    window.setTimeout(() => {
      modal?.querySelector('input, textarea, button[type="submit"]')?.focus?.();
    }, 50);
  }

  function renderBulkOffers(request) {
    const offers = Array.isArray(request.seller_offers) ? request.seller_offers : [];
    const requestedQuantity = Number(request.requested_quantity || request.product_info?.quantity || 0);
    const requestedText = requestedQuantity > 0 ? requestedQuantity.toLocaleString('vi-VN') : '0';

    if (!offers.length) {
      return '<div class="chat-empty">Chưa có seller tham gia.</div>';
    }

    return offers.map((offer) => `
      <div class="chat-bulk-offer">
        <strong>${escapeHtml(offer.seller_company_name || `Seller #${offer.seller_company_id}`)}</strong>
        <span>SL ${Number(offer.available_quantity || 0).toLocaleString('vi-VN')}/${requestedText} · ${formatCurrency(offer.unit_price || 0)} / xe</span>
        <small>${escapeHtml(offer.product_name || '')}</small>
      </div>
    `).join('');
  }

  function renderBulkContracts(request) {
    const contracts = Array.isArray(request.contracts) ? request.contracts : [];
    const role = request.viewer_role || activeRole();
    const currentCompanyId = companyId();

    if (!contracts.length) {
      return '';
    }

    let visibleContracts = contracts;

  
    if (role !== 'buyer') {

      visibleContracts = contracts.filter((contract) => {
        return Number(contract.seller_company_id || 0) === Number(currentCompanyId || 0);
      });
    }

    if (!visibleContracts.length) {
      return '';
    }

    return `
      <section class="chat-panel">
        <h3>Hợp đồng đã tạo</h3>
        <p class="chat-muted">Mỗi seller có một RFQ/hợp đồng riêng để ký điện tử, tạo đơn, thanh toán escrow và giải ngân riêng.</p>
        ${visibleContracts.map((contract) => `
          <div class="chat-bulk-contract">
            <strong>${escapeHtml(contract.seller_company_name || `Seller #${contract.seller_company_id}`)}</strong>
            <span>${Number(contract.quantity || 0).toLocaleString('vi-VN')} xe · ${formatCurrency(contract.total_amount || 0)}</span>
            <button type="button" data-chat-action="open-rfq" data-rfq-id="${escapeHtml(contract.rfq_id)}">Mở chat hợp đồng</button>
          </div>
        `).join('')}
      </section>
    `;
  }

  function renderBulkDealPanel() {
    const request = state.activeBulk;

    if (!request) {
      return '<div class="chat-empty">Không tìm thấy yêu cầu thu mua số lượng lớn.</div>';
    }

    const progress = `${Number(request.offered_quantity || 0).toLocaleString('vi-VN')} / ${Number(request.requested_quantity || 0).toLocaleString('vi-VN')}`;

    const buyerForm = request.can_submit_buyer_form ? renderBulkFormLauncher(request, 'buyer') : '';
    const sellerForm = request.can_join ? renderBulkFormLauncher(request, 'seller') : '';

    return `
      ${buyerForm}
      ${sellerForm}
      <section class="chat-panel">
        <h3>Bulk RFQ</h3>
        <div class="chat-panel-row">
          <span class="chat-status-badge">${escapeHtml(statusLabel(request.status))}</span>
          <strong>${escapeHtml(progress)}</strong>
        </div>
        ${request.broadcast_message ? `<p class="chat-muted chat-bulk-summary">${escapeHtml(request.broadcast_message)}</p>` : ''}
        ${bulkProductInfoHtml(request.product_info || {})}
        <div class="chat-inline-actions">
          ${request.can_request_form ? `<button type="button" data-chat-action="bulk-request-form" data-bulk-id="${escapeHtml(request.id)}">Yêu cầu buyer điền form</button>` : ''}
          ${request.can_send_rfq ? `<button type="button" data-chat-action="bulk-send-rfq" data-bulk-id="${escapeHtml(request.id)}">SendRFQ</button>` : ''}
          ${request.can_send_buyer ? `<button type="button" data-chat-action="bulk-send-buyer" data-bulk-id="${escapeHtml(request.id)}">SendBuyer</button>` : ''}
          ${request.can_accept ? `<button type="button" data-chat-action="bulk-accept" data-bulk-id="${escapeHtml(request.id)}">Đồng ý báo giá</button>` : ''}
        </div>
      </section>
      <section class="chat-panel">
        <h3>Seller tham gia</h3>
        ${renderBulkOffers(request)}
      </section>
      ${renderBulkContracts(request)}
    `;
  }

  return {
    bulkProductInfoHtml,
    closeBulkFormModal,
    ensureBulkFormModal,
    handleBulkDeadlineChange,
    openBulkFormModal,
    renderBulkBuyerForm,
    renderBulkContracts,
    renderBulkDealPanel,
    renderBulkFormLauncher,
    renderBulkOffers,
    renderBulkSellerForm,
    validateBulkDeadline,
  };
}
