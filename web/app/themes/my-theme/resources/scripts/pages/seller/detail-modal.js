function closeSellerDetailModal() {
  const modal = document.getElementById('seller-detail-modal');
  if (modal) {
    modal.style.display = 'none';
  }
}

export function ensureSellerDetailModal({ sellerDetailModalTemplate }) {
  if (document.getElementById('seller-detail-modal')) {
    return;
  }

  document.body.insertAdjacentHTML('beforeend', sellerDetailModalTemplate());

  document.getElementById('close-seller-modal-btn').onclick = closeSellerDetailModal;
  document.getElementById('seller-modal-close-btn').onclick = closeSellerDetailModal;

  window.addEventListener('click', (event) => {
    const modal = document.getElementById('seller-detail-modal');

    if (event.target === modal) {
      closeSellerDetailModal();
    }
  });
}

function showDetailLoadingState() {
  const targetModal = document.getElementById('seller-detail-modal');
  const loadingBox = document.getElementById('modal-loading-box');
  const mainContent = document.getElementById('modal-main-content');
  const gallerySection = document.getElementById('modal-gallery-section');
  const loadingText = document.getElementById('modal-loading-text');

  if (!targetModal) return null;

  loadingBox.style.display = 'block';
  loadingText.innerText = 'Đang truy vấn dữ liệu xe từ máy chủ...';
  loadingText.style.color = '#007bff';
  mainContent.style.display = 'none';
  gallerySection.style.display = 'none';
  targetModal.style.display = 'block';

  return {
    loadingBox,
    mainContent,
    gallerySection,
    loadingText,
  };
}

function renderCompanyLogo({ companyObj, sellerCompanyLogoTemplate }) {
  const logoContainer = document.getElementById('modal-company-logo-container');
  const logoBox = document.getElementById('modal-company-logo-box');

  if (!logoContainer || !logoBox) {
    return;
  }

  logoContainer.innerHTML = '';
  const companyDocs = Array.isArray(companyObj.documents) ? companyObj.documents : [];

  const logoFile = companyDocs.find((doc) => {
    if (doc && typeof doc === 'object') {
      const labelText = String(doc.label || '').toLowerCase();
      const urlText = String(doc.url || doc.image_url || '').toLowerCase();
      return labelText.includes('logo') || urlText.includes('logo');
    }

    if (typeof doc === 'string') {
      return doc.toLowerCase().includes('logo');
    }

    return false;
  });

  if (logoFile) {
    const logoUrl = typeof logoFile === 'object' ? (logoFile.url || logoFile.image_url) : logoFile;

    if (logoUrl) {
      logoBox.style.display = 'block';
      logoContainer.innerHTML = sellerCompanyLogoTemplate(logoUrl, 'Logo');
    } else {
      logoBox.style.display = 'none';
    }

    return;
  }

  if (companyDocs.length > 0) {
    const lastDoc = companyDocs[companyDocs.length - 1];
    const backupUrl = typeof lastDoc === 'object' ? (lastDoc.url || lastDoc.image_url) : lastDoc;
    logoBox.style.display = 'block';
    logoContainer.innerHTML = sellerCompanyLogoTemplate(backupUrl, 'Logo (Auto)');
    return;
  }

  logoBox.style.display = 'none';
}

function renderProductGallery({ productImages, sellerProductImagesTemplate }) {
  const imagesGrid = document.getElementById('seller-images-grid');

  if (!imagesGrid) {
    return;
  }

  imagesGrid.innerHTML = '';
  const processedImages = Array.isArray(productImages) ? productImages : [];

  if (processedImages.length > 0) {
    imagesGrid.innerHTML = sellerProductImagesTemplate(processedImages);
  } else {
    imagesGrid.innerHTML = '<p class="seller-modal-empty-gallery">Sản phẩm này chưa đăng tải bộ sưu tập hình ảnh.</p>';
  }
}

function renderProductStatus(product) {
  const statusEl = document.getElementById('modal-prod-status');

  if (!statusEl) {
    return;
  }

  statusEl.innerText = (product.status || 'PENDING').toUpperCase();

  let badgeBg = '#6c757d';
  if (product.status === 'active') badgeBg = '#28a745';
  if (product.status === 'blocked') badgeBg = '#dc3545';
  if (product.status === 'pending') badgeBg = '#ffc107';

  statusEl.style.backgroundColor = badgeBg;
  statusEl.style.color = badgeBg === '#ffc107' ? '#000' : '#fff';
}

export async function openSellerProductDetailModal({
  productId,
  SellerAPI,
  currentCompany,
  sellerCompanyLogoTemplate,
  sellerProductImagesTemplate,
}) {
  const modalNodes = showDetailLoadingState();

  if (!modalNodes) return;

  const {
    loadingBox,
    mainContent,
    gallerySection,
    loadingText,
  } = modalNodes;

  try {
    const detailData = await SellerAPI.productDetail(productId);
    const product = detailData?.product || {};
    const productImages = detailData?.images || [];
    const companyObj = detailData?.company || detailData?.company_info || currentCompany || {};

    document.getElementById('modal-prod-name').innerText = product.name || 'Không có tên';
    document.getElementById('modal-prod-price').innerText = `${Number(product.price_from || 0).toLocaleString()} VNĐ`;
    document.getElementById('modal-prod-brand').innerText = product.brand || 'Chưa cập nhật';
    document.getElementById('modal-prod-color').innerText = product.color || 'Chưa cập nhật';
    document.getElementById('modal-prod-years').innerText = product.years || 'Chưa rõ';
    document.getElementById('modal-prod-quantity').innerText = `${product.quantity || 0} xe`;
    document.getElementById('modal-prod-desc').innerText = product.description || 'Sản phẩm chưa cập nhật mô tả chi tiết.';

    document.getElementById('modal-company-name').innerText = companyObj.company_name || companyObj.name || 'Doanh nghiệp đối tác';
    document.getElementById('modal-company-rep').innerText = companyObj.representative_name || companyObj.contact_name || 'Chưa cập nhật';
    document.getElementById('modal-company-tax').innerText = companyObj.tax_code || companyObj.tax_no || 'Chưa cập nhật';
    document.getElementById('modal-company-address').innerText = companyObj.address || 'Chưa cập nhật';

    renderCompanyLogo({ companyObj, sellerCompanyLogoTemplate });
    renderProductStatus(product);
    renderProductGallery({ productImages, sellerProductImagesTemplate });

    loadingBox.style.display = 'none';
    mainContent.style.display = 'block';
    gallerySection.style.display = 'block';
  } catch (err) {
    console.error(err);
    loadingText.innerText = 'Lỗi cấu trúc hoặc máy chủ ngắt kết nối!';
    loadingText.style.color = '#dc3545';
  }
}
