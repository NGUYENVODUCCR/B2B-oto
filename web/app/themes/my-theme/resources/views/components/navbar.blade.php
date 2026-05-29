<link rel="stylesheet" href="{{ get_theme_file_uri('/resources/styles/components/navbar-bulk.css') }}?v={{ filemtime(get_theme_file_path('/resources/styles/components/navbar-bulk.css')) }}">

@php
    $navbarCurrentUser = wp_get_current_user();
    $navbarUserId = (int) ($navbarCurrentUser->ID ?? 0);
    $navbarRoles = array_map('strtolower', (array) ($navbarCurrentUser->roles ?? []));
    $isSupportNavbarUser = false;

    foreach ($navbarRoles as $roleName) {
        if (strpos((string) $roleName, 'support') !== false || strpos((string) $roleName, 'admin') !== false) {
            $isSupportNavbarUser = true;
            break;
        }
    }

    if (! $isSupportNavbarUser && $navbarUserId > 0 && class_exists('\\B2B\\Helpers\\RoleHelper')) {
        try {
            $isSupportNavbarUser = \B2B\Helpers\RoleHelper::hasRole($navbarUserId, 'ROLE_SUPPORT')
                || \B2B\Helpers\RoleHelper::hasRole($navbarUserId, 'ROLE_ADMIN');
        } catch (\Throwable $exception) {
        }
    }

    $supportNavHref = home_url('/support/');
    $isUserLoggedInPHP = ($navbarUserId > 0);
@endphp

<nav class="navbar b2b-navbar">
    <div class="navbar-left" style="display: flex; align-items: center;">
        <div class="logo" style="pointer-events: none; cursor: default; display: inline-flex; align-items: center; text-decoration: none; padding: 0; margin-right: 25px; background: transparent; min-height: 40px;">
            <div style="display: flex; align-items: center; gap: 8px;">

                <div style="width: 38px; flex-shrink: 0; display: flex; align-items: center;">
                    <svg viewBox="0 0 100 48" style="width: 100%; height: auto; fill: #ffffff; display: block;">
                        <path d="M5,18 L18,18 L22,29 L10,29 Z" fill="none" stroke="#ffffff" stroke-width="1.8" stroke-linejoin="round"/>
                        <line x1="9" y1="22" x2="19" y2="22" stroke="#ffffff" stroke-width="1"/>
                        <line x1="11" y1="26" x2="21" y2="26" stroke="#ffffff" stroke-width="1"/>
                        <line x1="14" y1="18" x2="17" y2="29" stroke="#ffffff" stroke-width="1"/>
                        <circle cx="12" cy="32" r="2.2"/>
                        <circle cx="19" cy="32" r="2.2"/>
                        <path d="M5,18 L2,15" stroke="#ffffff" stroke-width="1.8" stroke-linecap="round"/>

                        <path d="M22,28 C26,24 33,12 50,11 C65,10 76,17 84,21 C92,23 96,26 96,28 C92,29 88,29 86,29 C84,25 80,22 75,22 C70,22 66,25 64,29 L44,29 C42,25 38,22 33,22 C28,22 24,25 22,29 Z"/>
                        <path d="M49,13 C38,14 33,22 33,22 L60,22 L59,13 Z" fill="#0d212c"/>
                        <path d="M62,13 L62,22 L76,22 C71,18 66,14 62,13 Z" fill="#0d212c"/>
                        <circle cx="33" cy="29" r="6" fill="#0d212c"/>
                        <circle cx="75" cy="29" r="6" fill="#0d212c"/>
                    </svg>
                </div>

                <div style="display: flex; flex-direction: column; align-items: flex-start; justify-content: center; line-height: 1;">
                    <span style="color: #ffffff; font-family: 'Arial Black', 'Impact', sans-serif; font-size: 14px; font-weight: 900; letter-spacing: 0.3px; margin: 0; padding: 0;">
                        Ô TÔ
                    </span>
                    <span style="color: #ffffff; font-family: 'Arial', sans-serif; font-size: 7.5px; font-weight: 700; letter-spacing: 0.3px; text-transform: uppercase; margin: 2px 0 0 0; padding: 0; opacity: 0.95;">
                        TRỰC TUYẾN
                    </span>
                    <div style="width: 100%; height: 1px; background: linear-gradient(90deg, #ffffff 0%, transparent 95%); margin-top: 1px;"></div>
                </div>

            </div>
        </div>

        <div class="nav-links">
            <div class="home-dropdown-wrapper">
                <a id="homeMainLink" href="#">
                    Trang chủ <span id="homeSubArrow" class="home-sub-arrow">▼</span>
                </a>

                <div class="home-sub-menu" id="homeSubMenu"></div>
            </div>

            <div class="orders-dropdown-wrapper">
                <a href="{{ home_url('/orders') }}" class="navbar-link" id="ordersMainLink">
                    Đơn hàng <span class="orders-sub-arrow">▼</span>
                </a>
                <div class="orders-sub-menu" id="ordersSubMenu">
                    <a href="#" id="bulkPurchaseNavLink">Thu mua số lượng lớn</a>
                    <a href="{{ home_url('/orders') }}">Quản lý đơn hàng</a>
                </div>
            </div>
            <a href="{{ home_url('/chat') }}" class="navbar-link navbar-chat-link">
                Tin nhắn
                <span id="chatBadge" class="navbar-chat-badge hidden">0</span>
            </a>
            <a href="{{ home_url('/revenue') }}" id="navbarRevenueLink" class="navbar-link navbar-revenue-link" hidden>Doanh thu</a>
            <a href="{{ $supportNavHref }}" id="navbarSupportLink" class="navbar-link">Hỗ trợ</a>
        </div>
    </div>

    <div class="navbar-right">
        <div id="nav-logged-in-zone" style="display: {{ $isUserLoggedInPHP ? 'flex' : 'none' }}; align-items: center; gap: 15px;">
            <a href="{{ home_url('/wallet') }}" class="balance navbar-wallet-link" @if($isSupportNavbarUser) hidden @endif>
                Số dư: <span id="navWalletBalance">0 VND</span>
            </a>

            <span class="divider" @if($isSupportNavbarUser) hidden @endif>|</span>

            <div class="user-wrapper">
                <div class="user-info" id="userDropdownBtn">
                    <img id="navUserAvatar" class="nav-user-avatar" src="https://www.w3schools.com/howto/img_avatar.png" alt="Avatar">
                    <span id="userName">User</span> ▼
                </div>

                <div class="user-dropdown-menu" id="userDropdownMenu">
                    <a href="#" id="viewProfileItem" class="user-dropdown-link" data-navbar-action="profile">Xem Profile</a>
                    <a href="#" id="viewSettingItem" class="user-dropdown-link" data-navbar-action="setting">Cài đặt</a>
                    <hr class="user-dropdown-divider">
                    <a href="#" class="user-dropdown-link user-dropdown-link-danger" data-navbar-action="logout">Đăng xuất</a>
                </div>
            </div>

            <div class="notification-icon has-dropdown" id="notificationBtn" role="button" tabindex="0" aria-label="Thông báo" aria-expanded="false">
                <img src="https://res.cloudinary.com/dilvws4q7/image/upload/v1778823673/th%C3%B4ng_b%C3%A1o-removebg-preview_a464hl.png" alt="Notification" width="22" height="22">
                <span id="notificationBadge" class="notification-badge hidden">0</span>

                <div id="notificationDropdown" class="notification-dropdown" aria-hidden="true">
                    <div class="notification-dropdown-head">
                        <strong>Thông báo</strong>
                        <button type="button" id="notificationMarkAllReadBtn">Đọc tất cả</button>
                    </div>

                    <div id="notificationList" class="notification-list">
                        <div class="notification-empty">Chưa có thông báo.</div>
                    </div>
                </div>
            </div>
        </div>

        <div id="nav-guest-zone" style="display: {{ !$isUserLoggedInPHP ? 'flex' : 'none' }}; align-items: center; gap: 12px;">
            <a href="{{ home_url('/login') }}" class="btn-nav-auth-custom btn-nav-login-custom" style="display: inline-flex; align-items: center; justify-content: center; background: transparent; color: #ffffff; border: 1px solid rgba(255, 255, 255, 0.6); padding: 6px 18px; border-radius: 20px; font-size: 13.5px; font-weight: 500; text-decoration: none; transition: all 0.2s ease; height: 34px;">Đăng nhập</a>
            <a href="{{ home_url('/register') }}" class="btn-nav-auth-custom btn-nav-register-custom" style="display: inline-flex; align-items: center; justify-content: center; background: transparent; color: #ffffff; border: 1px solid rgba(255, 255, 255, 0.6); padding: 6px 18px; border-radius: 20px; font-size: 13.5px; font-weight: 500; text-decoration: none; transition: all 0.2s ease; height: 34px;">Đăng ký</a>
        </div>
    </div>

</nav>

<div id="bulkPurchaseModal" class="bulk-purchase-modal" aria-hidden="true">
    <div class="bulk-purchase-dialog" role="dialog" aria-modal="true" aria-labelledby="bulkPurchaseTitle">
        <button type="button" class="bulk-purchase-close" id="bulkPurchaseCloseBtn" aria-label="Đóng">×</button>
        <p class="bulk-purchase-kicker">Thu mua số lượng lớn</p>
        <h2 id="bulkPurchaseTitle">Bạn muốn yêu cầu thu mua số lượng lớn?</h2>
        <p class="bulk-purchase-copy">
            Hệ thống sẽ tạo ticket support và gửi tin nhắn: “tôi muốn thu mua số lượng lớn, hãy hỗ trợ tôi”.
        </p>
        <div id="bulkPurchaseModalMessage" class="bulk-purchase-message"></div>
        <div class="bulk-purchase-actions">
            <button type="button" id="bulkPurchaseCancelBtn" class="bulk-purchase-cancel">Hủy</button>
            <button type="button" id="bulkPurchaseConfirmBtn" class="bulk-purchase-confirm">Chọn</button>
        </div>
    </div>
</div>

<script type="module" src="{{ get_theme_file_uri('/resources/scripts/components/wallet-balance.js') }}?v={{ filemtime(get_theme_file_path('/resources/scripts/components/wallet-balance.js')) }}"></script>
<script type="module" src="{{ get_theme_file_uri('/resources/scripts/components/revenue-nav.js') }}?v={{ filemtime(get_theme_file_path('/resources/scripts/components/revenue-nav.js')) }}"></script>
<script type="module" src="{{ get_theme_file_uri('/resources/scripts/components/navbar-bulk.js') }}?v={{ filemtime(get_theme_file_path('/resources/scripts/components/navbar-bulk.js')) }}"></script>
<script type="module" src="{{ get_theme_file_uri('/resources/scripts/components/navbar-notifications.js') }}?v={{ filemtime(get_theme_file_path('/resources/scripts/components/navbar-notifications.js')) }}"></script>