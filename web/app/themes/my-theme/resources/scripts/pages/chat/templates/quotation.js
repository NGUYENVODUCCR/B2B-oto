import { escapeHtml, formatCurrency } from '../../../utils/format.js';
import { statusLabel } from './text.js';

export function quotationCard(quotationDetail, canAct) {
  const quote = quotationDetail?.quotation || quotationDetail;
  const total = Number(quotationDetail?.total_amount || 0);
  const subtotal = Number(quotationDetail?.subtotal_amount || total);
  const discount = Number(quotationDetail?.discount_total || 0);
  const lines = quotationDetail?.pricing?.lines || [];

  return `
    <div class="chat-quotation-card">
      <div>
        <strong>Seller #${escapeHtml(quote.seller_company_id)}</strong>
        <span>${escapeHtml(statusLabel(quote.status))}</span>
      </div>
      ${lines.length ? `
        <div class="chat-quotation-lines">
          ${lines.map((line) => `
            <span>SL ${escapeHtml(line.quantity)} · Giá ${formatCurrency(line.unit_price || 0)} · KM ${escapeHtml(line.discount_percent || 0)}%</span>
          `).join('')}
        </div>
      ` : ''}
      ${discount > 0 ? `
        <div class="chat-quotation-totals">
          <span>Tạm tính</span><strong>${formatCurrency(subtotal)}</strong>
          <span>Khuyến mãi</span><strong>-${formatCurrency(discount)}</strong>
        </div>
      ` : ''}
      <div class="chat-price">${formatCurrency(total)}</div>
      ${canAct && quote.status === 'pending' ? `
        <div class="chat-inline-actions">
          <button type="button" data-chat-action="accept-quote" data-quotation-id="${escapeHtml(quote.id)}">Chấp nhận</button>
          <button type="button" class="secondary" data-chat-action="reject-quote" data-quotation-id="${escapeHtml(quote.id)}">Từ chối</button>
        </div>
      ` : ''}
    </div>
  `;
}
