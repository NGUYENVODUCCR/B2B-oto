export function createSupportRequestModal({
  state,
  qs,
  wpUserId,
  companyId,
  currentUserName,
  formatCurrency,
  statusLabel,
  buildSupportMetaBlock,
  SupportAPI,
  emitRealtimeEvent,
  openSupportTicket,
}) {
  function supportCurrentUserLabel() {
    return currentUserName()
      || state.profile?.wp_user?.display_name
      || state.profile?.user?.fullname
      || `User #${wpUserId()}`;
  }

  function buildContractSupportContext(contractId) {
    const contract = state.contractDetail?.contract || {};
    const data = state.contractDetail?.contract_data || {};
    const buyer = data.buyer_company || {};
    const seller = data.seller_company || {};
    const items = Array.isArray(data.items) ? data.items : [];

    const itemLines = items.length
      ? items.map((item, index) => (
        `${index + 1}. ${item.product_name || `Sản phẩm #${item.product_id || ''}`} - SL: ${item.quantity || 0} - Đơn giá: ${formatCurrency(item.unit_price || 0)} - Thành tiền: ${formatCurrency(item.line_total || 0)}`
      )).join('\n')
      : 'Chưa có danh sách sản phẩm.';

    return [
      'ĐƠN YÊU CẦU HỖ TRỢ HỢP ĐỒNG',
      `Người gửi yêu cầu: ${supportCurrentUserLabel()}`,
      `RFQ ID: ${state.activeId || '-'}`,
      `Contract ID: ${contractId || contract.id || '-'}`,
      `Trạng thái hợp đồng: ${statusLabel(contract.status || '-')}`,
      `Tổng giá trị hợp đồng: ${formatCurrency(data.total_amount || 0)}`,
      '',
      '--- Bên bán ---',
      `Tên công ty: ${seller.company_name || '-'}`,
      `Mã số thuế: ${seller.tax_code || '-'}`,
      `Địa chỉ: ${seller.address || '-'}`,
      `Người đại diện: ${seller.representative_name || '-'}`,
      '',
      '--- Bên mua ---',
      `Tên công ty: ${buyer.company_name || '-'}`,
      `Mã số thuế: ${buyer.tax_code || '-'}`,
      `Địa chỉ: ${buyer.address || '-'}`,
      `Người đại diện: ${buyer.representative_name || '-'}`,
      '',
      '--- Sản phẩm trong hợp đồng ---',
      itemLines,
    ].join('\n');
  }

  function buildOrderSupportContext(orderId) {
    const order = state.orderDetail?.order || {};
    const payment = state.orderDetail?.payment || {};
    const settlement = payment?.settlement || null;

    return [
      'ĐƠN YÊU CẦU HỖ TRỢ ORDER / ESCROW',
      `Người gửi yêu cầu: ${supportCurrentUserLabel()}`,
      `WP User ID: ${wpUserId() || '-'}`,
      `Company ID: ${companyId() || '-'}`,
      `RFQ ID: ${state.activeId || '-'}`,
      `Order ID: ${orderId || order.id || '-'}`,
      `Buyer company ID: ${order.buyer_company_id || '-'}`,
      `Seller company ID: ${order.seller_company_id || '-'}`,
      `Trạng thái order: ${statusLabel(order.status || '-')}`,
      `Tổng tiền order: ${formatCurrency(order.total_amount || 0)}`,
      '',
      '--- Thanh toán escrow ---',
      `Payment ID: ${payment.id || '-'}`,
      `Trạng thái payment: ${statusLabel(payment.payment_status || 'pending')}`,
      `Số tiền payment: ${formatCurrency(payment.amount || order.total_amount || 0)}`,
      settlement ? `Quyết toán: giải ngân seller ${formatCurrency(settlement.release_amount || 0)}, hoàn buyer ${formatCurrency(settlement.refund_amount || 0)}` : 'Chưa có quyết toán escrow.',
    ].join('\n');
  }

  function ensureSupportRequestModal() {
    if (qs('chatSupportRequestModal')) {
      return;
    }

    document.body.insertAdjacentHTML('beforeend', `
      <div id="chatSupportRequestModal" class="chat-bulk-modal hidden" aria-hidden="true">
        <div class="chat-bulk-modal-dialog" role="dialog" aria-modal="true">
          <header class="chat-bulk-modal-head">
            <div>
              <span>Support</span>
              <h2 id="chatSupportRequestTitle">Gửi yêu cầu hỗ trợ</h2>
            </div>
            <button type="button" class="secondary" data-support-modal-close>Đóng</button>
          </header>

          <form id="chatSupportRequestForm" class="chat-bulk-modal-content">
            <div class="chat-bulk-fieldset">
              <p>Thông tin sẽ gửi cho support</p>
              <pre id="chatSupportRequestContext" style="white-space:pre-wrap;background:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;padding:12px;max-height:220px;overflow:auto;font-size:13px;"></pre>
            </div>

            <label class="chat-bulk-textarea">
              <span>Nội dung bạn muốn trao đổi với support</span>
              <textarea name="message" rows="5" placeholder="Nhập vấn đề cần support xử lý..." required></textarea>
            </label>

            <button type="submit" class="chat-bulk-submit">Gửi support</button>
          </form>
        </div>
      </div>
    `);
  }

  function openSupportRequestModal(kind, context, extra = {}) {
    ensureSupportRequestModal();

    state.supportRequest = {
      kind,
      context,
      ...extra,
    };

    const modal = qs('chatSupportRequestModal');
    const title = qs('chatSupportRequestTitle');
    const contextBox = qs('chatSupportRequestContext');
    const form = qs('chatSupportRequestForm');

    if (title) {
      title.innerText = kind === 'contract'
        ? 'Yêu cầu support hợp đồng'
        : 'Yêu cầu support order / escrow';
    }

    if (contextBox) {
      contextBox.innerText = context;
    }

    if (form) {
      form.reset();
    }

    modal?.classList.remove('hidden');
    modal?.setAttribute('aria-hidden', 'false');

    window.setTimeout(() => {
      form?.elements?.message?.focus?.();
    }, 50);
  }

  function closeSupportRequestModal() {
    const modal = qs('chatSupportRequestModal');

    if (!modal) return;

    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');
    state.supportRequest = null;
  }

  async function handleSupportRequestSubmit(event) {
    const form = event.target.closest?.('#chatSupportRequestForm');

    if (!form) {
      return;
    }

    event.preventDefault();

    const userMessage = String(form.elements.message?.value || '').trim();

    if (!userMessage) {
      alert('Vui lòng nhập nội dung cần support xử lý.');
      form.elements.message?.focus();
      return;
    }

    const request = state.supportRequest;

    if (!request) {
      alert('Không tìm thấy thông tin support cần gửi.');
      return;
    }

    if (wpUserId() <= 0) {
      alert('Không tìm thấy user_id. Vui lòng đăng nhập lại rồi thử gửi support.');
      return;
    }

    const activeRfqId = Number(state.activeId || 0);
    const hasActiveRfq = Number.isFinite(activeRfqId) && activeRfqId > 0;
    const supportType = request.kind === 'contract'
      ? 'contract'
      : (request.kind === 'order' ? 'payment' : 'dispute');
    const supportReference = hasActiveRfq
      ? `RFQ #${activeRfqId}`
      : (request.contract_id ? `Contract #${request.contract_id}` : '');
    const payload = {
      user_id: wpUserId(),
      type: supportType,
      support_reference: supportReference,
      message: `${request.kind === 'contract' ? '[SUPPORT HỢP ĐỒNG]' : '[SUPPORT ORDER / ESCROW]'}\n${buildSupportMetaBlock(request)}\n\n${request.context}\n\nNỘI DUNG NGƯỜI DÙNG GỬI\n${userMessage}`,
    };

    if (hasActiveRfq) {
      payload.rfq_id = activeRfqId;
    }

    if (request.order_id) {
      payload.order_id = Number(request.order_id);
    }

    try {
      const created = await SupportAPI.createTicket(payload);

      const ticketId =
      created?.ticket?.id ||
      created?.data?.ticket?.id ||
      created?.data?.ticket?.ticket?.id ||
      created?.ticket?.ticket?.id ||
      created?.ticket_id ||
      created?.data?.ticket_id ||
      created?.id ||
      0;

      if (!ticketId) {
        console.log('CREATE SUPPORT TICKET RESPONSE:', created);
        alert('Đã gửi yêu cầu support nhưng không lấy được mã ticket. Mở F12 Console để kiểm tra response.');
        return;
      }

      closeSupportRequestModal();

      emitRealtimeEvent('b2b:support:changed', {
        ticket_id: ticketId,
        type: request.kind,
        order_id: request.order_id || null,
        contract_id: request.contract_id || null,
      });

      alert('Đã tạo cuộc hội thoại support #' + ticketId);
      await openSupportTicket(ticketId);
    } catch (error) {
      console.error('CREATE SUPPORT TICKET ERROR:', error);
      alert(error?.message || 'Không thể tạo ticket support. Vui lòng kiểm tra lại API.');
    }
  }

  function handleSupportRequestModalClick(event) {
    if (event.target.closest?.('[data-support-modal-close]')) {
      closeSupportRequestModal();
    }
  }

  return {
    buildContractSupportContext,
    buildOrderSupportContext,
    closeSupportRequestModal,
    ensureSupportRequestModal,
    handleSupportRequestModalClick,
    handleSupportRequestSubmit,
    openSupportRequestModal,
  };
}
