import { escapeHtml } from './common.js';

export function userCardTemplate(user) {
  const isBlocked = user.is_banned === true || user.status === 'blocked' || user.status === 'banned';
  
  const badgeClass = isBlocked ? 'status-blocked' : 'status-active';
  const badgeText = isBlocked ? 'Đang bị khóa (Blocked)' : 'Đang hoạt động (Active)';
  

  const btnToggleClass = isBlocked ? 'btn-unlock-user' : 'btn-lock-user';
  const btnToggleText = isBlocked ? ' Mở khóa tài khoản' : ' Khóa tài khoản';
  const currentTargetStatus = isBlocked ? 'active' : 'blocked';

  const rolesText = Array.isArray(user.roles) ? user.roles.join(', ') : 'customer';

  return `
    <div class="admin-card user-detail-card" style="border: 1px solid #e0e0e0; padding: 15px; border-radius: 8px; background: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
      <div class="user-card-header" style="display: flex; align-items: center; margin-bottom: 10px;">
        <div style="font-size: 24px; margin-right: 10px;">👤</div>
        <div>
          <h3 style="margin: 0; font-size: 15px; color: #333; font-weight: bold;">
            Họ & Tên: ${escapeHtml(user.fullname)}
          </h3>
          <span style="font-size: 12px; color: #757575;">Tên đăng nhập: <strong>${escapeHtml(user.username)}</strong></span>
        </div>
      </div>
      
      <div class="user-card-body" style="font-size: 13px; color: #424242; line-height: 1.6;">
        <p style="margin: 4px 0;"><strong>Email:</strong> ${escapeHtml(user.email || 'Không có')}</p>
        <p style="margin: 4px 0;"><strong>Số điện thoại:</strong> ${escapeHtml(user.phone || 'Chưa cập nhật')}</p>
        <p style="margin: 4px 0;"><strong>Vai trò (Role):</strong> <span class="role-badge" style="background: #efebe9; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: bold;">${escapeHtml(rolesText.toUpperCase())}</span></p>
        <p style="margin: 4px 0;">
          <strong>Trạng thái:</strong> 
          <span class="status-badge ${badgeClass}">
            ${escapeHtml(badgeText)}
          </span>
        </p>
      </div>

      <div class="card-actions" style="margin-top: 15px; display: flex; gap: 8px; justify-content: flex-end;">
        <button 
          class="info-btn edit-user-btn" 
          data-id="${escapeHtml(user.id)}"
          style="padding: 6px 12px; font-size: 12px; cursor: pointer;"
        >
          Sửa đổi
        </button>
        
        <button 
          class="action-btn toggle-user-status-btn ${btnToggleClass}" 
          data-id="${escapeHtml(user.id)}"
          data-target-status="${currentTargetStatus}"
          style="padding: 6px 12px; font-size: 12px; cursor: pointer; font-weight: bold;"
        >
          ${btnToggleText}
        </button>

        <button 
          class="action-btn delete-user-btn" 
          data-id="${escapeHtml(user.id)}"
          style="padding: 6px 12px; font-size: 12px; cursor: pointer; background-color: #757575;"
        >
          Xóa
        </button>
      </div>
    </div>
  `;
}


export function userEditModalTemplate() {
  return `
    <div id="admin-user-modal" class="admin-modal" style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); padding-top: 60px;">
      <div class="admin-modal-content" style="background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 50%; max-width: 500px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); position: relative;">
        
        <span id="close-user-modal-btn" style="position: absolute; right: 15px; top: 10px; color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
        
        <h2 id="user-modal-title" style="margin-top: 0; font-size: 18px; color: #333; border-bottom: 1px solid #eee; padding-bottom: 10px;">
          Thông tin tài khoản
        </h2>
        
        <form id="admin-user-form" style="display: flex; flex-direction: column; gap: 12px; margin-top: 15px;">
          
          <input type="hidden" id="edit-user-id" name="user_id" value="">

          <div id="user-username-row" style="display: block;">
            <label style="display: block; font-weight: bold; font-size: 13px; margin-bottom: 4px;">Tên đăng nhập (Username) <span style="color: red;">*</span></label>
            <input type="text" id="edit-user-login" style="width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px;" placeholder="Nhập tên đăng nhập...">
          </div>

          <div>
            <label style="display: block; font-weight: bold; font-size: 13px; margin-bottom: 4px;">Họ & Tên <span style="color: red;">*</span></label>
            <input type="text" id="edit-user-fullname" style="width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px;" placeholder="Nhập họ và tên...">
          </div>

          <div id="user-pass-row" style="display: block;">
            <label style="display: block; font-weight: bold; font-size: 13px; margin-bottom: 4px;">Mật khẩu <span style="color: red;">*</span></label>
            <input type="password" id="edit-user-pass" style="width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px;" placeholder="Nhập mật khẩu...">
          </div>

          <div id="user-confirm-pass-row" style="display: block;">
            <label style="display: block; font-weight: bold; font-size: 13px; margin-bottom: 4px;">Xác nhận mật khẩu <span style="color: red;">*</span></label>
            <input type="password" id="edit-user-confirm-pass" style="width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px;" placeholder="Nhập lại mật khẩu...">
          </div>

          <div>
            <label style="display: block; font-weight: bold; font-size: 13px; margin-bottom: 4px;">Email</label>
            <input type="email" id="edit-user-email" style="width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px;" placeholder="example@gmail.com">
          </div>

          <div>
            <label style="display: block; font-weight: bold; font-size: 13px; margin-bottom: 4px;">Số điện thoại <span style="color: red;">*</span></label>
            <input type="text" id="modal-user-phone" style="width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px;" placeholder="Nhập số điện thoại...">
          </div>

          <div>
            <label style="display: block; font-weight: bold; font-size: 13px; margin-bottom: 4px;">Phân quyền (Role) <span style="color: red;">*</span></label>
            <select id="edit-user-role" style="width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; background-color: #fff;">
              <option value="customer">Khách mua xe </option>
              <option value="seller">Đối tác bán xe </option>
              <option value="ROLE_SUPPORT">Nhân viên hỗ trợ </option>
              <option value="administrator">Quản trị viên tối cao </option>
            </select>
          </div>

          <div id="user-company-row">
            <label style="display: block; font-weight: bold; font-size: 13px; margin-bottom: 4px;">Công ty tham gia</label>
            <select id="edit-user-company-id" style="width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; background-color: #fff;">
              <option value="">Chọn công ty có sẵn</option>
            </select>
          </div>

          <div class="modal-form-actions" style="margin-top: 15px; display: flex; gap: 10px; justify-content: flex-end; border-top: 1px solid #eee; padding-top: 15px;">
            <button type="button" id="user-modal-cancel-btn" style="padding: 8px 16px; border: 1px solid #ccc; background: #fff; border-radius: 4px; cursor: pointer; font-size: 13px;">
              Hủy bỏ
            </button>
            <button type="submit" id="user-modal-save-btn" style="padding: 8px 16px; border: none; background: #007bff; color: #fff; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: bold;">
              Tạo tài khoản
            </button>
          </div>
        </form>

        <div id="user-otp-zone" style="display: none; margin-top: 15px;">
          <p style="font-size: 13px; color: #555; margin-bottom: 12px;">
            Hệ thống đã gửi mã OTP kích hoạt đến số điện thoại: <strong id="otp-target-phone"></strong>
          </p>
          <form id="admin-otp-verify-form" style="display: flex; flex-direction: column; gap: 12px;">
            <input type="hidden" id="otp-hidden-phone" name="phone">
            
            <div>
              <label style="display: block; font-weight: bold; font-size: 13px; margin-bottom: 4px;">Mã OTP Xác Thực <span style="color: red;">*</span></label>
              <input type="text" id="otp-input-code" name="otp" required style="width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; text-align: center; font-size: 18px; letter-spacing: 4px;" placeholder="••••••" maxlength="6">
            </div>

            <div style="margin-top: 15px; display: flex; gap: 10px; justify-content: flex-end; border-top: 1px solid #eee; padding-top: 15px;">
              <button type="button" id="otp-back-btn" style="padding: 8px 16px; border: 1px solid #ccc; background: #fff; border-radius: 4px; cursor: pointer; font-size: 13px;">
                Quay lại
              </button>
              <button type="submit" id="otp-submit-btn" style="padding: 8px 16px; border: none; background: #28a745; color: #fff; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: bold;">
                Xác nhận kích hoạt
              </button>
            </div>
          </form>
        </div>

      </div>
    </div>
  `;
}
