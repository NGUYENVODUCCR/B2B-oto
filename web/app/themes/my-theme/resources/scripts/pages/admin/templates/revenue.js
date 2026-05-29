export function adminRevenueMainTemplate() {
  return `
    <div class="revenue-page" style="margin-top: 20px;">
      <section class="revenue-filter-panel" style="background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 20px;">
        <form id="revenueFilterForm" class="revenue-filters" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
          <input type="hidden" id="revenueSellerIdFilter" value="">
          <label style="display:flex; flex-direction:column; gap:5px; font-size:13px; font-weight:600;">
            Hãng xe
            <select id="revenueBrandFilter" style="padding:6px 12px; border:1px solid #cbd5e1; border-radius:4px;"><option value="">Tất cả</option></select>
          </label>
          <label style="display:flex; flex-direction:column; gap:5px; font-size:13px; font-weight:600;">
            Từ ngày
            <input type="date" id="revenueDateFrom" style="padding:5px 12px; border:1px solid #cbd5e1; border-radius:4px;">
          </label>
          <label style="display:flex; flex-direction:column; gap:5px; font-size:13px; font-weight:600;">
            Tới ngày
            <input type="date" id="revenueDateTo" style="padding:5px 12px; border:1px solid #cbd5e1; border-radius:4px;">
          </label>
          <button type="submit" style="padding: 7px 16px; background: #2563eb; color: #fff; border: none; border-radius: 4px; font-weight: 600; cursor: pointer;">Tìm kiếm</button>
        </form>
      </section>

      <section class="revenue-stat-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 20px;">
        <article class="revenue-stat-card" style="background:#fff; padding:20px; border-radius:8px; border:1px solid #e2e8f0;">
          <p id="revenueTotalLabel" style="margin:0; color:#64748b; font-size:14px;">Doanh thu gộp</p>
          <strong id="revenueTotalAmount" style="font-size:20px; color:#1e293b;">0 VND</strong>
        </article>
        <article class="revenue-stat-card" style="background:#fff; padding:20px; border-radius:8px; border:1px solid #e2e8f0;">
          <p id="revenueSecondLabel" style="margin:0; color:#64748b; font-size:14px;">Phí sàn đã trừ</p>
          <strong id="revenueCarsSold" style="font-size:20px; color:#1e293b;">0 VND</strong>
        </article>
        <article class="revenue-stat-card" style="background:#fff; padding:20px; border-radius:8px; border:1px solid #e2e8f0;">
          <p id="revenueThirdLabel" style="margin:0; color:#64748b; font-size:14px;">Thực nhận của Seller</p>
          <strong id="revenueCompletedOrders" style="font-size:20px; color:#1e293b;">0 VND</strong>
        </article>
        <article class="revenue-stat-card" style="background:#fff; padding:20px; border-radius:8px; border:1px solid #e2e8f0;">
          <p id="revenueFourthLabel" style="margin:0; color:#64748b; font-size:14px;">Số lượng giao dịch</p>
          <strong id="revenueCommission" style="font-size:16px; color:#1e293b;">0 đơn</strong>
        </article>
      </section>

      <section class="revenue-chart-panel" style="background:#fff; padding:20px; border-radius:8px; border:1px solid #e2e8f0; margin-bottom:20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
          <strong id="revenueChartTitle" style="font-size:16px;">Biểu đồ thực nhận</strong>
          <span id="revenueChartTotal" style="font-size:18px; color:#2563eb; font-weight:700;">0 VND</span>
        </div>
        <div id="revenueChart" style="min-height:200px; display:flex; align-items:center; justify-content:center;"></div>
      </section>

      <section class="revenue-orders-panel" style="background:#fff; padding:20px; border-radius:8px; border:1px solid #e2e8f0;">
        <div style="display:flex; justify-content:space-between; margin-bottom:15px; border-bottom:1px solid #f1f5f9; padding-bottom:10px;">
          <span style="font-weight:700;">Danh sách đơn hàng thành công</span>
          <span id="revenueOrdersCount" style="background:#f1f5f9; padding:2px 8px; border-radius:12px; font-size:12px;">0 đơn</span>
        </div>
        <div id="revenueOrdersList" style="display:flex; flex-direction:column; gap:12px;">
          <div class="revenue-empty" style="color:#94a3b8; text-align:center; padding:30px 0;">Đang tải dữ liệu...</div>
        </div>
      </section>
    </div>
  `;
}


export function adminRevenueOrderCardTemplate(order) {
  const gmv = Number(order.gross_release_amount || order.paid_amount || order.total_amount || 0);
  const platformFee = Number(order.platform_fee_amount || 0);
  const sellerPayout = Number(order.seller_payout_amount || Math.max(0, gmv - platformFee));
  const fallbackImg = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="60" height="40" viewBox="0 0 60 40"><rect width="60" height="40" fill="%23e8edf4"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-size="8" fill="%23718499">No img</text></svg>';

  const moneyFmt = (v) => `${Number(v).toLocaleString('vi-VN')} đ`;

  return `
    <article class="revenue-order-card" style="display:flex; gap:15px; background:#f8fafc; padding:12px; border-radius:6px; border:1px solid #f1f5f9; align-items:center;">
      <img src="${order.image_url || fallbackImg}" style="width:70px; height:50px; object-fit:cover; border-radius:4px; background:#e2e8f0;">
      <div style="flex:1; font-size:13px; color:#334155;">
        <div style="font-weight:700; color:#1e293b; font-size:14px; margin-bottom:2px;">#DH${String(order.id).padStart(4, '0')} - ${order.product_name || 'Đơn hàng'}</div>
        <div>Ngày hoàn thành: ${String(order.revenue_date || order.created_at || '').split(' ')[0]}</div>
        <div style="color:#64748b; margin-top:2px;">Tổng giá trị: ${moneyFmt(gmv)} | Phí sàn: ${moneyFmt(platformFee)}</div>
      </div>
      <div style="text-align:right; font-weight:700; color:#16a34a; font-size:15px;">
        + ${moneyFmt(sellerPayout)}
      </div>
    </article>
  `;
}
