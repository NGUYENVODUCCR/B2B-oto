import { EMPTY_IMAGE, escapeHtml, formatCurrency, getProductImage } from '../../utils/format.js';

export function dashboardProductCardTemplate(product) {
  const reviewCount = Number(product.review_count || 0);
  const avgRating = Number(product.avg_rating || 0);
  const image = getProductImage(product);
  const status = product.status || 'active';
  const companyName = product.company_name
    || product.company_info?.company_name
    || product.company_info?.name
    || 'Công ty đối tác';
  const specs = [
    product.brand ? { label: 'Hãng', value: product.brand } : null,
    product.years ? { label: 'Năm', value: product.years } : null,
    product.color ? { label: 'Màu', value: product.color } : null,
  ].filter(Boolean);

  return `
    <article class="product-card dashboard-clickable-detail" data-id="${escapeHtml(product.id)}">
      <div class="product-image-wrap">
        <img class="product-image" src="${escapeHtml(image)}" alt="${escapeHtml(product.name || 'Ảnh xe')}">
        <span class="product-status status-${escapeHtml(status)} dashboard-product-status">
          ${escapeHtml(status)}
        </span>
      </div>
      <div class="product-body">
        <div class="product-card-company">${escapeHtml(companyName)}</div>
        <h3 class="product-name dashboard-product-name">${escapeHtml(product.name || 'Chưa có tên')}</h3>
        <div class="product-specs">
          ${specs.map((item) => `
            <span><small>${escapeHtml(item.label)}</small>${escapeHtml(item.value)}</span>
          `).join('')}
        </div>
        <div class="product-desc">${escapeHtml(product.description || '')}</div>
        <div class="product-card-bottom">
          <div>
            <small>Giá chào bán</small>
            <div class="product-price">${formatCurrency(product.price_from)}</div>
          </div>
          <div class="product-review-count">
            <strong>${avgRating > 0 ? avgRating.toFixed(1) : '0.0'}</strong>
            <span>${reviewCount} đánh giá</span>
          </div>
        </div>
        <div class="dashboard-card-actions">
          <button type="button" class="dashboard-card-action dashboard-card-detail" data-dashboard-detail data-id="${escapeHtml(product.id)}">
            Chi tiết
          </button>
          <button type="button" class="dashboard-card-action dashboard-card-chat" data-flow-contact data-id="${escapeHtml(product.id)}">
            Nhắn người bán
          </button>
        </div>
      </div>
    </article>
  `;
}

export function dashboardDetailModalTemplate() {
  return `
    <div id="dashboard-detail-modal" class="dashboard-detail-modal">
      <div class="dashboard-detail-dialog">
        <div class="dashboard-detail-header">
          <h2>Chi tiết xe giao dịch</h2>
          <button type="button" id="close-dashboard-modal-btn" class="dashboard-detail-close" aria-label="Đóng">&times;</button>
        </div>

        <div class="dashboard-detail-body">
          <div class="dashboard-modal-loading-box" id="dashboard-modal-loading-box">
            <p id="dashboard-modal-loading-text">Đang tải dữ liệu xe...</p>
          </div>

          <div class="dashboard-modal-main-content" id="dashboard-modal-main-content">
            <h3 id="dashboard-modal-prod-name">Tên xe</h3>

            <div id="dashboard-company-info-box" class="dashboard-company-info-box">
              <div class="dashboard-company-copy">
                <p><strong id="dashboard-company-name">Công ty đối tác</strong></p>
                <p><strong>Người đại diện:</strong> <span id="dashboard-company-rep">Chưa cập nhật</span></p>
                <p><strong>Mã số thuế:</strong> <span id="dashboard-company-tax">Chưa cập nhật</span></p>
                <p><strong>Địa chỉ:</strong> <span id="dashboard-company-address">Chưa cập nhật</span></p>
              </div>

              <div id="dashboard-company-logo-box" class="dashboard-company-logo-box">
                <div id="dashboard-company-logo-container"></div>
              </div>
            </div>

            <p><strong>Giá bán dự kiến:</strong> <span id="dashboard-modal-prod-price" class="dashboard-modal-price"></span></p>
            <p><strong>Hãng xe:</strong> <span id="modal-prod-brand">-</span></p>
            <p><strong>Màu sắc:</strong> <span id="modal-prod-color">-</span></p>
            <p><strong>Năm sản xuất:</strong> <span id="dashboard-modal-prod-years"></span></p>
            <p><strong>Số lượng còn lại:</strong> <span id="dashboard-modal-prod-quantity"></span></p>
            <p><strong>Mô tả chi tiết:</strong></p>
            <div id="dashboard-modal-prod-desc" class="dashboard-modal-description"></div>
          </div>
        </div>

        <div id="dashboard-modal-gallery-section" class="dashboard-modal-gallery-section">
          <div id="dashboard-images-grid" class="dashboard-images-grid"></div>
        </div>

        <div class="dashboard-detail-footer">
          <button type="button" id="dashboard-modal-close-btn">Đóng lại</button>
        </div>
      </div>
    </div>
  `;
}

export function companyLogoTemplate(url) {
  return `<img class="dashboard-company-logo" src="${escapeHtml(url)}" alt="Company logo">`;
}

export function productImagesTemplate(images) {
  return images.map((img) => {
    const imageUrl = typeof img === 'object' ? img.image_url || img.url : img;

    return `
      <div class="dashboard-image-item">
        <img src="${escapeHtml(imageUrl || EMPTY_IMAGE)}" alt="Product image">
      </div>
    `;
  }).join('');
}
