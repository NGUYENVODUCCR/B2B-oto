@push('styles')
<link rel="stylesheet" href="{{ get_theme_file_uri('/resources/styles/pages/revenue.css') }}?v={{ filemtime(get_theme_file_path('/resources/styles/pages/revenue.css')) }}">
@endpush

@push('scripts')
<script type="module" src="{{ get_theme_file_uri('/resources/scripts/pages/revenue.js') }}?v={{ filemtime(get_theme_file_path('/resources/scripts/pages/revenue.js')) }}"></script>
@endpush

<div class="revenue-page">
  <section class="revenue-hero">
    <div>
      <p class="revenue-kicker">Báo cáo tài chính</p>
      <h1>Thống kê doanh thu</h1>
    </div>
    <div class="revenue-period" id="revenueScopeLabel">Đang tải dữ liệu</div>
  </section>

  <section class="revenue-filter-panel">
    <div class="revenue-section-title">Filter</div>
    <form id="revenueFilterForm" class="revenue-filters">
      <label>
        Hãng xe
        <select id="revenueBrandFilter" name="brand">
          <option value="">Tất cả</option>
        </select>
      </label>

      <label>
        Từ ngày
        <input type="date" id="revenueDateFrom" name="date_from">
      </label>

      <label>
        Tới ngày
        <input type="date" id="revenueDateTo" name="date_to">
      </label>

      <button type="submit">Tìm kiếm</button>
    </form>
  </section>

  <section class="revenue-stat-grid">
    <article class="revenue-stat-card">
      <span class="revenue-stat-icon revenue-stat-blue"></span>
      <p id="revenueTotalLabel">Doanh thu</p>
      <strong id="revenueTotalAmount">0 VND</strong>
    </article>
    <article class="revenue-stat-card">
      <span class="revenue-stat-icon revenue-stat-green"></span>
      <p id="revenueSecondLabel">Xe đã bán</p>
      <strong id="revenueCarsSold">0 xe</strong>
    </article>
    <article class="revenue-stat-card">
      <span class="revenue-stat-icon revenue-stat-orange"></span>
      <p id="revenueThirdLabel">Đơn thành công</p>
      <strong id="revenueCompletedOrders">0 đơn</strong>
    </article>
    <article class="revenue-stat-card">
      <span class="revenue-stat-icon revenue-stat-rose"></span>
      <p id="revenueFourthLabel">Hoa hồng nền tảng</p>
      <strong id="revenueCommission">0 VND</strong>
    </article>
  </section>

  <section class="revenue-chart-panel">
    <div class="revenue-panel-header">
      <div>
        <div class="revenue-section-title" id="revenueChartTitle">Biểu đồ doanh thu</div>
        <div class="revenue-tabs" aria-label="Khoảng thời gian">
          <button type="button" class="is-active" data-revenue-range="30">30 ngày</button>
          <button type="button" data-revenue-range="90">3 tháng</button>
          <button type="button" data-revenue-range="365">Tùy chỉnh</button>
        </div>
      </div>
      <strong id="revenueChartTotal">0 VND</strong>
    </div>
    <div id="revenueChart" class="revenue-chart"></div>
  </section>

  <section class="revenue-orders-panel">
    <div class="revenue-panel-header">
      <div class="revenue-section-title">Danh sách đơn hàng thành công</div>
      <span id="revenueOrdersCount">0 đơn</span>
    </div>
    <div id="revenueOrdersList" class="revenue-orders-list">
      <div class="revenue-empty">Đang tải doanh thu...</div>
    </div>
  </section>
</div>
