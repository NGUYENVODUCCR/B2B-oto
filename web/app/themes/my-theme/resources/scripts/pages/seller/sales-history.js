function salesStatusLabel(status) {
  const value = String(status || '').toLowerCase();

  return {
    pending: 'Pending',
    paid: 'Paid',
    delivering: 'Delivering',
    completed: 'Completed',
    cancelled: 'Cancelled',
  }[value] || (status || '-');
}

function salesDate(row = {}) {
  return row.released_at || row.paid_at || row.order_updated_at || row.order_created_at || '';
}

export function createSellerSalesHistory({
  OrderAPI,
  escapeHtml,
  salesList,
  salesMessage,
  salesStatusInput,
  salesDateFromInput,
  salesDateToInput,
}) {
  let salesHistoryLoading = false;
  let salesHistoryRows = [];

  function setSalesMessage(text = '', type = 'info') {
    if (!salesMessage) return;

    salesMessage.textContent = text;
    salesMessage.dataset.type = type;
  }

  function renderSellerSalesHistory() {
    if (!salesList) return;

    if (salesHistoryLoading) {
      salesList.innerHTML = '<div class="seller-sales-empty">Đang tải lịch sử bán hàng...</div>';
      return;
    }

    if (!Array.isArray(salesHistoryRows) || salesHistoryRows.length === 0) {
      salesList.innerHTML = '<div class="seller-sales-empty">Chưa có đơn bán hàng nào theo bộ lọc hiện tại.</div>';
      return;
    }

    salesList.innerHTML = salesHistoryRows.map((row) => {
      const orderId = row.order_id || row.id || '-';
      const status = String(row.order_status || '').toLowerCase();
      const paymentStatus = String(row.payment_status || 'unpaid').toUpperCase();
      const totalAmount = Number(row.total_amount || row.payment_amount || 0);
      const totalQuantity = Number(row.total_quantity || 0);
      const products = row.product_names || '-';
      const buyerName = row.buyer_company_name || `Buyer #${row.buyer_company_id || '-'}`;

      const updatedAt = salesDate(row);
      const prettyDate = updatedAt
        ? new Date(String(updatedAt).replace(' ', 'T')).toLocaleString('vi-VN')
        : '-';

      return `
        <article class="seller-sales-card">
          <div class="seller-sales-card-head">
            <div>
              <div class="seller-sales-kicker">Order #${escapeHtml(orderId)}</div>
              <h4>${escapeHtml(products)}</h4>
            </div>
            <span class="seller-sales-status status-${escapeHtml(status)}">${escapeHtml(salesStatusLabel(status))}</span>
          </div>
          <div class="seller-sales-meta">
            <div><span>Buyer</span><strong>${escapeHtml(buyerName)}</strong></div>
            <div><span>Tổng tiền</span><strong>${Number(totalAmount).toLocaleString('vi-VN')} VND</strong></div>
            <div><span>Số lượng xe</span><strong>${Number(totalQuantity).toLocaleString('vi-VN')}</strong></div>
            <div><span>Trạng thái thanh toán</span><strong>${escapeHtml(paymentStatus)}</strong></div>
            <div><span>Ngày cập nhật</span><strong>${escapeHtml(prettyDate)}</strong></div>
            <div><span>Hợp đồng</span><strong>${row.contract_id ? `#${escapeHtml(row.contract_id)}` : '-'}</strong></div>
            <div><span>Order status</span><strong>${escapeHtml(row.order_status || '-')}</strong></div>
            <div><span>Payment status</span><strong>${escapeHtml(row.payment_status || '-')}</strong></div>
          </div>
        </article>
      `;
    }).join('');
  }

  async function loadSellerSalesHistory(options = {}) {
    if (!salesList || salesHistoryLoading) return;

    const dateFrom = salesDateFromInput?.value || '';
    const dateTo = salesDateToInput?.value || '';

    if (dateFrom && dateTo && dateFrom > dateTo) {
      setSalesMessage('Khoảng ngày không hợp lệ. Vui lòng kiểm tra lại bộ lọc.', 'error');
      return;
    }

    salesHistoryLoading = true;

    if (!options.silent) {
      renderSellerSalesHistory();
    }

    try {
      const rows = await OrderAPI.sellerHistory({
        status: salesStatusInput?.value || 'all',
        date_from: dateFrom,
        date_to: dateTo,
        limit: 120,
      });

      salesHistoryRows = Array.isArray(rows) ? rows : [];
      setSalesMessage('');
    } catch (error) {
      salesHistoryRows = [];
      setSalesMessage(error?.message || 'Không t?i du?c l?ch s? bán hàng.', 'error');
    } finally {
      salesHistoryLoading = false;
      renderSellerSalesHistory();
    }
  }

  function init(salesFilterForm) {
    if (salesList) {
      loadSellerSalesHistory();
    }

    salesFilterForm?.addEventListener('submit', (event) => {
      event.preventDefault();
      loadSellerSalesHistory();
    });
  }

  return {
    init,
    loadSellerSalesHistory,
    renderSellerSalesHistory,
    setSalesMessage,
  };
}
