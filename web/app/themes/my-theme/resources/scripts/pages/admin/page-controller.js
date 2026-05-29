import { createAdminApi } from '../../api/admin.js';
import { extractUserIdFromToken } from '../../utils/jwt.js';


import {
  getProductFilterStatus,
  productCardTemplate,
  sellerRequestCardTemplate,
  userCardTemplate,
  internalChatLayoutTemplate,
  supportUserItemTemplate,
  singleChatMessageTemplate,
  adminRevenueMainTemplate,       
  adminRevenueOrderCardTemplate
} from './templates.js';


import {
  closeModal,
  collectProductEditPayload,
  ensureAdminModals,
  openProductDetailModal,
  openProductEditModal,
  openSellerModal,
  openUserCreateModal,
  openUserEditModal,
  collectUserPayload
} from './modals.js';

console.log('ADMIN REQUEST PAGE - INTEGRATED MODULAR ENTRY');

document.addEventListener('DOMContentLoaded', () => {
  const btnShowList = document.getElementById('btn-show-list');
  const sellerFilterTabsBar = document.getElementById('seller-filter-tabs-bar');
  const productFilterTabsBar = document.getElementById('product-filter-tabs-bar');
  const userFilterTabsBar = document.getElementById('user-filter-tabs-bar');
  const chatTabsBar = document.getElementById('chat-tabs-bar');
  const requestList = document.getElementById('request-list');
  const pageTitle = document.getElementById('admin-page-title');
  const btnCreateUserTrigger = document.getElementById('btn-create-user-trigger');

  if (!btnShowList || !requestList || !sellerFilterTabsBar || !productFilterTabsBar || !userFilterTabsBar) {
    return;
  }

  const api = createAdminApi();

  let allRequests = [];
  let allProducts = [];
  let allUsers = [];
  let allCompanies = [];
  let supportTeam = []; 
  let chatInterval = null;
  

  let lastChatHtmlCache = ""; 

  let currentModule = 'seller';
  let currentFilter = 'all';
  let currentUserPage = 1;

  ensureAdminModals();
  bindModuleTabs();
  bindFilterTabs();
  bindListActions();
  bindProductEditForm();
  bindUserFormSubmit();

  btnCreateUserTrigger?.addEventListener('click', async () => {
    await ensureCompanyOptions();
    openUserCreateModal();
  });
  btnShowList.addEventListener('click', () => {
    currentUserPage = 1;
    loadCurrentModule();
  });

  applyModuleState();

  async function ensureCompanyOptions() {
    const select = document.getElementById('edit-user-company-id');
    if (!select) {
      return;
    }

    if (!Array.isArray(allCompanies) || allCompanies.length === 0) {
      try {
        const rows = await api.fetchCompanies();
        allCompanies = Array.isArray(rows) ? rows : [];
      } catch (error) {
        allCompanies = [];
      }
    }

    const options = [
      '<option value="">Chọn công ty có sẵn</option>',
      ...allCompanies.map((company) => {
        const id = Number(company.id || 0);
        const name = String(company.company_name || `Company #${id}`);
        const taxCode = String(company.tax_code || '').trim();
        const suffix = taxCode ? ` - MST: ${taxCode}` : '';
        return `<option value="${id}">${name}${suffix}</option>`;
      }),
    ];

    select.innerHTML = options.join('');
  }

  function bindModuleTabs() {
    document.querySelectorAll('.module-tab-btn').forEach((btn) => {
      btn.addEventListener('click', () => {
        currentModule = btn.dataset.module || 'seller';
        currentFilter = 'all';
        currentUserPage = 1;
        requestList.innerHTML = '';
        lastChatHtmlCache = ""; 

        if (chatInterval) {
          clearInterval(chatInterval);
          chatInterval = null;
        }

        applyModuleState();
      });
    });
  }

  function bindFilterTabs() {
    document.querySelectorAll('.filter-tab-btn').forEach((tab) => {
      tab.addEventListener('click', () => {
        currentFilter = tab.dataset.status || 'all';
        setActiveFilterTab(tab);

        if (currentModule === 'user') {
          loadCurrentModule();
        } else {
          renderFilteredItems();
        }
      });
    });
  }

  function applyModuleState() {
    document.querySelectorAll('.module-tab-btn').forEach((tab) => {
      tab.classList.toggle('active', tab.dataset.module === currentModule);
    });

    sellerFilterTabsBar.classList.toggle('is-visible', currentModule === 'seller');
    productFilterTabsBar.classList.toggle('is-visible', currentModule === 'product');
    userFilterTabsBar.classList.toggle('is-visible', currentModule === 'user');
    chatTabsBar.classList.toggle('is-visible', currentModule === 'chat');

    if (btnCreateUserTrigger) {
      btnCreateUserTrigger.style.display = currentModule === 'user' ? 'inline-block' : 'none';
    }

    if (currentModule === 'seller') {
      pageTitle.innerText = 'Quản lý yêu cầu người bán (Seller)';
      btnShowList.innerText = 'Xem danh sách đơn đăng ký bán hàng';
      btnShowList.style.display = 'inline-block';
    } else if (currentModule === 'product') {
      pageTitle.innerText = 'Quản lý và Duyệt bài đăng (Sản phẩm)';
      btnShowList.innerText = 'Xem danh sách bài đăng';
      btnShowList.style.display = 'inline-block';
    } else if (currentModule === 'user') {
      pageTitle.innerText = 'Quản lý tài khoản và Phân quyền thành viên (Users)';
      btnShowList.innerText = 'Tải danh sách dữ liệu tài khoản';
      btnShowList.style.display = 'inline-block';
    } else if (currentModule === 'chat') {
      pageTitle.innerText = 'Hệ thống kết nối và Phản hồi Kênh liên hệ hỗ trợ nội bộ';
      btnShowList.style.display = 'none';
      loadCurrentModule();
    }

    const activeFilterBar = document.querySelector('.filter-tabs-bar.is-visible');
    if (activeFilterBar) {
      const defaultFilterTab = activeFilterBar.querySelector(`[data-status="${currentFilter}"]`) || activeFilterBar.querySelector('[data-status="all"]');
      if (defaultFilterTab) {
        setActiveFilterTab(defaultFilterTab);
      }
    }
  }

  function setActiveFilterTab(activeTab) {
    document
      .querySelectorAll(`.filter-tab-btn[data-module="${activeTab.dataset.module}"]`)
      .forEach((tab) => tab.classList.toggle('active', tab === activeTab));
  }

  async function loadCurrentModule() {
    btnShowList.disabled = true;

    try {
      if (currentModule === 'seller') {
        requestList.innerHTML = '<p class="admin-state-message">Đang tải danh sách đơn đăng ký...</p>';
        allRequests = await api.fetchSellerRequests();
        renderFilteredItems();
      } else if (currentModule === 'product') {
        requestList.innerHTML = '<p class="admin-state-message">Đang tải danh sách bài đăng...</p>';
        allProducts = await api.fetchProducts();
        renderFilteredItems();
      } else if (currentModule === 'user') {
        requestList.innerHTML = '<p class="admin-state-message">Đang tải danh sách tài khoản...</p>';
        
        const response = await api.fetchUsers(currentUserPage, currentFilter);
        const rawUsers = response?.data?.users || response?.users || [];
        
        allUsers = rawUsers.map(user => ({
          id: user.id || user.ID,
          company_id: user.company_id || user.companyId || user.company?.id || '',
          username: user.username || user.user_login,
          email: user.email || user.user_email,
          fullname: user.fullname || user.display_name || 'Chưa cập nhật',
          phone: user.phone || '',
          roles: Array.isArray(user.roles) ? user.roles : [user.role_slug || 'customer'],
          status: user.status || user.user_status || 'active',
          is_banned: user.is_banned === true || user.status === 'banned' || user.user_status === 'banned'
        }));

        renderFilteredItems();
      } else if (currentModule === 'chat') {
        requestList.innerHTML = '<p class="admin-state-message">Mở không gian làm việc Kênh Chat...</p>';
        supportTeam = await api.fetchSupportTeamList();
        renderChatLayout();
      }
    } catch (error) {
      console.error('ADMIN LOAD ERROR:', error);
      requestList.innerHTML = `<p class="admin-state-message admin-state-message-error">Lỗi tải dữ liệu: ${error.message}.</p>`;
    } finally {
      btnShowList.disabled = false;
    }
  }

  function renderFilteredItems() {
    if (currentModule === 'seller') {
      const filtered = allRequests.filter((request) => {
        if (currentFilter === 'all') return true;
        return String(request.status || '').toLowerCase() === currentFilter.toLowerCase();
      });

      requestList.innerHTML = filtered.length
        ? filtered.map(sellerRequestCardTemplate).join('')
        : '<p class="admin-state-message">Không có đơn đăng ký nào thuộc trạng thái này.</p>';
      return;
    }

    if (currentModule === 'product') {
      const targetProductStatus = getProductFilterStatus(currentFilter);
      const filtered = allProducts.filter((product) => {
        const dbStatus = String(product.status || '').toLowerCase();
        if (dbStatus === 'draft') return false;
        if (currentFilter === 'all') return true;
        return dbStatus === targetProductStatus;
      });

      requestList.innerHTML = filtered.length
        ? filtered.map(productCardTemplate).join('')
        : '<p class="admin-state-message">Không có bài đăng nào thuộc trạng thái này.</p>';
      return;
    }

    if (currentModule === 'user') {
      let targetRole = currentFilter;
      if (currentFilter === 'administrator') targetRole = 'admin';
      if (currentFilter === 'ROLE_SUPPORT') targetRole = 'support';
      if (currentFilter === 'customer') targetRole = 'buyer';

      const filteredUsers = allUsers.filter((user) => {
        if (currentFilter === 'all') return true;
        return user.roles.some(role => String(role).toLowerCase() === targetRole.toLowerCase());
      });

      requestList.innerHTML = filteredUsers.length
        ? `<div class="users-grid-layout">${filteredUsers.map(userCardTemplate).join('')}</div>`
        : '<p class="admin-state-message">Không có tài khoản người dùng nào khớp điều kiện lọc.</p>';
    }
  }

  function renderChatLayout() {
    const listHtml = supportTeam.length
      ? supportTeam.map(supportUserItemTemplate).join('')
      : '<p class="admin-empty-documents">Không có nhân viên support nào.</p>';

    requestList.innerHTML = internalChatLayoutTemplate(listHtml);
    bindChatSidebarEvents();
    bindChatFormSubmit();
  }

  function bindChatSidebarEvents() {
    const listContainer = requestList.querySelector('.support-users-list');
    if (!listContainer) return;

    listContainer.addEventListener('click', (e) => {
      const item = e.target.closest('.support-user-item');
      if (!item) return;

      document.querySelectorAll('.support-user-item').forEach(el => el.classList.remove('selected-active'));
      item.classList.add('selected-active');

      const supportId = item.dataset.supportId;
      document.getElementById('active-support-id').value = supportId;

      const msgInput = document.getElementById('chat-raw-message');
      const btnSend = document.getElementById('btn-send-chat');
      
      if (msgInput && btnSend) {
        msgInput.disabled = false;
        btnSend.disabled = false;
        msgInput.placeholder = 'Nhập tin nhắn hỗ trợ nội bộ...';
      }

      window.currentSelectedPartnerId = supportId;
      lastChatHtmlCache = "";

      loadChatMessages(supportId, 'as', false);
      if (chatInterval) clearInterval(chatInterval);
      chatInterval = setInterval(() => loadChatMessages(supportId, 'as', true), 3000);
    });
  }

function getUserIdFromToken() {
  const token = localStorage.getItem('access_token');

  if (!token) {
    return null;
  }

  return extractUserIdFromToken(token);
}


  async function loadChatMessages(targetId, flowType = 'as', isPolling = false) {
    try {
      if (currentModule !== 'chat') {
        if (chatInterval) {
          clearInterval(chatInterval);
          chatInterval = null;
        }
        return;
      }

      const container = document.getElementById('chat-messages-container');
      if (!container) return;

      let messages = [];
      if (flowType === 'aa') {
        messages = await api.fetchAdminMessages(targetId);
      } else {
        messages = await api.fetchSupportMessages(targetId);
      }

   
      const targetUser = supportTeam.find(u => String(u.id || u.ID || u.wp_user_id || u.uid) === String(targetId));
      const activePartnerName = targetUser 
        ? (targetUser.fullname || targetUser.display_name || targetUser.username) 
        : 'Nhân viên';


      const myId = Number(window.currentWordPressUserId || getUserIdFromToken() || localStorage.getItem('userId') || 0);
      const myHeaderName = document.querySelector('.custom-dropdown-toggle, #admin-profile-name')?.innerText?.trim() || 'HaAdmin1';

      const processedMessages = messages.map((msg) => {
        let msgSenderId = msg.sender_id || msg.senderId || msg.user_id || msg.userId || msg.wp_user_id || msg.from_user_id || 0;
        
        if (msg.sender && typeof msg.sender === 'object') {
          msgSenderId = msg.sender.id || msg.sender.ID || msg.sender.uid || msgSenderId;
        } else if (msg.sender && (typeof msg.sender === 'string' || typeof msg.sender === 'number')) {
          msgSenderId = msg.sender;
        }
        
        msgSenderId = Number(msgSenderId);
        const targetIdNum = Number(targetId);

    
        let isMe = false;
        if (myId > 0) {
          isMe = (msgSenderId === myId);
        } else {
          isMe = (msgSenderId !== targetIdNum && msgSenderId !== 0);
        }


        const finalSenderName = isMe ? `${myHeaderName} (Bạn)` : activePartnerName;


        return {
          ...msg,
          is_me_flag: isMe,
          sender_id: msgSenderId,

          sender_name: finalSenderName,
          display_name: finalSenderName,
          fullname: finalSenderName,
          username: finalSenderName,
          sender: {
            ...(msg.sender && typeof msg.sender === 'object' ? msg.sender : {}),
            id: msgSenderId,
            display_name: finalSenderName,
            fullname: finalSenderName,
            sender_name: finalSenderName
          }
        };
      });

  
      const html = processedMessages.length
        ? processedMessages.map(msg => singleChatMessageTemplate(msg)).join('')
        : `<p class="chat-empty-history">Bắt đầu cuộc hội thoại nội bộ bảo mật với thành viên này.</p>`;

   
      if (lastChatHtmlCache === html) {
         return; 
      }
      
      lastChatHtmlCache = html;

      const isAtBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 120;
      container.innerHTML = html;

      if (!isPolling || isAtBottom) {
        container.scrollTop = container.scrollHeight;
      }
    } catch (err) {
      console.error('CHAT POLLING ERROR:', err);
    }
  }

  function bindChatFormSubmit() {
    document.getElementById('admin-chat-input-form')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const supportId = document.getElementById('active-support-id').value;
      const input = document.getElementById('chat-raw-message');
      const text = input?.value.trim();

      if (!text || supportId === '0') return;
      input.value = '';

      try {
        await api.sendToSupport(supportId, text);
        lastChatHtmlCache = ""; 
        await loadChatMessages(supportId, 'as', false);
      } catch (err) {
        alert('Lỗi gửi tin nhắn: ' + err.message);
      }
    });
  }

function bindListActions() {
  requestList.addEventListener('click', async (event) => {
    const target = event.target.closest('button');
    if (!target) return;

    const targetId = target.dataset.id;

   
    if (currentModule === 'seller') {
   
      if (target.classList.contains('view-seller-detail-btn')) {
        const seller = allRequests.find((request) => String(request.id) === String(targetId));
        if (!seller) {
          alert('Không tìm thấy dữ liệu hồ sơ đăng ký người bán!');
          return;
        }
        openSellerModal(seller);
        return;
      }

      
      if (target.classList.contains('approve-btn') || target.classList.contains('reject-btn')) {
        await handleSellerStatusAction(target, targetId);
        return;
      }

    
      if (target.classList.contains('admin-revenue-view-btn')) {
        const sellerId = Number(target.dataset.sellerId || 0);
        const companyName = target.dataset.companyName || 'Đối tác';
        
        if (sellerId <= 0) {
          alert('Không thể xác định mã đối tác để truy vấn tài chính!');
          return;
        }


        currentModule = 'revenue';
        pageTitle.innerText = `Báo cáo tài chính doanh thu: ${companyName}`;
        
      
        const revenueSkeleton = document.getElementById('admin-revenue-zone')?.innerHTML || '';
        requestList.innerHTML = revenueSkeleton;
        
    
        bindRevenueFilterFormSubmit();

  
        setTimeout(async () => {
          try {
            await loadAdminRevenueData(sellerId, companyName);
          } catch (err) {
            console.error("Lỗi khởi chạy tiến trình nạp dữ liệu tài chính:", err);
          }
        }, 30);

        return;
      }
    }


    if (currentModule === 'product') {

      if (target.classList.contains('view-detail-btn')) {
        await handleProductDetailAction(targetId);
        return;
      }

     
      if (target.classList.contains('product-approve-btn') || target.classList.contains('product-block-btn')) {
        await handleProductStatusAction(target, targetId);
        return;
      }
    }


    if (currentModule === 'user') {

      if (target.classList.contains('edit-user-btn')) {
        const user = allUsers.find(u => String(u.id) === String(targetId));
        if (user) {
          openUserEditModal(user);
        } else {
          alert('Không lấy được thông tin chi tiết của User này!');
        }
        return;
      }

   
      if (target.classList.contains('toggle-user-status-btn')) {
        await handleToggleUserStatus(target, targetId);
        return;
      }

      
      if (target.classList.contains('delete-user-btn')) {
        await handleDeleteUser(target, targetId);
        return;
      }
    }
  });
}

  async function handleSellerStatusAction(button, requestId) {
    const isApprove = button.classList.contains('approve-btn');
    const endpoint = isApprove ? 'approve' : 'reject';
    const actionName = isApprove ? '"Đã xác minh"' : '"Không xác minh được"';

    if (!confirm(`Bạn chắc chắn muốn chuyển trạng thái đơn này thành ${actionName}?`)) return;

    button.disabled = true;
    button.innerText = 'Đang xử lý...';

    try {
      const result = await api.updateSellerRequest(requestId, endpoint);
      alert(result.message || result.msg || 'Thao tác đơn người bán thành công!');
      await loadCurrentModule();
    } catch (error) {
      console.error(error);
      alert('Thao tác thất bại.');
      button.disabled = false;
      button.innerText = isApprove ? 'Đã xác minh' : 'Không xác minh được';
    }
  }

  async function handleProductDetailAction(productId) {
    const product = allProducts.find((item) => String(item.id) === String(productId));
    if (!product) {
      alert('Không tìm thấy dữ liệu sản phẩm!');
      return;
    }

    const currentDbStatus = String(product.status || '').toLowerCase();
    if (currentDbStatus === 'deleted' || currentDbStatus === 'inactive') {
      openProductDetailModal(product);
      try {
        const detail = await api.fetchProductDetail(productId);
        openProductDetailModal(product, detail);
      } catch (error) {
        console.warn(error);
      }
      return;
    }
    openProductEditModal(product);
  }

  async function handleProductStatusAction(button, productId) {
    const isApprove = button.classList.contains('product-approve-btn');
    const targetStatus = isApprove ? 'active' : 'blocked';
    const actionName = isApprove ? '"Duyệt hiển thị công khai"' : '"Khóa hiển thị bài đăng"';

    if (!confirm(`Bạn chắc chắn muốn thực hiện hành động ${actionName} cho bài đăng này?`)) return;

    button.disabled = true;
    button.innerText = 'Đang lưu...';

    try {
      const result = await api.updateProduct(productId, { status: targetStatus });
      alert(result.message || 'Cập nhật trạng thái bài đăng thành công!');
      await loadCurrentModule();
    } catch (error) {
      alert('Lỗi hệ thống không thể kết nối đến API!');
      button.disabled = false;
      button.innerText = isApprove ? 'Mở khóa bài đăng' : 'Khóa bài đăng';
    }
  }

async function handleToggleUserStatus(btn, userId) {

  if (Number(userId) === 1) {
    alert('Không thể thay đổi trạng thái hoạt động hoặc khóa tài khoản Root Administrator tối cao của hệ thống WordPress!');
    return;
  }

  console.log("👉 ĐÃ CHẠY VÀO HÀM KHÓA USER MỚI!");
  const targetStatus = btn.dataset.targetStatus;
  const actionText = targetStatus === 'blocked' ? 'KHÓA tài khoản' : 'MỞ KHÓA tài khoản';

  if (!confirm(`Bạn có chắc muốn thực hiện hành động "${actionText}" cho tài khoản này?`)) return;
  
  btn.disabled = true;
  const originalText = btn.innerText;
  btn.innerText = 'Đang xử lý...';

  try {
    const targetUser = allUsers.find((user) => String(user.id) === String(userId));
    const companyId = Number(targetUser?.company_id || 0);

   
    const res = await api.toggleUserStatus(userId, targetStatus, companyId);
    alert(res.data?.message || res.message || 'Thay đổi trạng thái tài khoản thành công!');
    await loadCurrentModule();
  } catch (err) {
    console.error('TOGGLE USER ERROR:', err);
    alert('Thao tác thất bại: ' + err.message);
    btn.disabled = false;
    btn.innerText = originalText;
  }
}

 async function handleDeleteUser(btn, userId) {
    if (Number(userId) === 1) {
      alert('Không thể xóa tài khoản Root Administrator tối cao của hệ thống WordPress!');
      return;
    }

    if (!confirm('CẢNH BÁO: Bạn chắc chắn muốn xóa tài khoản này khỏi hệ thống?')) return;
    btn.disabled = true;
    try {
      const res = await api.deleteUser(userId);
      alert(res.message || 'Đã chuyển tài khoản sang trạng thái xóa mềm.');
      await loadCurrentModule();
    } catch (err) {
      alert('Lỗi: ' + err.message);
      btn.disabled = false;
    }
  }

  function bindProductEditForm() {
    document.getElementById('admin-edit-product-form')?.addEventListener('submit', async (event) => {
      event.preventDefault();
      const submitBtn = document.getElementById('modal-submit-btn');
      const productId = document.getElementById('edit-prod-id')?.value;

      if (!productId || !submitBtn) return;
      submitBtn.disabled = true;
      submitBtn.innerText = 'Đang cập nhật...';

      try {
        const result = await api.updateProduct(productId, collectProductEditPayload());
        alert(result.message || 'Admin cập nhật thông tin sản phẩm thành công!');
        closeModal('admin-product-modal');
        await loadCurrentModule();
      } catch (error) {
        alert(`Lỗi: ${error.message || 'Không thể lưu.'}`);
      } finally {
        submitBtn.disabled = false;
        submitBtn.innerText = 'Lưu cập nhật';
      }
    });
  }

function bindUserFormSubmit() {
 
    document.body.addEventListener('submit', async (e) => {
      if (e.target.id !== 'admin-user-form') return;
      e.preventDefault();

      const currentForm = e.target; 
      const submitBtn = currentForm.querySelector('#user-modal-save-btn');
      const userId = currentForm.querySelector('#edit-user-id')?.value || '';
      const phoneInput = currentForm.querySelector('#modal-user-phone');
      const userPhone = phoneInput ? phoneInput.value.trim() : '';

    
      if (!userId) {
        const password = currentForm.querySelector('#edit-user-pass')?.value || '';
        const confirmPassword = currentForm.querySelector('#edit-user-confirm-pass')?.value || '';

        if (!password) {
          alert('Vui lòng nhập mật khẩu hợp lệ!');
          return;
        }

        if (password !== confirmPassword) {
          alert('Thao tác thất bại: Mật khẩu nhập lại không khớp.');
          const confirmInput = currentForm.querySelector('#edit-user-confirm-pass');
          if (confirmInput) confirmInput.focus(); 
          return;
        }

        if (!userPhone) {
          alert('Vui lòng nhập số điện thoại để hệ thống gửi OTP kích hoạt!');
          if (phoneInput) phoneInput.focus();
          return;
        }
      }

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerText = 'Đang lưu xử lý...';
      }

      try {
        const payload = collectUserPayload(currentForm);

        if (!userId) {
        
          await api.createUser(payload);
          
       
          const modalTitle = document.getElementById('user-modal-title');
          if (modalTitle) modalTitle.innerText = 'Xác thực kích hoạt OTP tài khoản';
          
          currentForm.style.display = 'none'; 

          const otpZone = document.getElementById('user-otp-zone');
          if (otpZone) {
            const targetPhoneSpan = document.getElementById('otp-target-phone');
            const hiddenPhoneInput = document.getElementById('otp-hidden-phone');
            const otpCodeInput = document.getElementById('otp-input-code');

            if (targetPhoneSpan) targetPhoneSpan.innerText = userPhone;
            if (hiddenPhoneInput) hiddenPhoneInput.value = userPhone;
            if (otpCodeInput) otpCodeInput.value = '';
            
            otpZone.style.display = 'block';
          }
        } else {
     
          await api.updateUser(payload);
          alert('Cập nhật dữ liệu tài khoản thành công!');
          closeModal('admin-user-modal');
          await loadCurrentModule();
        }
      } catch (err) {
        console.error('User submit error:', err);
        alert('Thao tác thất bại: ' + (err.message || 'Lỗi hệ thống từ Server'));
      } finally {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.innerText = 'Lưu thay đổi';
        }
      }
    });

    document.body.addEventListener('submit', async (e) => {
      if (e.target.id !== 'admin-otp-verify-form') return;
      e.preventDefault();

      const otpForm = e.target;
      const otpSubmitBtn = otpForm.querySelector('#otp-submit-btn');
      const phone = document.getElementById('otp-hidden-phone')?.value || '';
      const otpCode = document.getElementById('otp-input-code')?.value.trim() || '';

      if (!otpCode) {
        alert('Vui lòng điền mã OTP kích hoạt!');
        return;
      }

      if (otpSubmitBtn) {
        otpSubmitBtn.disabled = true;
        otpSubmitBtn.innerText = 'Đang xác thực...';
      }

      try {
        const result = await api.verifyUserOtp(phone, otpCode);

        if (result?.success || result?.data?.success) {
          alert('Xác thực kích hoạt tài khoản thành công!');
          
      
          closeModal('admin-user-modal');
          resetUserModalState();
         
          await loadCurrentModule();
        } else {
          alert(result?.message || 'Mã OTP không chính xác hoặc đã hết hạn!');
        }
      } catch (error) {
        console.error('OTP Validation Error:', error);
        alert('Có lỗi xảy ra trong quá trình xác thực OTP!');
      } finally {
        if (otpSubmitBtn) {
          otpSubmitBtn.disabled = false;
          otpSubmitBtn.innerText = 'Xác nhận kích hoạt';
        }
      }
    });


    document.body.addEventListener('click', (e) => {
      const targetId = e.target.id;
      if (
        targetId === 'otp-back-btn' || 
        targetId === 'close-user-modal-btn' || 
        targetId === 'user-modal-cancel-btn'
      ) {
        resetUserModalState();
      }
    });
  }


  function resetUserModalState() {
    const infoForm = document.getElementById('admin-user-form');
    const otpZone = document.getElementById('user-otp-zone');
    const modalTitle = document.getElementById('user-modal-title');
    
    if (infoForm) infoForm.style.display = 'flex';
    if (otpZone) otpZone.style.display = 'none';
    if (modalTitle) modalTitle.innerText = 'Thông tin tài khoản';
  }



function bindRevenueFilterFormSubmit() {
  const filterForm = document.getElementById('revenueFilterForm');
  if (!filterForm) return;

  filterForm.addEventListener('submit', async (e) => {
    e.preventDefault(); 

    const sellerId = document.getElementById('revenueSellerIdFilter')?.value || '';
    const companyName = pageTitle.innerText.replace('Báo cáo tài chính doanh thu: ', '') || 'Đối tác';

    if (!sellerId) {
      alert('Không xác định được mã Seller để lọc dữ liệu!');
      return;
    }

    const submitBtn = filterForm.querySelector('button[type="submit"]');
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerText = 'Đang lọc...';
    }


    await loadAdminRevenueData(sellerId, companyName);

    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.innerText = 'Tìm kiếm';
    }
  });
}

async function loadAdminRevenueData(sellerId, companyName) {
  const container = document.getElementById('revenueOrdersList');
  if (!container) return;

  const filterSellerId = document.getElementById('revenueSellerIdFilter');
  const brandSelect = document.getElementById('revenueBrandFilter');
  const dateFromInput = document.getElementById('revenueDateFrom');
  const dateToInput = document.getElementById('revenueDateTo');

  if (filterSellerId) filterSellerId.value = sellerId;

  const params = {
    seller_id: sellerId,
    brand: brandSelect?.value || '',
    date_from: dateFromInput?.value || '',
    date_to: dateToInput?.value || '',
  };

  try {
    const payload = await api.revenue(params);
    const bundle = payload?.admin || payload?.seller || payload;

    if (!bundle) {
      container.innerHTML = '<div style="text-align:center;color:#94a3b8;padding:20px;">Không có dữ liệu tài chính cho đối tượng này.</div>';
      return;
    }

    const summary = bundle.summary || {};
    const chartRows = Array.isArray(bundle.chart) ? bundle.chart : [];
    const orders = Array.isArray(bundle.orders) ? bundle.orders : [];
    const brands = Array.isArray(bundle.brands) ? bundle.brands : [];

  
    if (brandSelect && brandSelect.options.length <= 1) {
      brands.forEach(b => {
        const opt = document.createElement('option');
        opt.value = b; opt.textContent = b;
        brandSelect.appendChild(opt);
      });
      brandSelect.value = params.brand;
    }

    const gmv = Number(summary.total_gmv || summary.total_revenue || 0);
    const fee = Number(summary.platform_fee_amount || summary.platform_commission || 0);
    const payout = Number(summary.seller_payout_amount || Math.max(0, gmv - fee));
    const moneyFmt = (v) => `${Number(v).toLocaleString('vi-VN')} VND`;

    const elTotal = document.getElementById('revenueTotalAmount');
    const elCars = document.getElementById('revenueCarsSold');
    const elCompleted = document.getElementById('revenueCompletedOrders');
    const elCommission = document.getElementById('revenueCommission');
    const elChartTotal = document.getElementById('revenueChartTotal');
    const elOrdersCount = document.getElementById('revenueOrdersCount');

    if (elTotal) elTotal.textContent = moneyFmt(gmv);
    if (elCars) elCars.textContent = moneyFmt(fee);
    if (elCompleted) elCompleted.textContent = moneyFmt(payout);
    if (elCommission) elCommission.textContent = `${Number(summary.total_orders || 0)} đơn · ${Number(summary.cars_sold || 0)} xe`;
    if (elChartTotal) elChartTotal.textContent = moneyFmt(payout);
    if (elOrdersCount) elOrdersCount.textContent = `${orders.length} đơn`;

    if (orders.length === 0) {
      container.innerHTML = '<div style="text-align:center;color:#94a3b8;padding:20px;">Seller chưa hoàn thành đơn hàng nào trong khoảng thời gian lọc.</div>';
    } else {
      container.innerHTML = orders.map(adminRevenueOrderCardTemplate).join('');
    }

    renderAdminRevenueChart(chartRows);

  } catch (error) {
    console.error("Lỗi nạp doanh thu Admin:", error);
    if (container) {
      container.innerHTML = `<div style="color:#ef4444;padding:20px;text-align:center;">Lỗi: ${error.message}</div>`;
    }
  }
}


function renderAdminRevenueChart(rows) {
  const chartBox = document.getElementById('revenueChart');
  if (!chartBox) return;

  if (!rows || !rows.length) {
    chartBox.innerHTML = '<div style="color:#94a3b8; font-size:13px;">Chưa có biểu đồ biến động thời gian.</div>';
    return;
  }

  const width = 650, height = 180;
  const values = rows.map(r => Number(r.seller_payout_amount || r.total_revenue || 0));
  const maxVal = Math.max(...values, 1);
  
  const points = rows.map((row, idx) => {
    const x = 40 + (rows.length === 1 ? 570 : (idx / (rows.length - 1)) * 570);
    const val = Number(row.seller_payout_amount || row.total_revenue || 0);
    const y = 150 - ((val / maxVal) * 130);
    return { x, y, day: String(row.sale_day || '').split('-').slice(1).reverse().join('/') };
  });

  const pointStr = points.map(p => `${p.x},${p.y}`).join(' ');
  
  chartBox.innerHTML = `
    <svg viewBox="0 0 ${width} ${height}" style="width:100%; height:100%;">
      <line x1="40" y1="150" x2="610" y2="150" stroke="#cbd5e1" stroke-width="1"></line>
      <polyline points="${pointStr}" fill="none" stroke="#2563eb" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"></polyline>
      ${points.map(p => `<circle cx="${p.x}" cy="${p.y}" r="3.5" fill="#2563eb"></circle>`).join('')}
      ${points.filter((_, i) => i === 0 || i === points.length - 1 || points.length <= 6).map(p => `
        <text x="${p.x}" y="${height - 5}" font-size="10" fill="#94a3b8" text-anchor="middle">${p.day}</text>
      `).join('')}
    </svg>
  `;
}
  
});

