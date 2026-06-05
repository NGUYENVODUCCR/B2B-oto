@push('styles')
<link rel="stylesheet" href="{{ get_theme_file_uri('/resources/styles/pages/admin.css') }}">
@endpush

<div class="admin-seller-requests-page">

    <div class="admin-module-tabs">
        <button class="module-tab-btn active" data-module="seller">
             Quản lý yêu cầu người bán
        </button>
        <button class="module-tab-btn" data-module="product">
             Quản lý tin bán xe (Sản phẩm)
        </button>
        <button class="module-tab-btn" data-module="user">
             Quản lý người dùng
        </button>
        <button class="module-tab-btn" data-module="chat">
             Kênh Chat nội bộ (Support)
        </button>
        <button class="module-tab-btn" data-module="revenue" id="tab-module-revenue" style="display: none;">
             Doanh thu bán hàng đang xem
        </button>
    </div>

    <h1 id="admin-page-title">Quản lý đăng ký bán hàng</h1>

    <div class="admin-actions-bar">
        <button id="btn-show-list" class="admin-main-btn">
            Xem danh sách đơn đăng ký bán hàng
        </button>
        <button id="btn-create-user-trigger" class="admin-main-btn success-btn" style="display: none; background-color: #28a745; color: white; border: none; border-radius: 4px; margin-left: 10px;">
             Tạo tài khoản mới
        </button>
    </div>

    <div id="seller-filter-tabs-bar" class="filter-tabs-bar">
        <button class="filter-tab-btn active" data-module="seller" data-status="all">Tất cả đơn</button>
        <button class="filter-tab-btn" data-module="seller" data-status="pending">Đang chờ xác minh</button>
        <button class="filter-tab-btn" data-module="seller" data-status="verified">Đã xác minh</button>
        <button class="filter-tab-btn" data-module="seller" data-status="unverified">Không xác minh được</button>
    </div>

    <div id="product-filter-tabs-bar" class="filter-tabs-bar">
        <button class="filter-tab-btn active" data-module="product" data-status="all">Tất cả bài đăng</button>
        <button class="filter-tab-btn" data-module="product" data-status="deleted">Bài đăng đã xóa</button>
        <button class="filter-tab-btn" data-module="product" data-status="pending">Seller đã ẩn tin</button>
        <button class="filter-tab-btn" data-module="product" data-status="verified">Bài đăng hiển thị</button>
        <button class="filter-tab-btn" data-module="product" data-status="unverified">Bài đăng bị khóa</button>
    </div>

    <div id="user-filter-tabs-bar" class="filter-tabs-bar">
        <button class="filter-tab-btn active" data-module="user" data-status="all">Tất cả tài khoản</button>
        <button class="filter-tab-btn" data-module="user" data-status="administrator">Quản trị viên</button>
        <button class="filter-tab-btn" data-module="user" data-status="ROLE_SUPPORT">Nhân viên </button>
        <button class="filter-tab-btn" data-module="user" data-status="seller">Người bán </button>
        <button class="filter-tab-btn" data-module="user" data-status="customer">Khách hàng</button>
    </div>

    <div id="chat-tabs-bar" class="filter-tabs-bar">
        <span class="chat-bar-instruction">Chọn một nhân viên bên dưới để bắt đầu hội thoại nội bộ:</span>
    </div>

    <div id="request-list"></div>

    <div id="admin-revenue-zone" style="display: none;">
        <div class="revenue-page" style="margin-top: 20px;">
            <section class="revenue-hero">
                <div>
                    <p class="revenue-kicker">Báo cáo tài chính đối tác</p>
                    <h1 id="revenue-admin-heading">Thống kê doanh thu</h1>
                </div>
                <div class="revenue-period" id="revenueScopeLabel">Dữ liệu tài chính thời gian thực</div>
            </section>

            <section class="revenue-filter-panel">
                <form id="revenueFilterForm" class="revenue-filters">
                    <input type="hidden" id="revenueSellerIdFilter" name="seller_id" value="">
                    <label>Hãng xe: 
                        <select id="revenueBrandFilter" name="brand">
                            <option value="">Tất cả</option>
                        </select>
                    </label>
                    <label>Từ ngày: <input type="date" id="revenueDateFrom" name="date_from"></label>
                    <label>Tới ngày: <input type="date" id="revenueDateTo" name="date_to"></label>
                    <button type="submit">Tìm kiếm</button>
                </form>
            </section>

            <section class="revenue-stats-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin: 20px 0;">
                <div class="stat-card" style="padding: 15px; background: #f8fafc; border-radius: 6px; border: 1px solid #e2e8f0;">
                    <p style="font-size: 13px; color: #64748b; margin: 0;">Tổng giá trị giao dịch (GMV)</p>
                    <h3 id="revenueTotalAmount" style="font-size: 20px; margin: 5px 0 0 0; color: #0f172a;">0 VND</h3>
                </div>
                <div class="stat-card" style="padding: 15px; background: #f8fafc; border-radius: 6px; border: 1px solid #e2e8f0;">
                    <p style="font-size: 13px; color: #64748b; margin: 0;">Phí nền tảng thu hộ</p>
                    <h3 id="revenueCarsSold" style="font-size: 20px; margin: 5px 0 0 0; color: #dc2626;">0 VND</h3>
                </div>
                <div class="stat-card" style="padding: 15px; background: #f8fafc; border-radius: 6px; border: 1px solid #e2e8f0;">
                    <p style="font-size: 13px; color: #64748b; margin: 0;">Doanh thu đối tác thực nhận</p>
                    <h3 id="revenueCompletedOrders" style="font-size: 20px; margin: 5px 0 0 0; color: #16a34a;">0 VND</h3>
                </div>
            </section>

            <section class="revenue-chart-section" style="background: #ffffff; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h4 style="margin: 0; font-size: 15px; color: #334155;">Biểu đồ biến động thực nhận</h4>
                    <span id="revenueChartTotal" style="font-weight: 600; color: #2563eb;">0 VND</span>
                </div>
                <div id="revenueChart" style="height: 180px; width: 100%;"></div>
                <p id="revenueCommission" style="font-size: 12px; color: #94a3b8; margin: 5px 0 0 0; text-align: right;">0 đơn hàng</p>
            </section>

            <section class="revenue-orders-section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <h4 style="margin: 0; font-size: 15px; color: #334155;">Danh sách đơn hàng hoàn thành</h4>
                    <span id="revenueOrdersCount" style="font-size: 13px; background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 12px;">0 đơn</span>
                </div>
                <div id="revenueOrdersList" class="orders-list-wrapper">
                    </div>
            </section>
        </div>
    </div>
</div>