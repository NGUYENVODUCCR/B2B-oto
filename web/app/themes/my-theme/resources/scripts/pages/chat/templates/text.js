function mojibakeScore(value) {
  if (!value) {
    return 0;
  }

  const matches = String(value).match(/(?:Ã.|Ä.|Â.|Æ.|á»|áº|�)/g);

  return matches ? matches.length : 0;
}

function decodeUtf8FromLatin1(value) {
  const bytes = Uint8Array.from(
    Array.from(String(value || '')).map((char) => char.charCodeAt(0) & 0xff)
  );

  return new TextDecoder('utf-8', { fatal: false }).decode(bytes);
}

export function normalizeMojibakeText(value) {
  let raw = String(value ?? '');

  if (!raw) {
    return '';
  }

  const fixes = {
    'TH�NG TIN PHI�N GIAO D�CH': 'THÔNG TIN PHIÊN GIAO DỊCH',
    'TH�NG TIN PHI�N BULK': 'THÔNG TIN PHIÊN BULK',
    'S�n ph�m trong RFQ': 'Sản phẩm trong RFQ',
    'Tr�ng th�i RFQ': 'Trạng thái RFQ',
    'T�n s�n ph�m': 'Tên sản phẩm',
    'N�I DUNG NG��I D�NG G�I': 'NỘI DUNG NGƯỜI DÙNG GỬI',
    'NOI DUNG NGUOI DUNG GUI': 'NỘI DUNG NGƯỜI DÙNG GỬI',
    'Ng��i h� tr�': 'Người hỗ trợ',
    'H� tr�': 'Hỗ trợ',
    'ch�t l�ợ�ng': 'chất lượng',
    'uy t�n': 'uy tín',
    'Nguoi dung gui': 'Người dùng gửi',
  };

  Object.entries(fixes).forEach(([bad, good]) => {
    raw = raw.replaceAll(bad, good);
  });

  if (!/(?:Ã.|Ä.|Â.|Æ.|á»|áº|�)/.test(raw)) {
    return raw;
  }

  try {
    let best = raw;
    let bestScore = mojibakeScore(raw);
    let candidate = raw;

    for (let round = 0; round < 3; round += 1) {
      candidate = decodeUtf8FromLatin1(candidate);
      const score = mojibakeScore(candidate);

      if (score < bestScore || (score === bestScore && candidate.length >= best.length)) {
        best = candidate;
        bestScore = score;
      }

      if (!/(?:Ã.|Ä.|Â.|Æ.|á»|áº|�)/.test(candidate)) {
        break;
      }
    }

    return best || raw;
  } catch (_) {
    return raw;
  }
}
export function statusLabel(value) {
  const labels = {
    pending: 'Chờ xử lý',
    quoted: 'Đã có báo giá',
    negotiating: 'Đang thương lượng',
    closed: 'Đã đóng',
    accepted: 'Đã chấp nhận',
    rejected: 'Đã từ chối',
    draft: 'Chờ ký',
    signed: 'Đã ký',
    paid: 'Đã thanh toán escrow',
    delivering: 'Đang giao hàng',
    completed: 'Hoàn tất',
    cancelled: 'Đã hủy',
    escrow: 'Đang giữ tiền',
    released: 'Đã giải ngân',
    failed: 'Lỗi/hoàn tiền',
    buyer: 'Người mua',
    seller: 'Người bán',
    support: 'Người hỗ trợ',
    started: 'Buyer vừa yêu cầu',
    form_requested: 'Chờ buyer điền form',
    buyer_submitted: 'Buyer đã gửi form',
    published: 'Đang mở cho seller',
    fulfilled: 'Đã đủ số lượng',
    buyer_notified: 'Đã gửi lại buyer',
  };

  return labels[value] || value || '-';
}
