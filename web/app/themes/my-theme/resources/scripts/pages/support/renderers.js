import {
  bulkStatusLabel,
  escapeHtml,
  formatCurrency,
  formatDate,
  normalizeMojibakeText,
  ticketStatusLabel,
} from './helpers.js';

export function createSupportRenderers({ state, getHomeUrl, canManageTickets }) {
  function renderTicketMessages(ticket) {
    const messages = Array.isArray(ticket.messages) ? ticket.messages : [];

    if (!messages.length) {
      return '<div class="support-empty-inline">Chưa có tin nhắn nào.</div>';
    }

    return `
      <div class="support-message-list">
        ${messages.map((message) => {
          const isBuyer = String(message.sender_id) === String(ticket.user_id);
          const author = isBuyer ? 'Buyer' : 'Support';

          return `
            <div class="support-message ${isBuyer ? 'support-message-buyer' : 'support-message-support'}">
              <div class="support-message-meta">${escapeHtml(author)} · ${escapeHtml(formatDate(message.created_at))}</div>
              <div class="support-message-text">${escapeHtml(normalizeMojibakeText(message.message || message.content || ''))}</div>
            </div>
          `;
        }).join('')}
      </div>
    `;
  }

  function renderTickets() {
  const list = document.getElementById('supportTicketList');

  if (!list) return;

  const setTicketListHtml = (key, html) => {
    if (list.dataset.renderKey === key) {
      return;
    }

    list.dataset.renderKey = key;
    list.innerHTML = html;
  };

  if (state.loading) {
    setTicketListHtml(
      '__loading__',
      '<div class="support-empty">Đang tải ticket...</div>'
    );
    return;
  }

  if (state.tickets.length === 0) {
    setTicketListHtml(
      '__empty__',
      '<div class="support-empty">Chưa có ticket support nào.</div>'
    );
    return;
  }

  const manage = canManageTickets();

  const renderKey = JSON.stringify({
    manage,
    tickets: state.tickets.map((ticket) => ({
      id: ticket.id,
      type: ticket.type || '',
      status: ticket.status || '',
      order_id: ticket.order_id || '',
      created_at: ticket.created_at || '',
      updated_at: ticket.updated_at || '',
      support_user_name: ticket.support_user_name || '',
    })),
  });

  const html = state.tickets.map((ticket) => {
    const status = String(ticket.status || 'open').toLowerCase();
    const title = ticket.order_id ? `Order #${ticket.order_id}` : `Ticket #${ticket.id}`;
    const chatUrl = `${getHomeUrl('/chat')}?support_ticket_id=${encodeURIComponent(ticket.id)}`;

    return `
      <article class="support-ticket" data-ticket-id="${escapeHtml(ticket.id)}">
        <div class="support-ticket-main">
          <div>
            <span class="support-ticket-kicker">${escapeHtml(ticket.type || 'dispute')}</span>
            <h2>${escapeHtml(title)}</h2>
            <p>Tạo lúc ${escapeHtml(formatDate(ticket.created_at))}</p>
            <p>Support phụ trách: ${escapeHtml(normalizeMojibakeText(ticket.support_user_name || 'Tự động phân công'))}</p>
          </div>
          <span class="support-status" data-status="${escapeHtml(status)}">${escapeHtml(ticketStatusLabel(status))}</span>
        </div>

        <div class="support-ticket-actions">
          <a class="support-ticket-open-chat" href="${escapeHtml(chatUrl)}">Mở chat để giải quyết</a>
        </div>

        ${manage ? `
          <div class="support-actions">
            <button type="button" data-support-close="${escapeHtml(ticket.id)}" data-status="resolved">Đánh dấu đã giải quyết</button>
            <button type="button" data-support-close="${escapeHtml(ticket.id)}" data-status="closed">Đóng ticket</button>
          </div>
        ` : ''}
      </article>
    `;
  }).join('');

  setTicketListHtml(renderKey, html);
}

  function renderPendingPayouts() {
    const list = document.getElementById('supportPayoutList');

    if (!list) return;

    if (state.payoutLoading) {
      list.innerHTML = '<div class="support-empty">Đang tải danh sách chờ giải ngân...</div>';
      return;
    }

    if (state.pendingPayouts.length === 0) {
      list.innerHTML = '<div class="support-empty">Không có order nào đang chờ duyệt giải ngân.</div>';
      return;
    }

    list.innerHTML = state.pendingPayouts.map((item) => {
      const rfqUrl = item.rfq_id
        ? `${getHomeUrl('/chat')}?rfq_id=${encodeURIComponent(item.rfq_id)}`
        : '';

      return `
        <article class="support-payout-card" data-order-id="${escapeHtml(item.order_id)}">
          <div class="support-payout-head">
            <div>
              <span class="support-ticket-kicker">Order #${escapeHtml(item.order_id)} · Payment #${escapeHtml(item.payment_id)}</span>
              <h3>${escapeHtml(item.seller_company_name || `Seller #${item.seller_company_id}`)}</h3>
              <p>Buyer: ${escapeHtml(item.buyer_company_name || `Buyer #${item.buyer_company_id}`)}</p>
            </div>
            <span class="support-status" data-status="processing">Chờ giải ngân</span>
          </div>

          <div class="support-payout-grid">
            <div><span>RFQ</span><strong>${item.rfq_id ? `#${escapeHtml(item.rfq_id)}` : '-'}</strong></div>
            <div><span>Contract</span><strong>${item.contract_id ? `#${escapeHtml(item.contract_id)}` : '-'}</strong></div>
            <div><span>Order status</span><strong>${escapeHtml(item.order_status || '-')}</strong></div>
            <div><span>Payment status</span><strong>${escapeHtml(item.payment_status || '-')}</strong></div>
            <div><span>Tổng order</span><strong>${formatCurrency(item.order_total_amount || 0)}</strong></div>
            <div><span>Số tiền escrow</span><strong>${formatCurrency(item.payment_amount || 0)}</strong></div>
            <div><span>Buyer xác nhận</span><strong>${escapeHtml(formatDate(item.order_updated_at || item.order_created_at || ''))}</strong></div>
            <div><span>Paid at</span><strong>${escapeHtml(formatDate(item.paid_at || item.payment_created_at || ''))}</strong></div>
          </div>

          <div class="support-actions">
            ${rfqUrl ? `<a class="support-ticket-open-chat" href="${escapeHtml(rfqUrl)}">Mở chat hợp đồng</a>` : ''}
            <button type="button" data-support-release-payment="${escapeHtml(item.payment_id)}" data-order-id="${escapeHtml(item.order_id)}">Duyệt giải ngân</button>
            <button type="button" class="support-refund-button" data-support-refund-payment="${escapeHtml(item.payment_id)}"data-order-id="${escapeHtml(item.order_id)}"data-payment-amount="${escapeHtml(item.payment_amount || 0)}">Hoàn tiền bên mua</button>
          </div>
        </article>
      `;
    }).join('');
  }

  function sellerChannelSenderLabel(message = {}) {
    const senderType = String(message.sender_type || '').toLowerCase();

    if (senderType === 'support') {
      return 'Support';
    }

    return 'Seller';
  }

  function sellerChannelMessageMarkup(message = {}) {
    const senderType = String(message.sender_type || '').toLowerCase() || 'seller';
    const sender = sellerChannelSenderLabel(message);
    const createdAt = formatDate(message.created_at || message.createdAt || '');
    const text = message.message || message.content || '';

    return `
      <article class="seller-channel-row" data-sender="${escapeHtml(senderType)}">
        <div class="seller-channel-meta">${escapeHtml(sender)} · ${escapeHtml(createdAt)}</div>
        <div class="seller-channel-text">${escapeHtml(text)}</div>
      </article>
    `;
  }

  function renderSellerChannel() {
    const containers = Array.from(document.querySelectorAll('[data-seller-channel-messages]'));

    if (!containers.length) return;

    let html = '';

    if (state.sellerChannelLoading) {
      html = '<div class="support-empty">Đang tải kênh liên lạc seller...</div>';
    } else {
      const rows = Array.isArray(state.sellerChannel?.messages) ? state.sellerChannel.messages : [];
      html = rows.length
        ? rows.map((message) => sellerChannelMessageMarkup(message)).join('')
        : '<div class="support-empty">Chưa có tin nhắn nào trong kênh chung seller.</div>';
    }

    containers.forEach((container) => {
      container.innerHTML = html;

      if (!state.sellerChannelLoading) {
        container.scrollTop = container.scrollHeight;
      }
    });
  }

  function productInfoMarkup(product = {}) {
    if (!product.product_name && !product.quantity) {
      return '<div class="bulk-empty-inline">Chưa có form thông tin sản phẩm.</div>';
    }

    return `
      <div class="bulk-product-info">
        <div><span>Sản phẩm</span><strong>${escapeHtml(product.product_name || '-')}</strong></div>
        <div><span>Hãng / model</span><strong>${escapeHtml([product.brand, product.model].filter(Boolean).join(' ') || '-')}</strong></div>
        <div><span>Năm / màu</span><strong>${escapeHtml([product.year, product.color].filter(Boolean).join(' ') || '-')}</strong></div>
        <div><span>Số lượng cần mua</span><strong>${Number(product.quantity || 0).toLocaleString('vi-VN')}</strong></div>
        <div><span>Giá mong muốn</span><strong>${formatCurrency(product.target_price || 0)}</strong></div>
        <div><span>Hạn cần hàng</span><strong>${escapeHtml(product.deadline || '-')}</strong></div>
        <div class="bulk-product-info-wide"><span>Mô tả</span><strong>${escapeHtml(product.description || '-')}</strong></div>
      </div>
    `;
  }

  function buyerProductForm(request) {
    const product = request.product_info || {};

    return `
      <form class="bulk-inline-form" data-bulk-product-form="${escapeHtml(request.id)}">
        <h3>Form thông tin sản phẩm</h3>
        <div class="bulk-form-grid">
          <label>Tên sản phẩm<input name="product_name" value="${escapeHtml(product.product_name || '')}" required></label>
          <label>Hãng xe<input name="brand" value="${escapeHtml(product.brand || '')}"></label>
          <label>Model / phiên bản<input name="model" value="${escapeHtml(product.model || '')}"></label>
          <label>Năm sản xuất<input name="year" value="${escapeHtml(product.year || '')}"></label>
          <label>Màu sắc<input name="color" value="${escapeHtml(product.color || '')}"></label>
          <label>Số lượng cần mua<input type="number" min="1" name="quantity" value="${escapeHtml(product.quantity || '')}" required></label>
          <label>Giá mong muốn / xe<input type="number" min="0" step="1000" name="target_price" value="${escapeHtml(product.target_price || '')}"></label>
          <label>Hạn cần hàng<input type="date" name="deadline" value="${escapeHtml(product.deadline || '')}"></label>
        </div>
        <label>Mô tả thêm<textarea name="description" rows="3">${escapeHtml(product.description || '')}</textarea></label>
        <button type="submit">Gửi thông tin sản phẩm</button>
      </form>
    `;
  }

  function sellerJoinForm(request) {
    const product = request.product_info || {};

    return `
      <form class="bulk-inline-form" data-bulk-join-form="${escapeHtml(request.id)}">
        <h3>Tham gia cung cấp</h3>
        <div class="bulk-form-grid">
          <label>Tên sản phẩm có thể cung cấp<input name="product_name" value="${escapeHtml(product.product_name || '')}" required></label>
          <label>Hãng xe<input name="brand" value="${escapeHtml(product.brand || '')}"></label>
          <label>Model / phiên bản<input name="model" value="${escapeHtml(product.model || '')}"></label>
          <label>Năm sản xuất<input name="year" value="${escapeHtml(product.year || '')}"></label>
          <label>Màu sắc<input name="color" value="${escapeHtml(product.color || '')}"></label>
          <label>Số lượng có thể cung cấp<input type="number" min="1" name="available_quantity" required></label>
          <label>Đơn giá / xe<input type="number" min="0" step="1000" name="unit_price" required></label>
        </div>
        <label>Ghi chú xác nhận thông tin trùng khớp<textarea name="note" rows="3" placeholder="Ví dụ: đúng phiên bản, đúng màu, có thể giao trong 7 ngày"></textarea></label>
        <button type="submit">Tham Gia</button>
      </form>
    `;
  }

  function offersMarkup(request) {
    const offers = Array.isArray(request.seller_offers) ? request.seller_offers : [];

    if (!offers.length) {
      return '<div class="bulk-empty-inline">Chưa có seller tham gia.</div>';
    }

    return `
      <div class="bulk-offer-list">
        ${offers.map((offer) => `
          <article class="bulk-offer-card">
            <div>
              <strong>${escapeHtml(offer.seller_company_name || `Seller #${offer.seller_company_id}`)}</strong>
              <span>${escapeHtml(offer.product_name || '-')}</span>
            </div>
            <div><span>Số lượng</span><strong>${Number(offer.available_quantity || 0).toLocaleString('vi-VN')}</strong></div>
            <div><span>Đơn giá</span><strong>${formatCurrency(offer.unit_price || 0)}</strong></div>
            <p>${escapeHtml(offer.note || '')}</p>
          </article>
        `).join('')}
      </div>
    `;
  }

  function contractsMarkup(request) {
    const contracts = Array.isArray(request.contracts) ? request.contracts : [];

    if (!contracts.length) return '';

    return `
      <div class="bulk-contract-list">
        <h3>Hồ sơ hợp đồng đã tạo</h3>
        ${contracts.map((contract) => `
          <div class="bulk-contract-row">
            <strong>${escapeHtml(contract.contract_no || `Contract #${contract.id}`)}</strong>
            <span>${escapeHtml(contract.seller_company_name || '')}</span>
            <span>${formatCurrency(contract.total_amount || 0)}</span>
          </div>
        `).join('')}
      </div>
    `;
  }

  function renderBulkRequests() {
    const list = document.getElementById('bulkPurchaseList');

    if (!list) return;

    if (state.bulkLoading) {
      list.innerHTML = '<div class="support-empty">Đang tải yêu cầu thu mua số lượng lớn...</div>';
      return;
    }

    if (!state.bulkRequests.length) {
      list.innerHTML = '<div class="support-empty">Chưa có yêu cầu thu mua số lượng lớn phù hợp với tài khoản này.</div>';
      return;
    }

    list.innerHTML = state.bulkRequests.map((request) => {
      const status = String(request.status || 'started').toLowerCase();
      const progressText = `${Number(request.offered_quantity || 0).toLocaleString('vi-VN')} / ${Number(request.requested_quantity || 0).toLocaleString('vi-VN')}`;
      const requestedForm = request.can_submit_buyer_form ? buyerProductForm(request) : '';
      const sellerForm = request.can_join ? sellerJoinForm(request) : '';

      return `
        <article class="bulk-request-card" data-bulk-id="${escapeHtml(request.id)}" data-status="${escapeHtml(status)}">
          <div class="bulk-request-head">
            <div>
              <span class="support-ticket-kicker">Bulk RFQ #${escapeHtml(request.id)}</span>
              <h2>${escapeHtml(request.product_info?.product_name || 'Yêu cầu thu mua số lượng lớn')}</h2>
              <p>Buyer: ${escapeHtml(request.buyer_company_name || `Company #${request.buyer_company_id}`)} · ${escapeHtml(formatDate(request.created_at))}</p>
            </div>
            <span class="support-status" data-status="${escapeHtml(status)}">${escapeHtml(bulkStatusLabel(status))}</span>
          </div>

          <div class="bulk-progress">
            <span>Tiến độ số lượng</span>
            <strong>${escapeHtml(progressText)}</strong>
          </div>

          <div class="bulk-broadcast">
            ${request.broadcast_message
              ? `<p>${escapeHtml(request.broadcast_message)}</p>`
              : `<p>${escapeHtml(request.initial_message || 'Yêu cầu thu mua số lượng lớn đang chờ Support xử lý.')}</p>`}
            ${productInfoMarkup(request.product_info || {})}
          </div>

          ${requestedForm}
          ${sellerForm}
          ${request.viewer_offer ? `<div class="bulk-note-success">Bạn đã tham gia RFQ này.</div>` : ''}

          <div class="bulk-section">
            <h3>Seller đã tham gia</h3>
            ${offersMarkup(request)}
          </div>

          <div class="bulk-actions">
            ${request.can_request_form ? `<button type="button" data-bulk-action="request-form" data-bulk-id="${escapeHtml(request.id)}">Yêu cầu buyer điền form</button>` : ''}
            ${request.can_send_rfq ? `<button type="button" data-bulk-action="send-rfq" data-bulk-id="${escapeHtml(request.id)}">SendRFQ</button>` : ''}
            ${request.can_send_buyer ? `<button type="button" data-bulk-action="send-buyer" data-bulk-id="${escapeHtml(request.id)}">SendBuyer</button>` : ''}
            ${request.can_accept ? `<button type="button" data-bulk-action="accept" data-bulk-id="${escapeHtml(request.id)}">Đồng ý báo giá</button>` : ''}
          </div>

          ${contractsMarkup(request)}
        </article>
      `;
    }).join('');
  }

  return {
    renderTicketMessages,
    renderTickets,
    renderPendingPayouts,
    renderSellerChannel,
    productInfoMarkup,
    buyerProductForm,
    sellerJoinForm,
    offersMarkup,
    contractsMarkup,
    renderBulkRequests,
  };
}
