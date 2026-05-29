import { getHomeUrl } from '../../api/http.js';
import { RfqAPI } from '../../api/rfq.js';
import {
  getProfileCompanyId,
  getProfileWpUserId,
} from '../../utils/format.js';

async function createDirectRfq(product, profile) {
  const buyerCompanyId = getProfileCompanyId(profile);
  const wpUserId = getProfileWpUserId(profile);

  if (!buyerCompanyId) {
    throw new Error('Tài khoản chưa gắn công ty nên chưa thể nhắn với người bán.');
  }

  if (Number(product.company_id) === Number(buyerCompanyId)) {
    throw new Error('Bạn không thể nhắn giao dịch với sản phẩm của chính công ty mình.');
  }

  const message = `Tôi muốn trao đổi và nhận báo giá cho sản phẩm ${product.name}.`;
  const created = await RfqAPI.create({
    buyer_company_id: buyerCompanyId,
    message,
    rfq_type: 'direct',
    created_by: 'buyer',
    items: [{
      product_id: product.id,
      quantity: 1,
      note: message,
    }],
  });

  const rfqId = created?.rfq?.id || created?.rfq_id || created?.id;

  if (!rfqId) {
    throw new Error('Không nhận được mã RFQ từ hệ thống.');
  }

  if (wpUserId) {
    await RfqAPI.sendNegotiation({
      rfq_id: rfqId,
      sender_id: wpUserId,
      sender_type: 'buyer',
      message,
    }).catch(() => null);
  }

  return rfqId;
}

export function initContactSeller({ getProfile, getProduct }) {
  document.getElementById('productGrid')?.addEventListener('click', async (event) => {
    const target = event.target.closest('[data-flow-contact]');

    if (!target) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();

    const product = getProduct(target.dataset.id);

    if (!product) {
      alert('Không tìm thấy sản phẩm để bắt đầu chat.');
      return;
    }

    target.disabled = true;
    const oldText = target.textContent;
    target.textContent = 'Đang mở chat...';

    try {
      const profile = await getProfile();

      if (!profile) {
        if (confirm('Bạn cần đăng nhập để chat với người bán. Chuyển tới trang đăng nhập?')) {
          window.location.href = getHomeUrl('/login');
        }
        return;
      }

      const rfqId = await createDirectRfq(product, profile);
      window.location.href = `${getHomeUrl('/chat')}?rfq_id=${encodeURIComponent(rfqId)}`;
    } catch (error) {
      alert(error.message || 'Không thể mở chat với người bán.');
    } finally {
      target.disabled = false;
      target.textContent = oldText;
    }
  });
}
