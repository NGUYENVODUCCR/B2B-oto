import { SupportAPI } from '../../api/support.js';
import { ChatAPI } from '../../api/chat.js';
import { QuotationAPI } from '../../api/quotation.js';
import { ContractAPI } from '../../api/contract.js';
import { OrderAPI } from '../../api/order.js';

function formatCurrency(value) {
  return `${Number(value || 0).toLocaleString('vi-VN')} VND`;
}
function stripInternalIds(text = '') {
  return String(text || '')
    .split('\n')
    .filter((line) => {
      const normalized = String(line || '').toLowerCase().trim();

      return !(
        normalized.startsWith('wp user id:')
        || normalized.startsWith('wp_user_id:')
        || normalized.startsWith('user id:')
        || normalized.startsWith('user_id:')
        || normalized.startsWith('company id:')
        || normalized.startsWith('company_id:')
      );
    })
    .join('\n')
    .replace(/\n{3,}/g, '\n\n')
    .trim();
}

function safeText(value, fallback = 'Chưa cập nhật') {
  const text = String(value ?? '').trim();

  return text ? text : fallback;
}

function pickFirst(...values) {
  for (const value of values) {
    const text = String(value ?? '').trim();

    if (text) {
      return text;
    }
  }

  return '';
}

function normalizeCompany(raw = {}, fallback = {}) {
  return {
    company_name: pickFirst(
      raw.company_name,
      raw.name,
      raw.company,
      raw.title,
      fallback.company_name,
      fallback.name
    ),
    tax_code: pickFirst(
      raw.tax_code,
      raw.tax_number,
      raw.tax,
      raw.company_tax_code,
      fallback.tax_code,
      fallback.tax_number
    ),
    address: pickFirst(
      raw.address,
      raw.company_address,
      raw.full_address,
      fallback.address,
      fallback.company_address
    ),
    representative_name: pickFirst(
      raw.representative_name,
      raw.representative,
      raw.legal_representative,
      raw.owner_name,
      raw.contact_name,
      fallback.representative_name,
      fallback.representative
    ),
  };
}

export function normalizeSupportType(typeValue = '') {
  const type = String(typeValue || '').toLowerCase();

  if (
    type.includes('bulk') ||
    type.includes('thu mua') ||
    type.includes('số lượng lớn')
  ) {
    return 'bulk';
  }

  if (
    type.includes('contract') ||
    type.includes('hợp đồng') ||
    type.includes('hop dong')
  ) {
    return 'contract';
  }

  if (
    type.includes('order') ||
    type.includes('escrow') ||
    type.includes('payment') ||
    type.includes('thanh toán') ||
    type.includes('thanh toan')
  ) {
    return 'order';
  }

  return 'rfq';
}

export function getSupportReferenceValue(formData) {
  const keys = [
    'order_id',
    'rfq_id',
    'bulk_id',
    'reference',
    'rfq_or_bulk',
    'support_reference',
    'code',
  ];

  for (const key of keys) {
    const value = String(formData.get(key) || '').trim();

    if (value) {
      return value;
    }
  }

  return '';
}

function extractNumber(value = '') {
  const match = String(value || '').match(/\d+/);

  return match ? Number(match[0]) : 0;
}

function contractContextText(contractDetail, rfqId) {
  const contract = contractDetail?.contract || {};
  const data = contractDetail?.contract_data || {};

  const buyer = normalizeCompany(
    data.buyer_company || data.buyer || contractDetail?.buyer_company || contractDetail?.buyer || {},
    {
      company_name: data.buyer_company_name || contractDetail?.buyer_company_name || contract.buyer_company_name,
      tax_code: data.buyer_tax_code || contractDetail?.buyer_tax_code || contract.buyer_tax_code,
      address: data.buyer_address || contractDetail?.buyer_address || contract.buyer_address,
      representative_name: data.buyer_representative_name || contractDetail?.buyer_representative_name || contract.buyer_representative_name,
    }
  );

  const seller = normalizeCompany(
    data.seller_company || data.seller || contractDetail?.seller_company || contractDetail?.seller || {},
    {
      company_name: data.seller_company_name || contractDetail?.seller_company_name || contract.seller_company_name,
      tax_code: data.seller_tax_code || contractDetail?.seller_tax_code || contract.seller_tax_code,
      address: data.seller_address || contractDetail?.seller_address || contract.seller_address,
      representative_name: data.seller_representative_name || contractDetail?.seller_representative_name || contract.seller_representative_name,
    }
  );

  const items = Array.isArray(data.items)
    ? data.items
    : (Array.isArray(contractDetail?.items) ? contractDetail.items : []);

  const itemLines = items.length
    ? items.map((item, index) => (
      `${index + 1}. ${safeText(item.product_name || item.name || `Sản phẩm #${item.product_id || ''}`)} - SL: ${safeText(item.quantity || 0, '0')} - Đơn giá: ${formatCurrency(item.unit_price || 0)} - Thành tiền: ${formatCurrency(item.line_total || 0)}`
    )).join('\n')
    : 'Chưa có danh sách sản phẩm.';

  return [
    '[SUPPORT HỢP ĐỒNG]',
    '',
    '===== THÔNG TIN PHIÊN GIAO DỊCH =====',
    `RFQ ID: ${safeText(rfqId, '-')}`,
    `Mã hợp đồng: ${safeText(contract.contract_no || `Contract #${contract.id || '-'}`, '-')}`,
    `Trạng thái hợp đồng: ${safeText(contract.status)}`,
    `Tổng giá trị hợp đồng: ${formatCurrency(data.total_amount || contract.total_amount || 0)}`,
    '',
    '--- Bên mua ---',
    `Tên công ty: ${safeText(buyer.company_name)}`,
    `Mã số thuế: ${safeText(buyer.tax_code)}`,
    `Địa chỉ: ${safeText(buyer.address)}`,
    `Người đại diện: ${safeText(buyer.representative_name)}`,
    '',
    '--- Bên bán ---',
    `Tên công ty: ${safeText(seller.company_name)}`,
    `Mã số thuế: ${safeText(seller.tax_code)}`,
    `Địa chỉ: ${safeText(seller.address)}`,
    `Người đại diện: ${safeText(seller.representative_name)}`,
    '',
    '--- Sản phẩm trong hợp đồng ---',
    itemLines,
  ].join('\n');
}

function orderContextText(orderDetail, rfqId) {
  const order = orderDetail?.order || {};
  const payment = orderDetail?.payment || {};

  return [
    '[SUPPORT ORDER / ESCROW]',
    '',
    '===== THÔNG TIN PHIÊN GIAO DỊCH =====',
    `RFQ ID: ${rfqId || '-'}`,
    `Order ID: ${order.id || '-'}`,
    `Trạng thái order: ${order.status || '-'}`,
    `Payment: ${payment.payment_status || '-'}`,
    `Tổng tiền order: ${formatCurrency(order.total_amount || payment.amount || 0)}`,
  ].join('\n');
}

function rfqContextText(rfqDetail, rfqId) {
  const rfq = rfqDetail?.rfq || {};
  const items = Array.isArray(rfqDetail?.items) ? rfqDetail.items : [];

  const itemLines = items.length
    ? items.map((item, index) => (
      `${index + 1}. ${item.product_name || `Sản phẩm #${item.product_id || ''}`} - SL: ${item.quantity || 0}`
    )).join('\n')
    : 'Chưa có sản phẩm trong RFQ.';

  return [
    '[SUPPORT RFQ]',
    '',
    '===== THÔNG TIN PHIÊN GIAO DỊCH =====',
    `RFQ ID: ${rfqId || rfq.id || '-'}`,
    `Trạng thái RFQ: ${rfq.status || '-'}`,
    `Tên sản phẩm: ${rfq.product_names || '-'}`,
    '',
    '--- Sản phẩm trong RFQ ---',
    itemLines,
  ].join('\n');
}

function bulkContextText(bulk) {
  const product = bulk?.product_info || {};

  return [
    '[SUPPORT BULK]',
    '',
    '===== THÔNG TIN PHIÊN BULK =====',
    `Bulk ID: ${bulk?.id || '-'}`,
    `Trạng thái: ${bulk?.status || '-'}`,
    `Buyer: ${bulk?.buyer_company_name || '-'}`,
    `Sản phẩm: ${product.product_name || '-'}`,
    `Hãng xe: ${product.brand || '-'}`,
    `Model: ${product.model || '-'}`,
    `Năm: ${product.year || '-'}`,
    `Màu: ${product.color || '-'}`,
    `Số lượng cần mua: ${product.quantity || bulk?.requested_quantity || '-'}`,
    `Giá mong muốn: ${formatCurrency(product.target_price || 0)}`,
    `Mô tả: ${product.description || '-'}`,
  ].join('\n');
}

async function findAcceptedQuotationByRfq(rfqId) {
  const quotations = await QuotationAPI.byRfq(rfqId).catch(() => []);

  return (Array.isArray(quotations) ? quotations : []).find((item) => {
    const quote = item.quotation || item;

    return String(quote.status || '').toLowerCase() === 'accepted';
  }) || null;
}

function validReferenceResult({ context, orderId, rfqId, bulkId }) {
  return {
    context: stripInternalIds(context),
    orderId,
    rfqId,
    bulkId,
    referenceValid: true,
    referenceError: '',
  };
}

function invalidReferenceResult(kind, message, contextText = '') {
  const supportKind = String(kind || 'rfq').toUpperCase();
  const normalizedMessage = String(message || 'Mã giao dịch không hợp lệ.').trim();

  return {
    context: stripInternalIds(contextText || [`[SUPPORT ${supportKind}]`, '', normalizedMessage].join('\n')),
    orderId: undefined,
    rfqId: undefined,
    bulkId: undefined,
    referenceValid: false,
    referenceError: normalizedMessage,
  };
}

export async function buildSupportContextByReference(type, referenceValue) {
  const referenceRaw = String(referenceValue || '').trim();
  const referenceText = referenceRaw.toLowerCase();
  const preferredKind = normalizeSupportType(type);
  const explicitKind = referenceText.includes('bulk')
    ? 'bulk'
    : (referenceText.includes('rfq') ? 'rfq' : '');
  const refId = extractNumber(referenceRaw);

  if (!refId) {
    const fallbackKind = explicitKind || preferredKind || 'rfq';

    return invalidReferenceResult(
      fallbackKind,
      'Vui lòng nhập đúng mã RFQ hoặc Bulk từ trang Tin nhắn.'
    );
  }

  const [bulkRows, rfqDetail] = await Promise.all([
    SupportAPI.listBulkPurchases().catch(() => []),
    ChatAPI.detail(refId).catch(() => null),
  ]);

  const bulk = (Array.isArray(bulkRows) ? bulkRows : [])
    .find((item) => String(item.id) === String(refId));
  const hasBulk = Boolean(bulk);
  const hasRfq = Boolean(rfqDetail);

  let kind = explicitKind || preferredKind;

  if (!explicitKind) {
    if (preferredKind === 'bulk') {
      kind = hasBulk ? 'bulk' : 'rfq';
    } else if (hasRfq) {
      kind = preferredKind;
    } else if (hasBulk) {
      kind = 'bulk';
    }
  }

  if (kind === 'bulk') {
    if (!hasBulk) {
      return invalidReferenceResult(
        'bulk',
        `Không tìm thấy Bulk #${refId} trong các cuộc giao dịch của bạn.`
      );
    }

    return validReferenceResult({
      context: bulkContextText(bulk),
      orderId: undefined,
      rfqId: undefined,
      bulkId: refId,
    });
  }

  if (!rfqDetail) {
    return invalidReferenceResult(
      'rfq',
      `Không tìm thấy RFQ #${refId} trong các cuộc giao dịch của bạn.`
    );
  }

  if (kind === 'rfq') {
    return validReferenceResult({
      context: rfqContextText(rfqDetail, refId),
      orderId: undefined,
      rfqId: refId,
      bulkId: undefined,
    });
  }

  const accepted = await findAcceptedQuotationByRfq(refId);

  if (!accepted) {
    return validReferenceResult({
      context: [
        kind === 'contract' ? '[SUPPORT HỢP ĐỒNG]' : '[SUPPORT ORDER / ESCROW]',
        '',
        `RFQ ID: ${refId}`,
        'RFQ này chưa có báo giá được buyer chấp nhận nên chưa tìm thấy hợp đồng/order.',
        '',
        rfqContextText(rfqDetail, refId),
      ].join('\n'),
      orderId: undefined,
      rfqId: refId,
      bulkId: undefined,
    });
  }

  const quote = accepted.quotation || accepted;
  const contractRef = await ContractAPI.byQuotation(quote.id).catch(() => null);
  const contractId = contractRef?.contract?.id || contractRef?.contract_id || contractRef?.id;

  if (!contractId) {
    return validReferenceResult({
      context: [
        kind === 'contract' ? '[SUPPORT HỢP ĐỒNG]' : '[SUPPORT ORDER / ESCROW]',
        '',
        `RFQ ID: ${refId}`,
        'Báo giá đã được chấp nhận nhưng chưa tìm thấy hợp đồng.',
      ].join('\n'),
      orderId: undefined,
      rfqId: refId,
      bulkId: undefined,
    });
  }

  const contractDetail = contractRef?.contract
    ? contractRef
    : await ContractAPI.detail(contractId).catch(() => null);

  if (kind === 'contract') {
    return validReferenceResult({
      context: contractContextText(contractDetail, refId),
      orderId: undefined,
      rfqId: refId,
      bulkId: undefined,
    });
  }

  const orderId = contractDetail?.order?.id || contractDetail?.contract?.order_id || 0;

  if (!orderId) {
    return validReferenceResult({
      context: [
        '[SUPPORT ORDER / ESCROW]',
        '',
        `RFQ ID: ${refId}`,
        `Contract ID: ${contractId}`,
        'Hợp đồng đã có nhưng chưa tìm thấy order.',
        '',
        contractContextText(contractDetail, refId),
      ].join('\n'),
      orderId: undefined,
      rfqId: refId,
      bulkId: undefined,
    });
  }

  const orderDetail = await OrderAPI.detail(orderId).catch(() => null);

  return validReferenceResult({
    context: orderContextText(orderDetail, refId),
    orderId,
    rfqId: refId,
    bulkId: undefined,
  });
}


