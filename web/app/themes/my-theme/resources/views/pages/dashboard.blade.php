@push('styles')
<link rel="stylesheet" href="{{ get_theme_file_uri('/resources/styles/pages/dashboard.css') }}?v={{ filemtime(get_theme_file_path('/resources/styles/pages/dashboard.css')) }}">
@endpush

@push('scripts')
<script>
    window.__B2B_DASHBOARD_BOOTED__ = true;
    window.__B2B_DASHBOARD_FORCE_BOOT__ = true;
</script>
<script type="module" src="{{ get_theme_file_uri('/resources/scripts/pages/dashboard.js') }}?v={{ filemtime(get_theme_file_path('/resources/scripts/pages/dashboard.js')) }}" charset="UTF-8"></script>
@endpush

<div class="marketplace">

    <section class="hero">
        <img class="hero-bg" src="https://images.unsplash.com/photo-1503376780353-7e6692767b70?auto=format&fit=crop&w=1800&q=85" alt="Showroom ô tô B2B">
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <div class="hero-top">
                <a href="{{ home_url('/seller-request') }}" id="registerSellerBtn" class="seller-btn">Đăng ký bán xe</a>
                <a href="{{ home_url('/seller') }}" id="createProductBtn" class="seller-btn hidden">Quản lý cửa hàng</a>
            </div>
            <p class="hero-kicker">B2B Auto Marketplace</p>
            <h1>SÀN GIAO DỊCH Ô TÔ<br>B2B MARKETPLACE</h1>
            <p class="hero-subtitle">Tìm nguồn xe, so sánh nhà bán và bắt đầu giao dịch trực tiếp trên cùng một hệ thống.</p>
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Tìm kiếm sản phẩm phù hợp">
            </div>
        </div>
    </section>

    <section class="dashboard-summary" aria-label="Tổng quan sàn giao dịch">
        <div class="dashboard-summary-card">
            <span>Xe đang bán</span>
            <strong id="dashboardProductCount">0</strong>
        </div>
        <div class="dashboard-summary-card">
            <span>Công ty tham gia</span>
            <strong id="dashboardCompanyCount">0</strong>
        </div>
        <div class="dashboard-summary-card">
            <span>Kết quả đang hiển thị</span>
            <strong id="dashboardFilteredCount">0</strong>
        </div>
    </section>

    <div class="filter-bar">
        <div class="filter-copy">
            <div class="hot-label">Kho xe nổi bật</div>
            <strong>Lọc nhanh theo nhu cầu mua xe</strong>
        </div>

        <div class="dashboard-toolbar-actions">
            <button type="button" id="filterToggleBtn" class="filter-trigger-btn" aria-expanded="false" aria-controls="filterPanel">Bộ lọc</button>
        </div>

        <div id="filterPanel" class="filter-dropdown-panel">

            <div class="filter-field-group">
                <label>Tên xe</label>
                <input type="text" id="filterName" class="filter-input-ctrl" placeholder="Nhập tên xe...">
            </div>

            <div class="filter-field-group">
                <label>Hãng xe</label>
                <select id="filterBrand" class="filter-input-ctrl">
                    <option value="">-- Tất cả hãng --</option>
                </select>
            </div>

            <div class="filter-field-group">
                <label>Màu sắc</label>
                <select id="filterColor" class="filter-input-ctrl">
                    <option value="">-- Tất cả màu --</option>
                </select>
            </div>

            <div class="filter-field-group">
                <label>Năm sản xuất</label>
                <select id="filterYears" class="filter-input-ctrl">
                    <option value="">-- Tất cả năm --</option>
                </select>
            </div>

            <div class="filter-field-group">
                <label>Giá từ (VND)</label>
                <input type="number" id="filterPriceMin" class="filter-input-ctrl" min="0" step="100000" placeholder="Ví dụ: 50000000">
            </div>

            <div class="filter-field-group">
                <label>Giá đến (VND)</label>
                <input type="number" id="filterPriceMax" class="filter-input-ctrl" min="0" step="100000" placeholder="Ví dụ: 500000000">
            </div>

            <div class="filter-field-group">
                <label>Công ty sở hữu</label>
                <select id="filterCompany" class="filter-input-ctrl">
                    <option value="">-- Tất cả công ty --</option>
                </select>
            </div>

        </div>
    </div>

    <div class="dashboard-section-head">
        <div>
            <p>Danh sách xe</p>
            <h2>Xe đang được chào bán</h2>
        </div>
    </div>

    <div id="productGrid" class="product-grid"></div>

    <div id="loading" class="loading hidden">Đang tải sản phẩm...</div>

</div>
