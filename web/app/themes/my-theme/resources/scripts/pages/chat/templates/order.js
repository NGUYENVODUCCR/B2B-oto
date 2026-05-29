import { escapeHtml, formatCurrency } from '../../../utils/format.js';
import { statusLabel } from './text.js';

export function reviewBlock(review) {
  if (!review) {
    return '';
  }

  const rating = Number(review.rating || 0);
  const stars = '★'.repeat(Math.max(0, Math.min(5, rating))) + '☆'.repeat(Math.max(0, 5 - rating));

  return `
    <div class="chat-review-card">
      <div class="chat-review-head">
        <strong>Đánh giá đơn hàng</strong>
        <span>${escapeHtml(stars)}</span>
      </div>
      ${review.comment ? `<p>${escapeHtml(review.comment)}</p>` : '<p class="chat-muted">Buyer không để lại nhận xét.</p>'}
      <small>${escapeHtml(review.created_at || '')}</small>
    </div>
  `;
}

export function settlementBlock(payment) {
  const settlement = payment?.settlement;

  if (!settlement) {
    return '';
  }

  return `
    <div class="chat-settlement-card">
      <strong>Quyết toán escrow</strong>
      <div><span>Giải ngân seller</span><b>${formatCurrency(settlement.release_amount || 0)}</b></div>
      <div><span>Hoàn buyer</span><b>${formatCurrency(settlement.refund_amount || 0)}</b></div>
      <small>${escapeHtml(settlement.reason || '')} · ${escapeHtml(settlement.settled_at || '')}</small>
    </div>
  `;
}

export function orderPanel(orderDetail, role, review = null, viewerCompanyId = 0, canManageEscrow = false) {
  const order = orderDetail?.order;
  const payment = orderDetail?.payment;

  if (!order) {
    return '';
  }

  const currentCompanyId = Number(viewerCompanyId || 0);
  const buyerCompanyId = Number(order.buyer_company_id || 0);
  const sellerCompanyId = Number(order.seller_company_id || 0);


  const isCurrentBuyer = currentCompanyId > 0 && currentCompanyId === buyerCompanyId;
  const isCurrentSeller = currentCompanyId > 0 && currentCompanyId === sellerCompanyId;

  return `
    <section class="chat-panel">
      <h3>Order & escrow</h3>
      <div class="chat-panel-row">
        <span class="chat-status-badge">${escapeHtml(statusLabel(order.status))}</span>
        <strong>${formatCurrency(order.total_amount)}</strong>
      </div>
      <p class="chat-muted">Payment: ${escapeHtml(statusLabel(payment?.payment_status || 'pending'))}</p>

      <div class="chat-inline-actions">
        ${isCurrentBuyer && order.status === 'pending'
          ? `<button type="button" data-chat-action="pay-order" data-order-id="${escapeHtml(order.id)}" data-payment-id="${escapeHtml(payment?.id || '')}">Thanh toán escrow</button>`
          : ''}

        ${isCurrentSeller && order.status === 'paid'
          ? `<button type="button" data-chat-action="deliver-order" data-order-id="${escapeHtml(order.id)}">Xác nhận giao hàng</button>`
          : ''}

        ${isCurrentBuyer && ['paid', 'delivering'].includes(order.status)
          ? `<button type="button" data-chat-action="complete-order" data-order-id="${escapeHtml(order.id)}">Đã nhận hàng</button>`
          : ''}

        ${canManageEscrow && order.status === 'completed' && ['escrow', 'paid'].includes(payment?.payment_status)
          ? `<button type="button" data-chat-action="admin-release-payment" data-payment-id="${escapeHtml(payment?.id || '')}" data-order-id="${escapeHtml(order.id)}">Admin duyệt giải ngân</button>`
          : ''}

        <button type="button" class="secondary" data-chat-action="support-ticket" data-order-id="${escapeHtml(order.id)}">Support</button>
      </div>

      ${settlementBlock(payment)}
      ${reviewBlock(review)}

      ${!review && isCurrentBuyer && order.status === 'completed' && payment?.payment_status === 'released' ? `
        <form class="chat-review-form" data-chat-form="review" data-order-id="${escapeHtml(order.id)}">
          <select name="rating">
            <option value="5">5 sao</option>
            <option value="4">4 sao</option>
            <option value="3">3 sao</option>
            <option value="2">2 sao</option>
            <option value="1">1 sao</option>
          </select>
          <input type="text" name="comment" placeholder="Nhận xét">
          <button type="submit">Đánh giá</button>
        </form>
      ` : ''}
    </section>
  `;
}
