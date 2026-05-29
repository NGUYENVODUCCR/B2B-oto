import { PLACEHOLDER_IMAGE, escapeHtml } from './common.js';

export function getSellerStatusMeta(status) {
  const normalized = String(status || '').toLowerCase();
  const map = {
    pending: {
      text: 'Đang chờ xác minh giấy tờ',
      className: 'status-pending',
    },
    verified: {
      text: 'Đã xác minh',
      className: 'status-verified',
    },
    unverified: {
      text: 'Không xác minh được',
      className: 'status-unverified',
    },
  };

  return map[normalized] || {
    text: status || 'Chưa rõ',
    className: 'status-default',
  };
}


export function sellerRequestCardTemplate(request) {
  const status = getSellerStatusMeta(request.status);
  const currentStatus = String(request.status || '').toLowerCase(); // Chuẩn hóa chữ thường
  const sellerCompanyId = Number(
    request.company_id
    || request.seller_company_id
    || request.company?.id
    || request.id
    || 0
  );

  return `
    <div class="admin-card request-card">
      <h3>Công ty: ${escapeHtml(request.company_name || 'Chưa cập nhật')}</h3>
      <p><strong>Người đại diện:</strong> ${escapeHtml(request.representative_name || 'Chưa cập nhật')}</p>
      <p><strong>Mã số thuế:</strong> ${escapeHtml(request.tax_code || 'Chưa cập nhật')}</p>
      <p>
        <strong>Trạng thái:</strong>
        <span class="status-badge ${status.className}">${escapeHtml(status.text)}</span>
      </p>

      <div class="card-actions">
        <button class="info-btn view-seller-detail-btn" data-id="${escapeHtml(request.id)}">
          Xem thông tin & Giấy tờ
        </button>
        
        ${currentStatus === 'verified' ? `
          <button type="button" class="admin-revenue-view-btn action-btn" 
            data-seller-id="${escapeHtml(sellerCompanyId)}" 
            data-company-name="${escapeHtml(request.company_name || 'Seller')}"
            style="background-color: #10b981; color: #fff; font-weight: bold;">
            📊 Doanh thu
          </button>
        ` : ''}

        ${currentStatus === 'pending' ? `
          <button class="action-btn approve-btn" data-id="${escapeHtml(request.id)}">Đã xác minh</button>
          <button class="action-btn reject-btn" data-id="${escapeHtml(request.id)}">Không xác minh được</button>
        ` : ''}
      </div>
    </div>
  `;
}


export function sellerModalTemplate() {
  return `
    <div id="admin-seller-modal" class="admin-modal">
      <div class="admin-modal-dialog admin-modal-dialog-wide">
        <div class="admin-modal-header">
          <h2>Chi tiết thông tin & Giấy tờ Seller</h2>
          <button type="button" id="close-seller-modal-btn" class="admin-modal-close" aria-label="Đóng">&times;</button>
        </div>
        <div id="admin-seller-modal-content"></div>
        <div class="admin-modal-footer">
          <button type="button" id="seller-modal-close-btn" class="admin-secondary-btn">Đóng cửa sổ</button>
        </div>
      </div>
    </div>
  `;
}


export function sellerDetailTemplate(request) {
  return `
    <div class="admin-detail-grid">
      <section class="admin-detail-panel">
        <h3>Thông tin công ty</h3>
        <p><strong>Tên công ty:</strong> ${escapeHtml(request.company_name || 'Chưa cập nhật')}</p>
        <p><strong>Mã số thuế:</strong> ${escapeHtml(request.tax_code || 'Chưa cập nhật')}</p>
        <p><strong>Email liên hệ:</strong> ${escapeHtml(request.company_email || 'Chưa cập nhật')}</p>
        <p><strong>Địa chỉ văn phòng:</strong> ${escapeHtml(request.address || 'Chưa cập nhật')}</p>
      </section>

      <section class="admin-detail-panel admin-detail-panel-blue">
        <h3>Thông tin đại diện</h3>
        <p><strong>Người đại diện:</strong> ${escapeHtml(request.representative_name || 'Chưa cập nhật')}</p>
        <p><strong>Số định danh CCCD:</strong> ${escapeHtml(request.citizen_id_number || 'Chưa cập nhật')}</p>
        <p><strong>ID tài khoản:</strong> ${escapeHtml(request.user_id || 'N/A')}</p>
        <p><strong>Thời gian nộp đơn:</strong> ${escapeHtml(request.created_at || 'N/A')}</p>
      </section>
    </div>

    <h3 class="admin-documents-title">Danh sách giấy tờ pháp lý đính kèm:</h3>
    ${sellerDocumentsTemplate(request.documents)}
  `;
}

export function sellerDocumentsTemplate(documents) {
  if (!Array.isArray(documents) || documents.length === 0) {
    return '<p class="admin-empty-documents">Doanh nghiệp này không đính kèm ảnh tài liệu chứng minh.</p>';
  }

  return `
    <div id="seller-documents-grid" class="admin-documents-grid">
      ${documents.map((doc) => `
        <div class="admin-document-card">
          <div class="admin-document-label" title="${escapeHtml(doc.label || 'Tài liệu')}">
            ${escapeHtml(doc.label || 'Hình ảnh giấy tờ')}
          </div>
          <a href="${escapeHtml(doc.url || '#')}" target="_blank" rel="noopener" title="Kích chuột để xem ảnh gốc Cloudinary độ phân giải cao">
            <img class="admin-document-image" src="${escapeHtml(doc.url || PLACEHOLDER_IMAGE)}" alt="${escapeHtml(doc.label || 'Tài liệu')}">
          </a>
        </div>
      `).join('')}
    </div>
  `;
}
