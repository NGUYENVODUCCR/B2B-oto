

let activeSupportId = null;
let chatInterval = null;


export function initChatModule() {
  activeSupportId = null;
  if (chatInterval) {
    clearInterval(chatInterval);
    chatInterval = null;
  }
}


export function clearChatModule() {
  if (chatInterval) {
    clearInterval(chatInterval);
    chatInterval = null;
  }
}


export function renderChatLayout(requestList, allSupports) {
  requestList.innerHTML = `
    <div class="chat-modular-container">
      <div class="chat-sidebar">
        <div class="chat-sidebar-header">👥 Đội ngũ hỗ trợ</div>
        <div class="support-users-flow" id="support-users-flow">
          ${allSupports.map(sup => `
            <button class="open-support-room-btn ${String(activeSupportId) === String(sup.id || sup.ID || sup.wp_user_id) ? 'active' : ''}" 
                    data-id="${sup.id || sup.ID || sup.wp_user_id}" 
                    data-name="${sup.fullname || sup.display_name || sup.username}">
              <div class="chat-user-title">${sup.fullname || sup.display_name || sup.username}</div>
              <small class="chat-user-meta">${sup.phone || 'Không có SĐT'}</small>
            </button>
          `).join('')}
        </div>
      </div>
      
      <div class="chat-main-flow">
        <div class="chat-flow-header" id="chat-target-title">
          ${activeSupportId ? '🔄 Đang kết nối hội thoại...' : '💬 Vui lòng chọn 1 Support viên để chat'}
        </div>
        
        <div class="chat-messages-box chat-messages-container" id="chat-messages-stream">
          <p class="admin-state-message" style="margin: auto; text-align: center;">Nội dung tin nhắn bảo mật hệ thống.</p>
        </div>
        
        <div class="chat-input-area" id="chat-input-block" style="display: ${activeSupportId ? 'block' : 'none'};">
          <form id="modular-chat-submit-form" class="admin-chat-input-form">
            <input type="text" id="modular-msg-input" placeholder="Nhập nội dung tin nhắn..." required autocomplete="off">
            <button type="submit" class="admin-dark-btn gửi-di-btn">Gửi tin</button>
          </form>
        </div>
      </div>
    </div>
  `;
}


export async function streamRoomMessages(api) {
  if (!activeSupportId) return;
  
  try {
    const res = await api.fetchSupportMessages(activeSupportId);
    const streamBox = document.getElementById('chat-messages-stream');
    if (!streamBox || !res.success) return;

    if (!res.data || res.data.length === 0) {
      streamBox.innerHTML = '<p class="admin-state-message" style="margin: auto; text-align: center;">Chưa có tin nhắn. Nhập tin và gửi để bắt đầu!</p>';
      return;
    }

    const currentAdminName = window.B2B_CONFIG?.currentUserTitle || 'HaAdmin1';

    streamBox.innerHTML = res.data.map(msg => {
      const senderIdStr = String(msg.sender_id);
      const activeSupportIdStr = String(activeSupportId);

      let displayName = '';
      let msgClass = '';

 
      if (senderIdStr === activeSupportIdStr) {
        const sidebarTarget = document.querySelector(`.open-support-room-btn[data-id="${activeSupportId}"]`);
        const senderName = sidebarTarget ? sidebarTarget.dataset.name : 'Hung ADN';
        displayName = `${senderName} (Hỗ trợ)`;
        msgClass = 'msg-sender-them'; 
      } else {
  
        displayName = `${currentAdminName} (Quản trị viên)`;
        msgClass = 'msg-sender-me'; 
      }

      return `
        <div class="chat-message-row ${msgClass}">
          <div class="chat-message-bubble">
            <div class="chat-meta-name" style="font-weight: bold; font-size: 12px; margin-bottom: 4px;">${displayName}</div>
            <div class="chat-text-content">${msg.message || ''}</div>
            <small class="chat-meta-time" style="display: block; font-size: 10px; color: #888; margin-top: 4px;">${msg.created_at || ''}</small>
          </div>
        </div>
      `;
    }).join('');
    
    streamBox.scrollTop = streamBox.scrollHeight;
  } catch (e) {
    console.warn('STREAM CHAT ERROR:', e);
  }
}
export function handleRoomSelection(target, api) {
  document.querySelectorAll('.open-support-room-btn').forEach(b => b.classList.remove('active'));
  target.classList.add('active');
  
  activeSupportId = target.dataset.id;
  
  const titleEl = document.getElementById('chat-target-title');
  const inputBlockEl = document.getElementById('chat-input-block');
  
  if (titleEl) titleEl.innerText = `Cuộc hội thoại với: ${target.dataset.name}`;
  if (inputBlockEl) inputBlockEl.style.display = 'block';
  
  if (chatInterval) clearInterval(chatInterval);
  

  streamRoomMessages(api);
  chatInterval = setInterval(() => streamRoomMessages(api), 3000);
}


export async function handleSendMessage(e, api) {
  e.preventDefault();
  const input = document.getElementById('modular-msg-input');
  const text = input ? input.value.trim() : '';
  
  if (!text || !activeSupportId) return;

  try {
    await api.sendToSupport(activeSupportId, text);
    if (input) input.value = '';
    await streamRoomMessages(api);
  } catch (error) {
    console.error('SEND CHAT ERROR:', error);
    alert('Không thể gửi tin nhắn, vui lòng thử lại.');
  }
}