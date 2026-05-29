import { PLACEHOLDER_IMAGE, escapeHtml, formatMoney } from './common.js';

export function getProductStatusMeta(status) {
  const normalized = String(status || '').toLowerCase();
  const map = {
    active: {
      text: 'Đang hiển thị (Active)',
      className: 'status-active',
    },
    blocked: {
      text: 'Đã bị khóa (Blocked)',
      className: 'status-blocked',
    },
    deleted: {
      text: 'Seller đã xóa',
      className: 'status-deleted',
    },
    inactive: {
      text: 'Seller đã ẩn tin',
      className: 'status-inactive',
    },
  };

  return map[normalized] || {
    text: status || 'Chưa rõ',
    className: 'status-default',
  };
}

export function getProductImage(product) {
  if (!product?.images) {
    return PLACEHOLDER_IMAGE;
  }

  if (Array.isArray(product.images) && product.images.length > 0) {
    const first = product.images[0];

    return first?.image_url || first?.url || first || PLACEHOLDER_IMAGE;
  }

  return product.images.image_url || product.images.url || PLACEHOLDER_IMAGE;
}

export function getProductBrand(product) {
  return product?.brand_name
    || (typeof product?.brand === 'object' ? product.brand?.name : product?.brand)
    || 'Chưa cập nhật';
}

export function getProductColor(product) {
  return product?.color_name
    || (typeof product?.color === 'object' ? product.color?.name : product?.color)
    || 'Chưa cập nhật';
}


export function productCardTemplate(product) {
  const status = getProductStatusMeta(product.status);
  const currentDbStatus = String(product.status || '').toLowerCase();
  const image = getProductImage(product);
  const company = product.company_info?.company_name || 'Đang cập nhật';

  return `
    <div class="admin-card admin-product-card request-card">
      <img class="admin-product-thumb" src="${escapeHtml(image)}" alt="${escapeHtml(product.name || 'Product')}">

      <div class="admin-card-main">
        <h3>Bài đăng: ${escapeHtml(product.name || 'Chưa có tên')}</h3>

        <p class="admin-product-attributes">
          <strong>Hãng xe:</strong> <span class="text-brand">${escapeHtml(getProductBrand(product))}</span> |
          <strong>Màu xe:</strong> <span class="text-color">${escapeHtml(getProductColor(product))}</span>
        </p>

        <p><strong>Công ty:</strong> ${escapeHtml(company)}</p>
        <p><strong>Giá bán:</strong> ${formatMoney(product.price_from)}</p>
        <p><strong>Năm SX:</strong> ${escapeHtml(product.years || 'Chưa rõ')} - <strong>Số lượng:</strong> ${escapeHtml(product.quantity || 0)} SP còn lại</p>
        <p>
          <strong>Trạng thái:</strong>
          <span class="status-badge ${status.className}">${escapeHtml(status.text)}</span>
        </p>

        <div class="card-actions">
          <button class="info-btn view-detail-btn" data-id="${escapeHtml(product.id)}">
            ${currentDbStatus === 'deleted' || currentDbStatus === 'inactive' ? 'Xem chi tiết' : 'Xem & Sửa nhanh'}
          </button>

          ${currentDbStatus !== 'deleted' && currentDbStatus !== 'inactive' ? `
            ${currentDbStatus !== 'active' ? `
              <button class="action-btn product-approve-btn" data-id="${escapeHtml(product.id)}">Mở khóa bài đăng</button>
            ` : ''}
            ${currentDbStatus !== 'blocked' ? `
              <button class="action-btn product-block-btn" data-id="${escapeHtml(product.id)}">Khóa bài đăng</button>
            ` : ''}
          ` : ''}
        </div>
      </div>
    </div>
  `;
}


export function productDetailModalTemplate() {
  return `
    <div id="admin-product-detail-modal" class="admin-modal">
      <div class="admin-modal-dialog admin-modal-dialog-wide">
        <div class="admin-modal-header">
          <h2 id="admin-product-detail-modal-title">Chi tiết bài đăng</h2>
          <button type="button" id="close-product-detail-modal-btn" class="admin-modal-close" aria-label="Đóng">&times;</button>
        </div>
        <div id="deleted-product-detail-content"></div>
        <div class="admin-modal-footer">
          <button type="button" id="product-detail-close-btn" class="admin-dark-btn">Đóng</button>
        </div>
      </div>
    </div>
  `;
}

export function productEditModalTemplate() {
  return `
    <div id="admin-product-modal" class="admin-modal">
      <div class="admin-modal-dialog">
        <div class="admin-modal-header">
          <h2 id="modal-title">Chi tiết và Chỉnh sửa sản phẩm</h2>
          <button type="button" id="close-modal-btn" class="admin-modal-close" aria-label="Đóng">&times;</button>
        </div>

        <form id="admin-edit-product-form">
          <input type="hidden" id="edit-prod-id">

          <div class="admin-form-row">
            <label for="edit-prod-name">Tên sản phẩm/Xe:</label>
            <input type="text" id="edit-prod-name">
          </div>

          <div class="admin-form-grid admin-form-grid-two">
            <div class="admin-form-row">
              <label for="edit-prod-brand">Hãng xe:</label>
              <input type="text" id="edit-prod-brand" placeholder="Nhập tên hãng xe...">
            </div>
            <div class="admin-form-row">
              <label for="edit-prod-color">Màu sắc:</label>
              <input type="text" id="edit-prod-color" placeholder="Nhập màu sắc xe...">
            </div>
          </div>

          <div class="admin-form-grid">
            <div class="admin-form-row">
              <label for="edit-prod-price">Giá bán khởi điểm (VNĐ):</label>
              <input type="number" id="edit-prod-price">
            </div>
            <div class="admin-form-row">
              <label for="edit-prod-years">Năm sản xuất:</label>
              <input type="number" id="edit-prod-years">
            </div>
            <div class="admin-form-row">
              <label for="edit-prod-quantity">Số lượng:</label>
              <input type="number" id="edit-prod-quantity">
            </div>
          </div>

          <div class="admin-form-row">
            <label for="edit-prod-status">Trạng thái hệ thống:</label>
            <select id="edit-prod-status">
              <option value="deleted">Seller đã xóa (Deleted)</option>
              <option value="active">Đang hiển thị (Active)</option>
              <option value="inactive">Seller đã ẩn (Inactive)</option>
              <option value="blocked">Đã bị khóa (Blocked)</option>
            </select>
          </div>

          <div class="admin-form-row">
            <label for="edit-prod-description">Mô tả sản phẩm:</label>
            <textarea id="edit-prod-description" rows="4"></textarea>
          </div>

          <div class="admin-modal-footer">
            <button type="button" id="modal-cancel-btn" class="admin-secondary-btn">Hủy bỏ</button>
            <button type="submit" id="modal-submit-btn" class="admin-primary-btn">Lưu cập nhật</button>
          </div>
        </form>
      </div>
    </div>
  `;
}


export function productDetailTemplate(product, detailJson = null) {
  const productFromDetail = detailJson?.data?.product || detailJson?.product || product || {};
  const company = detailJson?.data?.company || detailJson?.company || product?.company_info || {};
  const images = detailJson?.data?.images || productFromDetail.images || product.images || [];
  const status = getProductStatusMeta(productFromDetail.status || product.status);
  const docs = Array.isArray(company.documents) ? company.documents : [];
  const logoDoc = docs.find((doc) => {
    const type = String(doc?.type || '').toLowerCase();
    const url = String(doc?.url || '').toLowerCase();

    return type.includes('logo') || url.includes('logo');
  });

  return `
    <div class="admin-detail-grid">
      <section class="admin-detail-panel admin-detail-panel-blue">
        <h3>Thông tin doanh nghiệp</h3>
        <p><strong>Tên công ty:</strong> ${escapeHtml(company.company_name || company.name || 'Chưa cập nhật')}</p>
        <p><strong>Người đại diện:</strong> ${escapeHtml(company.representative_name || company.contact_name || 'Chưa cập nhật')}</p>
        <p><strong>Mã số thuế:</strong> ${escapeHtml(company.tax_code || company.tax_no || 'Chưa cập nhật')}</p>
        <p><strong>Địa chỉ:</strong> ${escapeHtml(company.address || 'Chưa cập nhật')}</p>
        ${logoDoc?.url ? `<img class="admin-company-logo" src="${escapeHtml(logoDoc.url)}" alt="Company logo">` : ''}
      </section>

      <section class="admin-detail-panel">
        <h3>Thông tin bài đăng</h3>
        <p><strong>Tên xe:</strong> ${escapeHtml(productFromDetail.name || product.name || 'N/A')}</p>
        <p><strong>Giá bán:</strong> ${formatMoney(productFromDetail.price_from || product.price_from)}</p>
        <p><strong>Hãng xe:</strong> <span class="text-brand">${escapeHtml(getProductBrand(productFromDetail))}</span></p>
        <p><strong>Màu sắc:</strong> <span class="text-color">${escapeHtml(getProductColor(productFromDetail))}</span></p>
        <p><strong>Năm SX:</strong> ${escapeHtml(productFromDetail.years || product.years || 'Chưa rõ')}</p>
        <p><strong>Số lượng:</strong> ${escapeHtml(productFromDetail.quantity || product.quantity || 0)}</p>
        <p><strong>Trạng thái:</strong> <span class="status-badge ${status.className}">${escapeHtml(status.text)}</span></p>
        <p><strong>Mô tả:</strong></p>
        <div class="admin-description-box">${escapeHtml(productFromDetail.description || product.description || 'Không có mô tả')}</div>
      </section>
    </div>

    <section class="admin-gallery-section">
      <h3>Hình ảnh sản phẩm</h3>
      ${productImagesTemplate(images)}
    </section>
  `;
}

function productImagesTemplate(images) {
  if (!Array.isArray(images) || images.length === 0) {
    return '<p class="admin-state-message">Không có hình ảnh.</p>';
  }

  return `
    <div class="admin-product-gallery">
      ${images.map((image) => {
        const imageUrl = typeof image === 'object' ? image.image_url || image.url : image;

        return `<img src="${escapeHtml(imageUrl || PLACEHOLDER_IMAGE)}" alt="Product image">`;
      }).join('')}
    </div>
  `;
}
