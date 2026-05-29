import {sellerCompanyLogoTemplate,sellerDetailModalTemplate,sellerProductCardTemplate,sellerProductImagesTemplate,} 
from './templates.js';
import { StatisticsAPI } from '../../api/statistics.js';
import { OrderAPI } from '../../api/order.js';
import { SellerAPI } from '../../api/seller.js';
import { createRealtimeLoop, emitRealtimeEvent } from '../../utils/realtime.js';
import { escapeHtml } from '../../utils/format.js';
import { loadSellerRevenueStats } from './revenue-stats.js';
import { createSellerSalesHistory } from './sales-history.js';
import { ensureSellerDetailModal, openSellerProductDetailModal } from './detail-modal.js';

document.addEventListener('DOMContentLoaded', async () => {
  if (document.body?.dataset?.page !== 'seller') {
    return;
  }

  const openBtn = document.getElementById('open-product-form');
  const formWrapper = document.getElementById('product-form-wrapper');
  const form = document.getElementById('product-create-form');
  const productList = document.getElementById('seller-product-list');
  const msg = document.getElementById('product-message');
  const draftBtn = document.getElementById('btn-save-draft');
  const salesFilterForm = document.getElementById('seller-sales-filter');
  const salesList = document.getElementById('seller-sales-list');
  const salesMessage = document.getElementById('seller-sales-message');
  const salesStatusInput = document.getElementById('sellerSalesStatus');
  const salesDateFromInput = document.getElementById('sellerSalesDateFrom');
  const salesDateToInput = document.getElementById('sellerSalesDateTo');
  const revenueNode = document.getElementById('sellerRevenueAmount');
  const orderNode = document.getElementById('sellerCompletedOrders');

  if (!productList && !form && !openBtn && !salesList && !revenueNode && !orderNode) {
    return;
  }

  await loadSellerRevenueStats({
    StatisticsAPI,
    revenueNode,
    orderNode,
  });

  const salesHistory = createSellerSalesHistory({
    OrderAPI,
    escapeHtml,
    salesList,
    salesMessage,
    salesStatusInput,
    salesDateFromInput,
    salesDateToInput,
  });

  salesHistory.init(salesFilterForm);

  let productLoading = false;
  let productPollTimer = null;
  let sellerPageNavigating = false;
  let lastRenderedProducts = [];
  let lastProductsSignature = '';
  let profileUserId = '';
  let productCacheKey = 'b2b:seller:products:current';

  function refreshProductCacheKey(userId = '') {
    const normalizedId = String(userId || '').trim();

    profileUserId = normalizedId;
    productCacheKey = `b2b:seller:products:${normalizedId || 'current'}`;
  }

  function productSignature(products) {
    try {
      return JSON.stringify(products || []);
    } catch (error) {
      return String(Date.now());
    }
  }

  function cacheProducts(products) {
    try {
      window.sessionStorage.setItem(productCacheKey, JSON.stringify(products || []));
    } catch (error) {
    }
  }

  function renderProducts(products, options = {}) {
    if (!productList) {
      return;
    }

    const rows = Array.isArray(products) ? products : [];
    const signature = productSignature(rows);

    lastRenderedProducts = rows;
    cacheProducts(rows);

    if (!options.force && signature === lastProductsSignature) {
      return;
    }

    lastProductsSignature = signature;

    if (rows.length === 0) {
      productList.innerHTML = '<div class="empty-product">Bạn chưa đăng sản phẩm nào trên hệ thống.</div>';
      return;
    }

    productList.innerHTML = rows.map((product) => {
      const compInfo = product.company_info || currentCompany;
      const compName = compInfo ? (compInfo.company_name || compInfo.name) : 'Doanh nghiệp đối tác';

      return sellerProductCardTemplate(product, compName);
    }).join('');
  }

  function hydrateProductsFromCache() {
    if (!productList) {
      return;
    }

    try {
      const cachedProducts = JSON.parse(window.sessionStorage.getItem(productCacheKey) || '[]');

      if (Array.isArray(cachedProducts) && cachedProducts.length > 0) {
        renderProducts(cachedProducts, { force: true });
      }
    } catch (error) {
    }
  }

  function handleProductLoadError(error, options = {}) {
    console.error('FETCH PRODUCTS EXCEPTION:', error);

    const hasStableProducts = lastRenderedProducts.length > 0 || productList?.querySelector('.seller-list-card');

    if (hasStableProducts) {
      if (!options.silent && msg) {
        msg.innerHTML = '<span class="seller-warning-state">Kết nối tạm thời gián đoạn, đang giữ danh sách sản phẩm hiện tại.</span>';
      }

      return;
    }

    if (productList) {
      productList.innerHTML = '<div class="text-danger">Lỗi kết nối hệ thống, không thể tải sản phẩm.</div>';
    }
  }

  function stopSellerBackgroundRequests() {
    sellerPageNavigating = true;

    if (productPollTimer) {
      productPollTimer.stop?.();
      productPollTimer = null;
    }
  }

  document.querySelectorAll('.seller-chat-link, a[href*="/chat"]').forEach((link) => {
    link.addEventListener('click', () => {
      stopSellerBackgroundRequests();
      link.classList.add('is-loading');
    });
  });

  window.addEventListener('pagehide', stopSellerBackgroundRequests);

  let currentCompany = null;


  ensureSellerDetailModal({ sellerDetailModalTemplate });

  if (openBtn && formWrapper) {
    openBtn.onclick = function() {
      const hiddenIdEl = document.getElementById('product-id-hidden');
      if (hiddenIdEl) {
        form.reset();
        hiddenIdEl.remove();
        if (msg) msg.innerHTML = '';
      }
      formWrapper.classList.toggle('d-none');
      if (!formWrapper.classList.contains('d-none')) {
        formWrapper.scrollIntoView({ behavior: 'smooth' });
      }
    };
  }


  async function verifySellerProfile() {
    try {
      const profileData = await SellerAPI.profile();
      currentCompany = profileData?.company_info || profileData?.company || null;

      const resolvedUserId = profileData?.wp_user?.id
        || profileData?.wp_user?.ID
        || profileData?.user?.wp_user_id
        || profileData?.user?.id
        || '';

      refreshProductCacheKey(resolvedUserId);
      return true;
    } catch (e) {
      console.error('Lỗi đồng bộ thông tin profile người bán:', e);
    }

    return false;
  }


  async function loadProducts(options = {}) {
    if (!productList || sellerPageNavigating || productLoading) return;

    productLoading = true;

    try {
      const fetchResult = await SellerAPI.myProducts(
        profileUserId ? { auth_user_id: profileUserId } : {}
      );

      if (sellerPageNavigating) {
        return;
      }

      const rows = Array.isArray(fetchResult) ? fetchResult : [];

      if (rows.length === 0) {
        productList.innerHTML = '<div class="empty-product">Bạn chưa đăng sản phẩm nào trên hệ thống.</div>';
        return;
      }

      renderProducts(rows, { force: options.forceRender });
    } catch (err) {
      handleProductLoadError(err, options);
    } finally {
      productLoading = false;
    }
  }


  await verifySellerProfile();
  hydrateProductsFromCache();
  if (productList) loadProducts({ silent: lastRenderedProducts.length > 0 });

  if (productList) {
    productPollTimer = createRealtimeLoop({
      interval: 15000,
      maxInterval: 45000,
      eventName: 'b2b:products:changed',
      immediate: false,
      canRun: () => {
        const modal = document.getElementById('seller-detail-modal');
        const formIsOpen = formWrapper && !formWrapper.classList.contains('d-none');
        const modalIsOpen = modal && modal.style.display === 'block';

        return !formIsOpen && !modalIsOpen && !productLoading;
      },
      run: () => loadProducts({ silent: true }),
    });
    productPollTimer.start();
  }


  if (productList) {
    productList.addEventListener('click', async function (e) {
      const detailTarget = e.target.closest('.view-prod-detail-btn, .clickable-detail');
      
  
      if (detailTarget) {
        e.preventDefault();

        await openSellerProductDetailModal({
          productId: detailTarget.dataset.id,
          SellerAPI,
          currentCompany,
          sellerCompanyLogoTemplate,
          sellerProductImagesTemplate,
        });

        return;
      }

   
      const toggleTarget = e.target.closest('.toggle-status-btn');
      if (toggleTarget) {
        e.preventDefault();
        const productId = toggleTarget.dataset.id;
        const targetStatus = toggleTarget.dataset.status;

        if (!confirm(`Bạn có chắc chắn muốn chuyển trạng thái sản phẩm sang [${targetStatus}] không?`)) return;

        try {
          await SellerAPI.updateProductStatus(productId, targetStatus.toLowerCase());
          emitRealtimeEvent('b2b:products:changed', { product_id: productId });
          alert('Cập nhật trạng thái thành công!');
          loadProducts({ forceRender: true });
        } catch (err) { alert('Không thể kết nối máy chủ để thực hiện thay đổi trạng thái.'); }
        return;
      }

     
      const editQuantityTarget = e.target.closest('.edit-quantity-btn');
      if (editQuantityTarget) {
        e.preventDefault();
        const productId = editQuantityTarget.dataset.id;
        const currentQuantity = Number(editQuantityTarget.dataset.quantity || 0);
        const nextValue = window.prompt('Nhập số lượng xe mới:', String(currentQuantity));

        if (nextValue === null) return;

        const normalizedQuantity = Number(nextValue);
        if (!Number.isFinite(normalizedQuantity) || normalizedQuantity < 0) {
          alert('Số lượng không hợp lệ. Vui lòng nhập số từ 0 trở lên.');
          return;
        }

        try {
          await SellerAPI.updateProductQuantity(productId, Math.floor(normalizedQuantity));
          emitRealtimeEvent('b2b:products:changed', { product_id: productId });
          alert('Cập nhật số lượng thành công!');
          loadProducts({ forceRender: true });
        } catch (err) {
          alert('Không thể cập nhật số lượng xe lúc này.');
        }
        return;
      }

      
      const deleteTarget = e.target.closest('.delete-product-btn');
      if (deleteTarget) {
        e.preventDefault();
        const productId = deleteTarget.dataset.id;
        if (!confirm('Hành động này không thể phục hồi! Bạn có chắc chắn muốn xóa sản phẩm này khỏi danh sách?')) return;

        try {
          await SellerAPI.deleteProduct(productId);
          emitRealtimeEvent('b2b:products:changed', { product_id: productId });
          alert('Xóa sản phẩm thành công!');
          loadProducts({ forceRender: true });
        } catch (err) { alert('Hệ thống ngắt kết nối mạng, xóa sản phẩm không thành công.'); }
        return;
      }

      const editTarget = e.target.closest('.edit-draft-btn');
      if (editTarget) {
        e.preventDefault();
        const productId = editTarget.dataset.id;
        if (!form || !formWrapper) return;

        try {
          const detailData = await SellerAPI.productDetail(productId);
          const product = detailData?.product || {};


          let hiddenIdEl = document.getElementById('product-id-hidden');
          if (!hiddenIdEl) {
              form.insertAdjacentHTML('beforeend', `<input type="hidden" name="id" id="product-id-hidden" value="${product.id}">`);
          } else {
              hiddenIdEl.value = product.id;
          }

          if (form.elements['name']) form.elements['name'].value = product.name || '';
          if (form.elements['description']) form.elements['description'].value = product.description || '';
          if (form.elements['price_from']) form.elements['price_from'].value = product.price_from || '';
          if (form.elements['years']) form.elements['years'].value = product.years || '';
          if (form.elements['quantity']) form.elements['quantity'].value = product.quantity || '';
          
        
          if (form.elements['brand']) form.elements['brand'].value = product.brand || '';
          if (form.elements['color']) form.elements['color'].value = product.color || '';


          formWrapper.classList.remove('d-none');
          formWrapper.scrollIntoView({ behavior: 'smooth' });

          if (msg) msg.innerHTML = `<span class="seller-draft-edit-message">📝 Đang ở chế độ sửa Bản nháp (ID: ${product.id})</span>`;

        } catch (err) {
          console.error(err);
          alert('Không thể kết nối máy chủ để nạp thông tin sửa bản nháp.');
        }
      }
    });
  }


  if (form) {
    let submitMode = 'active'; 

    if (draftBtn) {
      draftBtn.onclick = function() {
        submitMode = 'draft'; 
        form.requestSubmit();
      };
    }

    form.onsubmit = async function (e) {
      e.preventDefault();
      if (msg) msg.innerHTML = 'Dang xu ly du lieu...';

      const formData = new FormData(this);
      formData.append('status', submitMode);

      const profileIdInput = document.getElementById('profId');
      const profileId = String(profileIdInput?.value || profileUserId || '').trim();

      if (profileId) {
        formData.append('auth_user_id', profileId);
        formData.append('seller_id', profileId);
      }

      const hiddenIdEl = document.getElementById('product-id-hidden');
      const editProductId = hiddenIdEl?.value ? String(hiddenIdEl.value) : '';
      const isEditMode = Number(editProductId || 0) > 0;

      try {
        await SellerAPI.saveProduct(formData, editProductId || null);

        const actionText = isEditMode
          ? (submitMode === 'draft' ? 'Cap nhat ban nhap' : 'Dang san pham tu ban nhap')
          : (submitMode === 'draft' ? 'Luu ban nhap thanh cong' : 'Dang san pham moi thanh cong');

        alert(`${actionText}!`);
        form.reset();

        if (hiddenIdEl) hiddenIdEl.remove();
        if (formWrapper) formWrapper.classList.add('d-none');
        if (msg) msg.innerHTML = '';

        emitRealtimeEvent('b2b:products:changed');
        await loadProducts({ forceRender: true });
      } catch (error) {
        if (msg) msg.innerHTML = `<span class="text-danger">${error.message || 'Loi ket noi API'}</span>`;
      } finally {
        submitMode = 'active';
      }
    };
  }
});