import { escapeHtml } from './common.js';

export function supportUserItemTemplate(user) {
  const supportId = user.id || user.ID || user.wp_user_id || '';
  
  const userRole = String(user.role || '').toLowerCase();
  const isClassAdmin = userRole === 'admin' || userRole === 'administrator';
  
  const roleBadgeText = isClassAdmin ? 'Quản Trị' : 'Hỗ Trợ';

  const roleClass = isClassAdmin ? 'role-admin' : 'role-support';

  const nameText = user.fullname || user.display_name || user.username || user.user_login || 'Mối liên hệ nội bộ';

  return `
    <div class="support-user-item" data-support-id="${escapeHtml(supportId)}">
      <div class="user-item-info" style="width: 100%;">
        <div class="chat-user-header-block">
          <span class="user-item-name">${escapeHtml(nameText)}</span>
          <span class="chat-role-text ${roleClass}">(${escapeHtml(roleBadgeText)})</span>
        </div>
        <div class="user-item-email">
          Số ĐT: ${escapeHtml(user.phone || 'Chưa cập nhật')}
        </div>
      </div>
    </div>
  `;
}

export function internalChatLayoutTemplate(supportTeamHtml) {
  return `
    <div class="admin-chat-wrapper">
      <aside class="admin-chat-sidebar">
        <div class="sidebar-header">DANH SÁCH NHÂN VIÊN NỘI BỘ</div>
        <div class="support-users-list">${supportTeamHtml}</div>
      </aside>
      <main class="admin-chat-main-box">
        <div id="chat-messages-container" class="chat-messages-container">
          <div class="chat-select-placeholder">
            Vui lòng chọn một nhân viên bên trái để bắt đầu cuộc hội thoại.
          </div>
        </div>
        <form id="admin-chat-input-form" class="admin-chat-input-form">
          <input type="hidden" id="active-support-id" value="0">
          <input
            type="text"
            id="chat-raw-message"
            disabled
            placeholder="Chưa có cuộc hội thoại nào được chọn."
            autocomplete="off"
          >
          <button type="submit" id="btn-send-chat" disabled class="btn-chat-submit">
            Gửi đi
          </button>
        </form>
      </main>
    </div>
  `;
}

export function singleChatMessageTemplate(msg) {
  const isMe = msg.is_me_flag === true;
  const msgClass = isMe ? 'msg-sender-me' : 'msg-sender-them';
  const displayName = isMe ? 'Bạn' : msg.sender_name;
  const timeStr = msg.created_at ? msg.created_at.substring(11, 16) : ''; 
  
  const cleanMessage = escapeHtml(msg.message || '');

  return `
    <div class="chat-message-row ${msgClass}">
      <div class="chat-bubble">
        <div class="chat-bubble-name">${escapeHtml(displayName)}</div>
        <div class="chat-bubble-text">${cleanMessage}</div>
        <div class="chat-bubble-time">${timeStr}</div>
      </div>
    </div>
  `;
}

