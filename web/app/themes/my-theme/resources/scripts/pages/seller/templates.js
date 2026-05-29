const EMPTY_IMAGE = `data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><rect width="100%" height="100%" fill="%23eee"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="sans-serif" font-size="10" fill="%23aaa">No Image</text></svg>`;

function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function getProductImage(product) {
  if (!product?.images) {
    return EMPTY_IMAGE;
  }

  if (Array.isArray(product.images) && product.images.length > 0) {
    return product.images[0].image_url || product.images[0] || EMPTY_IMAGE;
  }

  return product.images.image_url || EMPTY_IMAGE;
}

export function sellerDetailModalTemplate() {
  return `
    <div id="seller-detail-modal" class="seller-detail-modal">
      <div class="seller-detail-dialog">
        <div class="seller-detail-header">
          <h2>Chi tiết sản phẩm (Live API)</h2>
          <button type="button" id="close-seller-modal-btn" class="seller-detail-close" aria-label="Đóng">&times;</button>
        </div>

        <div class="seller-detail-body">
          <div class="seller-modal-loading-box" id="modal-loading-box">
            <p id="modal-loading-text">Đang truy vấn dữ liệu xe từ máy chủ...</p>
          </div>

          <div class="seller-modal-main-content" id="modal-main-content">
            <h3 id="modal-prod-name">Tên xe</h3>

            <div class="seller-modal-company-box">
              <div class="seller-modal-company-copy">
                <p><strong id="modal-company-name">Đang nạp công ty...</strong></p>
                <p><strong>Người đại diện:</strong> <span id="modal-company-rep">Chưa cập nhật</span></p>
                <p><strong>Mã số thuế:</strong> <span id="modal-company-tax">Chưa có</span></p>
                <p><strong>Địa chỉ:</strong> <span id="modal-company-address">Chưa cập nhật</span></p>
              </div>

              <div id="modal-company-logo-box" class="seller-modal-company-logo-box">
                <div id="modal-company-logo-container"></div>
              </div>
            </div>

            <p><strong>Giá bán:</strong> <span id="modal-prod-price" class="seller-modal-price"></span></p>
            <p><strong>Hãng xe:</strong> <span id="modal-prod-brand"></span></p>
            <p><strong>Màu sắc:</strong> <span id="modal-prod-color"></span></p>
            <p><strong>Năm sản xuất:</strong> <span id="modal-prod-years"></span></p>
            <p><strong>Số lượng trong kho:</strong> <span id="modal-prod-quantity"></span></p>
            <p><strong>Trạng thái hiển thị:</strong> <span id="modal-prod-status" class="seller-modal-status"></span></p>
            <p><strong>Mô tả chi tiết:</strong></p>
            <div id="modal-prod-desc" class="seller-modal-description"></div>
          </div>
        </div>

        <div id="modal-gallery-section" class="seller-modal-gallery-section">
          <h3>Bộ sưu tập hình ảnh sản phẩm</h3>
          <div id="seller-images-grid" class="seller-images-grid"></div>
        </div>

        <div class="seller-detail-footer">
          <button type="button" id="seller-modal-close-btn">Đóng lại</button>
        </div>
      </div>
    </div>
  `;
}

export function sellerProductCardTemplate(product, companyName) {
  const image = getProductImage(product);
  const status = String(product.status || '');

  return `
    <div class="product-card seller-list-card">
      <img src="${escapeHtml(image)}" alt="${escapeHtml(product.name || 'Product')}" class="clickable-detail seller-list-image" data-id="${escapeHtml(product.id)}">
      <div class="product-card-body seller-list-body">
        <div class="product-card-title clickable-detail seller-list-title" data-id="${escapeHtml(product.id)}">${escapeHtml(product.name || 'Chưa có tên')}</div>
        <div class="product-card-company seller-list-company">🏬 ${escapeHtml(companyName)}</div>
        <div class="product-card-desc seller-list-desc">${escapeHtml(product.description ?? '')}</div>
        <div class="product-card-price seller-list-price">${Number(product.price_from || 0).toLocaleString()} VNĐ</div>

        <div class="seller-list-actions-row">
          <span class="product-status status-${escapeHtml(status)} seller-list-status">${escapeHtml(status)}</span>
          <div class="seller-list-actions">
            <button type="button" class="view-prod-detail-btn seller-action-btn seller-info-btn" data-id="${escapeHtml(product.id)}">Chi tiết</button>
            ${status === 'active' ? `<button type="button" class="toggle-status-btn seller-action-btn seller-warning-btn" data-id="${escapeHtml(product.id)}" data-status="inactive">Ẩn tin</button>` : ''}
            ${status === 'inactive' ? `<button type="button" class="toggle-status-btn seller-action-btn seller-success-btn" data-id="${escapeHtml(product.id)}" data-status="active">Hiện lại</button>` : ''}
            ${status !== 'draft'
              ? `<button type="button" class="edit-quantity-btn seller-action-btn seller-warning-btn" data-id="${escapeHtml(product.id)}" data-quantity="${escapeHtml(product.quantity || 0)}">Sửa SL</button>`
              : ''
            }
            ${status === 'draft'
              ? `<button type="button" class="edit-draft-btn seller-action-btn seller-warning-btn" data-id="${escapeHtml(product.id)}">Sửa bản nháp</button>`
              : `<button type="button" class="delete-product-btn seller-action-btn seller-danger-btn" data-id="${escapeHtml(product.id)}">Xóa</button>`
            }
          </div>
        </div>
      </div>
    </div>
  `;
}

export function sellerCompanyLogoTemplate(url, label) {
  return `
    <img src="${escapeHtml(url)}" class="seller-company-logo" alt="Company Logo">
    <span class="seller-company-logo-label">${escapeHtml(label)}</span>
  `;
}

export function sellerProductImagesTemplate(images) {
  return images.map((img) => {
    const imageUrl = typeof img === 'object' ? img.image_url || img.url : img;

    return `
      <div class="seller-modal-image-item">
        <img src="${escapeHtml(imageUrl || EMPTY_IMAGE)}" alt="Product image">
      </div>
    `;
  }).join('');
}
