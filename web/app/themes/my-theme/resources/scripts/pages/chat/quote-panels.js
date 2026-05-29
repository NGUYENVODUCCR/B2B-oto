export function createQuotePanelRenderers({
  state,
  companyId,
  ownQuotation,
  escapeHtml,
  formatCurrency,
  quotationPricingLine,
  quoteLineMath,
  quotationCard,
  statusLabel,
}) {
  function renderSellerQuotePanel() {
    const quoteDetail = ownQuotation();
    const quote = quoteDetail?.quotation || quoteDetail;
    const disabled = quote && quote.status !== 'pending';
    const sellerItems = (state.rfqDetail?.items || []).filter((item) => Number(item.seller_company_id) === companyId());
    const items = sellerItems.length ? sellerItems : (state.rfqDetail?.items || []);
    const totals = items.reduce((carry, item) => {
      const line = quotationPricingLine(quoteDetail, item.product_id);
      const math = quoteLineMath(
        line?.quantity || item.quantity || 1,
        line?.unit_price ?? item.price_from ?? 0,
        line?.discount_percent || 0
      );

      carry.subtotal += math.subtotal;
      carry.discount += math.discountAmount;
      carry.total += math.total;

      return carry;
    }, { subtotal: 0, discount: 0, total: 0 });

    return `
      <section class="chat-panel">
        <h3>Báo giá</h3>
        ${quote ? `<p class="chat-muted">Báo giá hiện tại: <strong>${escapeHtml(statusLabel(quote.status))}</strong></p>` : ''}
        ${disabled ? '<div class="chat-empty">Báo giá đã khóa, không thể sửa.</div>' : `
          <form data-chat-form="quote" data-quotation-id="${escapeHtml(quote?.id || '')}">
            <div class="chat-quote-fields">
              ${items.map((item) => {
                const line = quotationPricingLine(quoteDetail, item.product_id);
                const math = quoteLineMath(
                  line?.quantity || item.quantity || 1,
                  line?.unit_price ?? item.price_from ?? 0,
                  line?.discount_percent || 0
                );

                return `
                  <div class="chat-quote-line" data-quote-line data-quote-product="${escapeHtml(item.product_id)}">
                    <div class="chat-quote-line-title">
                      <strong>${escapeHtml(item.product_name || `Sản phẩm #${item.product_id}`)}</strong>
                      <span>SL RFQ: ${escapeHtml(item.quantity || 1)}</span>
                    </div>
                    <div class="chat-quote-grid">
                      <label>
                        <span>Số lượng</span>
                        <input type="number" min="1" step="1" data-quote-qty-input value="${escapeHtml(math.quantity)}">
                      </label>
                      <label>
                        <span>Đơn giá</span>
                        <input type="number" min="0" step="1000" data-quote-price-input value="${escapeHtml(math.unitPrice)}">
                      </label>
                      <label>
                        <span>Khuyến mãi (%)</span>
                        <input type="number" min="0" max="100" step="0.01" data-quote-discount-input value="${escapeHtml(math.discountPercent)}">
                      </label>
                    </div>
                    <div class="chat-quote-line-summary">
                      <span>Tạm tính: <b data-quote-line-subtotal>${formatCurrency(math.subtotal)}</b></span>
                      <span>Giảm: <b data-quote-line-discount>${formatCurrency(math.discountAmount)}</b></span>
                      <span>Còn lại: <b data-quote-line-total>${formatCurrency(math.total)}</b></span>
                    </div>
                  </div>
                `;
              }).join('')}
              <div class="chat-quote-summary">
                <span>Tạm tính <b data-quote-summary-subtotal>${formatCurrency(totals.subtotal)}</b></span>
                <span>Khuyến mãi <b data-quote-summary-discount>${formatCurrency(totals.discount)}</b></span>
                <span>Tổng sau giảm <b data-quote-summary-total>${formatCurrency(totals.total)}</b></span>
              </div>
            </div>
            <button type="submit">${quote ? 'Cập nhật báo giá' : 'Gửi báo giá'}</button>
          </form>
        `}
      </section>
    `;
  }

  function renderBuyerQuotePanel() {
    return `
      <section class="chat-panel">
        <h3>Báo giá từ seller</h3>
        ${state.quotations.length ? state.quotations.map((quote) => quotationCard(quote, true)).join('') : '<div class="chat-empty">Seller chưa gửi báo giá.</div>'}
      </section>
    `;
  }

  function recalculateQuoteForm(form) {
    if (!form) return;

    let subtotal = 0;
    let discount = 0;
    let total = 0;

    form.querySelectorAll('[data-quote-line]').forEach((line) => {
      const math = quoteLineMath(
        line.querySelector('[data-quote-qty-input]')?.value,
        line.querySelector('[data-quote-price-input]')?.value,
        line.querySelector('[data-quote-discount-input]')?.value
      );

      subtotal += math.subtotal;
      discount += math.discountAmount;
      total += math.total;

      const subtotalNode = line.querySelector('[data-quote-line-subtotal]');
      const discountNode = line.querySelector('[data-quote-line-discount]');
      const totalNode = line.querySelector('[data-quote-line-total]');

      if (subtotalNode) subtotalNode.innerText = formatCurrency(math.subtotal);
      if (discountNode) discountNode.innerText = formatCurrency(math.discountAmount);
      if (totalNode) totalNode.innerText = formatCurrency(math.total);
    });

    const summarySubtotal = form.querySelector('[data-quote-summary-subtotal]');
    const summaryDiscount = form.querySelector('[data-quote-summary-discount]');
    const summaryTotal = form.querySelector('[data-quote-summary-total]');

    if (summarySubtotal) summarySubtotal.innerText = formatCurrency(subtotal);
    if (summaryDiscount) summaryDiscount.innerText = formatCurrency(discount);
    if (summaryTotal) summaryTotal.innerText = formatCurrency(total);
  }

  function handleQuoteCalculatorInput(event) {
    if (!event.target.closest('[data-quote-qty-input], [data-quote-price-input], [data-quote-discount-input]')) {
      return;
    }

    recalculateQuoteForm(event.target.closest('[data-chat-form="quote"]'));
  }

  return {
    renderSellerQuotePanel,
    renderBuyerQuotePanel,
    recalculateQuoteForm,
    handleQuoteCalculatorInput,
  };
}
