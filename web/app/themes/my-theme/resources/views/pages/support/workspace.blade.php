<section id="supportAdminPage" class="support-admin-page" hidden>
    <section class="support-header">
        <div>
            <p>Support Center Workspace</p>
            <h1>Hệ thống xử lý Ticket và Chat điều phối nội bộ</h1>
        </div>
        <div class="support-action-header-bar">
            <button type="button" id="btn-switch-tickets" class="sub-tab-btn active">Danh sách Ticket</button>
            <button type="button" id="btn-switch-internal-chat" class="sub-tab-btn">Kênh Chat với Admin</button>
            <button type="button" id="btn-switch-payouts" class="sub-tab-btn">Danh sách chờ giải ngân</button>
            <button type="button" id="btn-switch-seller-channel" class="sub-tab-btn">Kênh chung Seller</button>
            <button type="button" id="supportRefreshBtn">Làm mới</button>
        </div>
    </section>

    <section id="workspace-tickets-zone" class="support-layout support-admin-layout">
        <section class="support-panel support-list-panel">
            <div class="support-panel-title">
                <h2>Ticket support công khai</h2>
            </div>
            <div id="supportTicketList" class="support-ticket-list">
                <div class="support-empty">Đang tải ticket...</div>
            </div>
        </section>
    </section>

    <section id="workspace-chat-zone" class="support-layout support-chat-layout" style="display: none;">
        <div class="support-chat-sidebar">
            <h3>Danh sách Admin</h3>
            <div id="admin-users-list" class="admin-users-list">
                <p class="chat-sidebar-loading">Đang nạp dữ liệu Admin...</p>
            </div>
        </div>

        <div class="support-chat-main-content">
            <div class="chat-box-header">
                <span id="active-chat-with-name">Chọn một Admin để bắt đầu trao đổi</span>
                <input type="hidden" id="active-admin-target-id" value="0">
            </div>

            <div id="support-chat-messages-container" class="chat-messages-container">
                <p class="chat-empty-history">Vui lòng chọn tài khoản quản trị viên từ danh sách bên trái để xem lịch sử hội thoại.</p>
            </div>

            <form id="support-chat-input-form" class="chat-input-form">
                <input type="text" id="chat-raw-message" placeholder="Nhập tin nhắn phản hồi Admin..." disabled required autocomplete="off">
                <button type="submit" id="btn-send-chat" disabled>Gửi đi</button>
            </form>
        </div>
    </section>

    <section id="workspace-payout-zone" class="support-layout support-admin-layout" style="display: none;">
        <section class="support-panel support-list-panel">
            <div class="support-panel-title">
                <h2>Danh sách chờ giải ngân escrow</h2>
            </div>
            <div id="supportPayoutList" class="support-payout-list">
                <div class="support-empty">Đang tải danh sách chờ giải ngân...</div>
            </div>
        </section>
    </section>

    <section id="workspace-seller-channel-zone" class="support-layout support-admin-layout" style="display: none;">
        <section class="support-panel support-list-panel seller-channel-panel">
            <div class="support-panel-title">
                <h2>Kênh liên lạc chung toàn bộ seller</h2>
            </div>
            <div class="seller-channel-shell" data-seller-channel-shell>
                <div class="seller-channel-messages" data-seller-channel-messages>
                    <div class="support-empty">Đang tải kênh chung seller...</div>
                </div>
                <form class="seller-channel-form" data-seller-channel-form>
                    <textarea name="message" rows="3" placeholder="Nhập nội dung thông báo cho toàn bộ seller..." required></textarea>
                    <button type="submit">Gửi thông báo</button>
                </form>
            </div>
        </section>
    </section>
</section>
