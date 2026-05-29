import {
  getProductBrand,
  getProductColor,
  productDetailModalTemplate,
  productDetailTemplate,
  productEditModalTemplate,
  sellerDetailTemplate,
  sellerModalTemplate,
  userEditModalTemplate,
} from './templates.js';

export function ensureAdminModals() {
  if (!document.getElementById('admin-seller-modal')) {
    document.body.insertAdjacentHTML('beforeend', sellerModalTemplate());
  }
  if (!document.getElementById('admin-product-detail-modal')) {
    document.body.insertAdjacentHTML('beforeend', productDetailModalTemplate());
  }
  if (!document.getElementById('admin-product-modal')) {
    document.body.insertAdjacentHTML('beforeend', productEditModalTemplate());
  }
  if (!document.getElementById('admin-user-modal')) {
    document.body.insertAdjacentHTML('beforeend', userEditModalTemplate());
  }
  bindModalCloseButtons();
}

export function closeModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) modal.style.display = 'none';
}

export function openModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) modal.style.display = 'block';
}

export function openSellerModal(seller) {
  ensureAdminModals();
  const content = document.getElementById('admin-seller-modal-content');
  if (!content) return;
  content.innerHTML = sellerDetailTemplate(seller);
  openModal('admin-seller-modal');
}

export function openProductDetailModal(product, detailJson = null) {
  ensureAdminModals();
  const content = document.getElementById('deleted-product-detail-content');
  if (!content) return;
  content.innerHTML = productDetailTemplate(product, detailJson);
  openModal('admin-product-detail-modal');
}

export function openProductEditModal(product) {
  ensureAdminModals();
  setInputValue('edit-prod-id', product.id);
  setInputValue('edit-prod-name', product.name || '');
  setInputValue('edit-prod-brand', getProductBrand(product) === 'Chưa cập nhật' ? '' : getProductBrand(product));
  setInputValue('edit-prod-color', getProductColor(product) === 'Chưa cập nhật' ? '' : getProductColor(product));
  setInputValue('edit-prod-price', product.price_from || 0);
  setInputValue('edit-prod-years', product.years || '');
  setInputValue('edit-prod-quantity', product.quantity || 0);
  setInputValue('edit-prod-status', product.status || 'deleted');
  setInputValue('edit-prod-description', product.description || '');
  openModal('admin-product-modal');
}

export function collectProductEditPayload() {
  return {
    name: getInputValue('edit-prod-name'),
    brand: getInputValue('edit-prod-brand'),
    color: getInputValue('edit-prod-color'),
    price_from: parseFloat(getInputValue('edit-prod-price')) || 0,
    years: parseInt(getInputValue('edit-prod-years'), 10) || 0,
    quantity: parseInt(getInputValue('edit-prod-quantity'), 10) || 0,
    status: getInputValue('edit-prod-status') || 'deleted',
    description: getInputValue('edit-prod-description'),
  };
}



export function openUserCreateModal() {
  ensureAdminModals();
  
  const modalTitle = document.getElementById('user-modal-title');
  if (modalTitle) modalTitle.innerText = 'Tạo mới tài khoản người dùng';
  
  const submitBtn = document.getElementById('user-modal-save-btn');
  if (submitBtn) submitBtn.innerText = 'Tạo tài khoản';
  
  const usernameRow = document.getElementById('user-username-row');
  if (usernameRow) usernameRow.style.display = 'block';
  const passRow = document.getElementById('user-pass-row');
  if (passRow) passRow.style.display = 'block';
  const confirmPassRow = document.getElementById('user-confirm-pass-row');
  if (confirmPassRow) confirmPassRow.style.display = 'block';
  
  const loginInput = document.getElementById('edit-user-login');
  if (loginInput) loginInput.disabled = false;
  
  const phoneInput = document.getElementById('modal-user-phone');
  if (phoneInput) phoneInput.disabled = false; 

  setInputValue('edit-user-id', '');
  setInputValue('edit-user-login', '');
  setInputValue('edit-user-fullname', '');
  setInputValue('edit-user-pass', '');
  setInputValue('edit-user-confirm-pass', '');
  setInputValue('edit-user-email', '');
  setInputValue('modal-user-phone', '');
  setInputValue('edit-user-role', 'ROLE_SUPPORT');
  setInputValue('edit-user-company-id', '');
  const companyRow = document.getElementById('user-company-row');
  if (companyRow) companyRow.style.display = 'block';

  openModal('admin-user-modal');
}

export function openUserEditModal(user) {

  const userId = Number(user.id || user.ID);
  if (userId === 1) {
    alert('Đây là tài khoản Root Administrator quản lý hệ thống WordPress. Không được phép chỉnh sửa thông tin từ trang Quản trị này!');
    return;
  }

  ensureAdminModals();
  
  const modalTitle = document.getElementById('user-modal-title');
  if (modalTitle) modalTitle.innerText = 'Sửa đổi thông tin tài khoản';
  
 
  const submitBtn = document.getElementById('user-modal-save-btn');
  if (submitBtn) submitBtn.innerText = 'Lưu thay đổi';

  const usernameRow = document.getElementById('user-username-row');
  if (usernameRow) usernameRow.style.display = 'none';
  const passRow = document.getElementById('user-pass-row');
  if (passRow) passRow.style.display = 'none';
  const confirmPassRow = document.getElementById('user-confirm-pass-row');
  if (confirmPassRow) confirmPassRow.style.display = 'none';
  
  const phoneInput = document.getElementById('modal-user-phone');
  if (phoneInput) phoneInput.disabled = true;

  setInputValue('edit-user-id', userId);
  setInputValue('edit-user-login', user.username || user.user_login || '');
  setInputValue('edit-user-fullname', user.fullname || user.display_name || '');
  setInputValue('edit-user-email', user.email || user.user_email || '');
  setInputValue('modal-user-phone', user.phone || '');
  setInputValue('edit-user-company-id', user.company_id || '');

  let primaryRole = 'customer';
  if (Array.isArray(user.roles) && user.roles.length > 0) {
    primaryRole = user.roles[0];
  } else if (user.role) {
    primaryRole = user.role;
  }

  if (primaryRole === 'admin' || primaryRole === 'administrator') primaryRole = 'administrator';
  else if (primaryRole === 'support' || primaryRole === 'ROLE_SUPPORT') primaryRole = 'ROLE_SUPPORT';
  else if (primaryRole === 'seller') primaryRole = 'seller';
  else primaryRole = 'customer';

  setInputValue('edit-user-role', primaryRole);
  const companyRow = document.getElementById('user-company-row');
  if (companyRow) companyRow.style.display = 'none';

  openModal('admin-user-modal');
}


export function collectUserPayload(formElement) {
  const userId = formElement.querySelector('#edit-user-id')?.value || '';
  let selectedRole = formElement.querySelector('#edit-user-role')?.value || 'customer';
  

  if (selectedRole === 'administrator') selectedRole = 'admin';
  if (selectedRole === 'ROLE_SUPPORT') selectedRole = 'support';
  if (selectedRole === 'customer') selectedRole = 'buyer';

  const payload = {
    name: formElement.querySelector('#edit-user-fullname')?.value || '', 
    email: formElement.querySelector('#edit-user-email')?.value || '',
    phone: formElement.querySelector('#modal-user-phone')?.value || '',
    role: selectedRole,
    company_id: Number(formElement.querySelector('#edit-user-company-id')?.value || 0)
  };

  if (!userId) {
  
    payload.password = formElement.querySelector('#edit-user-pass')?.value || '';
    payload.confirm_password = formElement.querySelector('#edit-user-confirm-pass')?.value || '';
    payload.username = formElement.querySelector('#edit-user-login')?.value || '';
    if (!payload.name) {
      payload.name = payload.username;
    }
  } else {
    payload.user_id = userId;
    payload.fullname = payload.name; 
  }
  
  return payload;
}

function bindModalCloseButtons() {
  document.getElementById('close-modal-btn')?.addEventListener('click', () => closeModal('admin-product-modal'));
  document.getElementById('modal-cancel-btn')?.addEventListener('click', () => closeModal('admin-product-modal'));
  document.getElementById('close-seller-modal-btn')?.addEventListener('click', () => closeModal('admin-seller-modal'));
  document.getElementById('seller-modal-close-btn')?.addEventListener('click', () => closeModal('admin-seller-modal'));
  document.getElementById('close-product-detail-modal-btn')?.addEventListener('click', () => closeModal('admin-product-detail-modal'));
  document.getElementById('product-detail-close-btn')?.addEventListener('click', () => closeModal('admin-product-detail-modal'));
  
  document.getElementById('close-user-modal-btn')?.addEventListener('click', () => closeModal('admin-user-modal'));
  document.getElementById('user-modal-cancel-btn')?.addEventListener('click', () => closeModal('admin-user-modal'));

  window.addEventListener('click', (event) => {
    ['admin-product-detail-modal', 'admin-seller-modal', 'admin-product-modal', 'admin-user-modal'].forEach((modalId) => {
      const modal = document.getElementById(modalId);
      if (event.target === modal) closeModal(modalId);
    });
  });
}

function setInputValue(id, value) {
  const input = document.getElementById(id);
  if (input) input.value = value;
}

function getInputValue(id) {
  return document.getElementById(id)?.value || '';
}
