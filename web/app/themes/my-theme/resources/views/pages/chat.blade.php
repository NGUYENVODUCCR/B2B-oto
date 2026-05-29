@push('styles')
<link rel="stylesheet" href="{{ get_theme_file_uri('/resources/styles/pages/chat.css') }}?v={{ filemtime(get_theme_file_path('/resources/styles/pages/chat.css')) }}">
@endpush

@push('scripts')
<script type="module" src="{{ get_theme_file_uri('/resources/scripts/pages/chat.js') }}?v={{ filemtime(get_theme_file_path('/resources/scripts/pages/chat.js')) }}"></script>
@endpush

<div class="b2b-chat-page">
    <aside class="b2b-chat-sidebar">
        <div class="b2b-chat-sidebar-header">
            <div>
                <h1>Chat giao dịch</h1>
                <p>RFQ, báo giá, hợp đồng và escrow</p>
            </div>
            <button type="button" id="chatRefreshBtn" class="chat-icon-btn" aria-label="Làm mới">↻</button>
        </div>

        <div class="b2b-chat-search">
            <input type="text" id="chatSearchInput" placeholder="Tìm theo công ty, sản phẩm, RFQ...">
        </div>

        <div id="chatConversationList" class="chat-conversation-list">
            <div class="chat-empty">Đang tải đoạn chat...</div>
        </div>
    </aside>

    <section class="b2b-chat-main">
        <div id="chatEmptyState" class="chat-empty-state">
            <h2>Chọn một đoạn chat</h2>
            <p>Seller có thể phản hồi buyer và gửi báo giá tại đây. Buyer có thể thương lượng, chấp nhận báo giá và đi tiếp hợp đồng.</p>
        </div>

        <div id="chatActivePane" class="chat-active-pane hidden">
            <header class="chat-active-header">
                <div>
                    <div id="chatActiveKicker" class="chat-active-kicker">RFQ</div>
                    <h2 id="chatActiveTitle">Đoạn chat</h2>
                    <p id="chatActiveMeta"></p>
                </div>
                <span id="chatActiveStatus" class="chat-status-badge">pending</span>
            </header>

            <div class="chat-workspace">
                <div class="chat-message-column">
                    <div id="chatMessages" class="chat-messages"></div>

                    <form id="chatMessageForm" class="chat-message-form">
                        <input type="text" id="chatMessageInput" placeholder="Nhập tin nhắn..." autocomplete="off" required>
                        <button type="submit">Gửi</button>
                    </form>
                </div>

                <aside id="chatDealPanel" class="chat-deal-panel"></aside>
            </div>
        </div>
    </section>
</div>

