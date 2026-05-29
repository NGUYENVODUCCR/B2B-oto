export function createInternalSupportChat({
  state,
  adminApi,
  escapeHtml,
  normalizeSupportChatMessages,
  supportCurrentUserId,
  supportCurrentUserName,
}) {
  let lastSupportChatHtmlCache = '';

  async function loadAdminSupportTeam() {
    const sidebarList = document.getElementById('admin-users-list');
    if (!sidebarList) return;

    try {
      const supportTeam = await adminApi.fetchSupportTeamList().catch(() => []);
      const finalAdminsMap = new Map();

      supportTeam.forEach((user) => {
        const userRoles = user.roles || user.role || [];
        const rolesArray = Array.isArray(userRoles) ? userRoles : [String(userRoles)];

        const isAdmin = rolesArray.some((role) => {
          const roleStr = String(role?.role_name || role).toUpperCase();
          return roleStr === 'ADMINISTRATOR' || roleStr === 'ADMIN';
        });

        if (isAdmin) {
          const uid = String(user.id || user.ID);
          user.is_current_admin = true;
          finalAdminsMap.set(uid, user);
        }
      });

      if (state.adminList && state.adminList.length > 0) {
        state.adminList.forEach((oldAdmin) => {
          const oldAdminId = String(oldAdmin.id || oldAdmin.ID);
          const hasChatHistory = Boolean(state.lastMessagesTimestamps[oldAdminId]);

          if (!finalAdminsMap.has(oldAdminId) && hasChatHistory) {
            oldAdmin.is_current_admin = false;
            finalAdminsMap.set(oldAdminId, oldAdmin);
          }
        });
      }

      state.adminList = Array.from(finalAdminsMap.values());

      if (!state.allUsersNameMap) state.allUsersNameMap = {};
      state.adminList.forEach((user) => {
        const uid = String(user.id || user.ID);
        state.allUsersNameMap[uid] = user.fullname || user.display_name || user.username || 'Quản trị viên';
      });

      renderAdminSidebarList();
    } catch (error) {
      console.error('Lỗi đồng bộ danh sách Support nội bộ:', error);
      renderAdminSidebarList();
    }
  }

  function renderAdminSidebarList() {
    const sidebarList = document.getElementById('admin-users-list');
    if (!sidebarList) return;

    if (!state.adminList.length) {
      sidebarList.innerHTML = '<p class="chat-sidebar-empty">Không tìm thấy tài khoản Admin nào.</p>';
      return;
    }

    sidebarList.innerHTML = state.adminList.map((admin) => {
      const name = admin.fullname || admin.display_name || admin.username || 'Quản trị viên';
      const adminId = admin.id || admin.ID;
      const activeClass = String(state.activeAdminId) === String(adminId) ? 'selected-active' : '';
      const roleTitle = admin.is_current_admin !== false ? 'Administrator' : 'Đã đổi quyền (Lịch sử)';
      const unreadCount = state.unreadCounts[adminId] || 0;
      const badgeHtml = unreadCount > 0 ? `<span class="chat-alert-badge">${unreadCount}</span>` : '';

      return `
        <div class="admin-user-item ${activeClass}" data-admin-id="${escapeHtml(adminId)}">
          <div class="admin-avatar-ui" style="${admin.is_current_admin === false ? 'background: #94a3b8;' : ''}">A</div>
          <div class="admin-info-ui" style="flex: 1; display: flex; justify-content: space-between; align-items: center; width: 100%;">
            <div style="display: flex; flex-direction: column; overflow: hidden; max-width: 80%;">
              <span class="admin-name" style="${admin.is_current_admin === false ? 'color: #64748b;' : ''}">${escapeHtml(name)}</span>
              <span class="admin-role">${escapeHtml(roleTitle)}</span>
            </div>
            ${badgeHtml}
          </div>
        </div>
      `;
    }).join('');
  }

  async function checkNewMessagesNotifications() {
    if (state.currentSubTab !== 'chat') return;

    let needReloadSidebar = false;
    const targetIds = state.adminList.map((admin) => String(admin.id || admin.ID));

    for (const adminId of targetIds) {
      try {
        const rawMessages = await adminApi.fetchSupportMessages(adminId);
        const messages = normalizeSupportChatMessages(rawMessages);
        if (!messages || !messages.length) continue;

        const partnerMessages = messages.filter((message) => {
          let senderId = message.sender_id || message.senderId || message.user_id || message.userId || 0;
          if (message.sender && typeof message.sender === 'object') {
            senderId = message.sender.id || message.sender.ID || senderId;
          }
          return String(senderId) === String(adminId);
        });

        if (!partnerMessages.length) continue;

        const lastMsg = partnerMessages[partnerMessages.length - 1];
        const lastMsgTime = lastMsg.created_at || lastMsg.timestamp || '';

        if (String(state.activeAdminId) === String(adminId)) {
          state.unreadCounts[adminId] = 0;
          state.lastMessagesTimestamps[adminId] = lastMsgTime;
          continue;
        }

        if (state.lastMessagesTimestamps[adminId] !== lastMsgTime) {
          state.unreadCounts[adminId] = (state.unreadCounts[adminId] || 0) + 1;
          state.lastMessagesTimestamps[adminId] = lastMsgTime;
          needReloadSidebar = true;
        }
      } catch (error) {
        console.error(`Lỗi đồng bộ ngầm cho ID ${adminId}`, error);
      }
    }

    if (needReloadSidebar) {
      renderAdminSidebarList();
    }
  }

  async function loadChatMessagesWithAdmin(adminId, isPolling = false) {
    const container = document.getElementById('support-chat-messages-container');
    if (!container || state.currentSubTab !== 'chat') return;

    try {
      const rawMessages = await adminApi.fetchSupportMessages(adminId);
      const messages = normalizeSupportChatMessages(rawMessages);
      const activeAdmin = state.adminList.find((user) => String(user.id || user.ID) === String(adminId));
      const partnerName = activeAdmin ? (activeAdmin.fullname || activeAdmin.display_name || activeAdmin.username) : 'Admin Gốc';
      const myName = supportCurrentUserName() || 'Support';
      const myId = supportCurrentUserId();

      const html = messages.map((message) => {
        let senderId = message.sender_id || message.senderId || message.user_id || message.userId || 0;
        if (message.sender && typeof message.sender === 'object') {
          senderId = message.sender.id || message.sender.ID || senderId;
        }

        senderId = Number(senderId);

        const isMe = myId > 0
          ? senderId === myId
          : (String(senderId) !== String(adminId) && senderId !== 0);

        const senderTitle = isMe ? `${myName} (Bạn)` : partnerName;
        const messageClass = isMe ? 'msg-row-me' : 'msg-row-partner';

        return `
          <div class="chat-message-row ${messageClass}">
            <div class="message-bubble">
              <span class="message-sender-name">${escapeHtml(senderTitle)}</span>
              <p class="message-text-content">${escapeHtml(message.message || message.content || '')}</p>
            </div>
          </div>
        `;
      }).join('');

      const nextHtml = html.length
        ? html
        : `<p class="chat-empty-history">Bắt đầu cuộc trò chuyện nội bộ an toàn với ${escapeHtml(partnerName)}.</p>`;

      if (isPolling && lastSupportChatHtmlCache === nextHtml) {
        return;
      }

      const isAtBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 120;
      container.innerHTML = nextHtml;
      lastSupportChatHtmlCache = nextHtml;

      if (!isPolling || isAtBottom) {
        container.scrollTop = container.scrollHeight;
      }
    } catch (error) {
      console.error('Không thể đồng bộ tin nhắn từ Admin:', error);
    }
  }

  async function handleSendSupportChat(event) {
    event.preventDefault();
    const input = document.getElementById('chat-raw-message');
    const message = input?.value?.trim();
    const adminId = state.activeAdminId;

    if (!message || !adminId) return;
    input.value = '';

    try {
      await adminApi.sendToSupport(adminId, message);
      await loadChatMessagesWithAdmin(adminId, false);
    } catch (error) {
      alert(error.message || 'Không gửi được tin nhắn.');
    }
  }

  function handleAdminSidebarClick(event) {
    const item = event.target.closest('.admin-user-item');
    if (!item) return;

    const adminId = item.dataset.adminId;
    lastSupportChatHtmlCache = '';
    state.activeAdminId = adminId;
    state.unreadCounts[adminId] = 0;

    document.querySelectorAll('.admin-user-item').forEach((element) => element.classList.remove('selected-active'));
    item.classList.add('selected-active');

    const adminName = item.querySelector('.admin-name').innerText;
    const activeChatWithName = document.getElementById('active-chat-with-name');
    const chatRawMessage = document.getElementById('chat-raw-message');
    const btnSendChat = document.getElementById('btn-send-chat');

    if (activeChatWithName) activeChatWithName.innerText = `Đang trò chuyện với: ${adminName}`;
    if (chatRawMessage) {
      chatRawMessage.disabled = false;
      chatRawMessage.placeholder = 'Nhập tin nhắn nội bộ gửi Admin...';
    }
    if (btnSendChat) btnSendChat.disabled = false;

    loadChatMessagesWithAdmin(adminId, false);
    renderAdminSidebarList();
  }

  return {
    loadAdminSupportTeam,
    renderAdminSidebarList,
    checkNewMessagesNotifications,
    loadChatMessagesWithAdmin,
    handleSendSupportChat,
    handleAdminSidebarClick,
  };
}
