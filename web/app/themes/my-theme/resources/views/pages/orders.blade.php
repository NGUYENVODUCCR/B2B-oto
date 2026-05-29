@push('styles')
<link rel="stylesheet" href="{{ get_theme_file_uri('/resources/styles/pages/orders.css') }}?v={{ filemtime(get_theme_file_path('/resources/styles/pages/orders.css')) }}">
@endpush

<div class="orders-page">
    <h1>Theo dõi đơn hàng</h1>
    <p class="orders-subtitle">Bạn có thể xem toàn bộ đơn hàng. Tìm kiếm theo RFQ, Bulk, Order, Contract hoặc tên công ty.</p>

    <div class="orders-toolbar">
        <input
            id="ordersSearchInput"
            class="orders-search-input"
            type="text"
            placeholder="VD: RFQ #40, Bulk #12, Order #21, tên công ty..."
        >
        <button id="ordersSearchBtn" type="button" class="orders-search-btn">Tìm kiếm</button>
        <button id="ordersSearchResetBtn" type="button" class="orders-search-reset-btn">Xóa</button>
    </div>

    <div id="ordersSearchMeta" class="orders-search-meta"></div>

    <div id="ordersTrackingList" class="orders-tracking-list">
        Đang tải đơn hàng...
    </div>
</div>

<script type="module" src="{{ get_theme_file_uri('/resources/scripts/pages/orders.js') }}?v={{ filemtime(get_theme_file_path('/resources/scripts/pages/orders.js')) }}"></script>
