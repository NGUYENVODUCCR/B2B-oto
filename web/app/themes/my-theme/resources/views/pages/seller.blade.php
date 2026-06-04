@push('styles')
<link rel="stylesheet" href="{{ get_theme_file_uri('/resources/styles/pages/seller.css') }}">
@endpush

<div class="container py-5">

    <div class="seller-header">
        <h2 class="seller-title">Trang bán hàng</h2>
        <button id="open-product-form" class="btn-create-product">Đăng sản phẩm mới</button>
    </div>

    <div class="seller-stats-box" hidden>
        <div class="seller-stat-card">
            <strong>Doanh thu bán hàng</strong>
            <p id="sellerRevenueAmount">0 VNĐ</p>
        </div>

        <div class="seller-stat-card">
            <strong>Đơn đã hoàn tất</strong>
            <p id="sellerCompletedOrders">0</p>
        </div>
    </div>

    <div class="seller-chat-alert">
        <div>
            <strong>Tin nhắn giao dịch</strong>
            <p>Phản hồi buyer, gửi báo giá và theo dõi hợp đồng/order trong một hộp chat riêng.</p>
        </div>
        <div class="seller-chat-actions">
            <a href="{{ home_url('/support/') }}?view=seller-channel&force_public=1" class="seller-chat-link seller-channel-link">
                Kênh chat chung bán hàng
            </a>
            <a href="{{ home_url('/chat') }}" class="seller-chat-link">
                Mở chat
                <span id="sellerChatBadge" class="seller-chat-badge hidden">0</span>
            </a>
        </div>
    </div>

    <div id="product-form-wrapper" class="product-form-card d-none">
        <h4 id="form-title">Đăng sản phẩm</h4>
        <form id="product-create-form" enctype="multipart/form-data">
            <input type="hidden" id="product-id" name="id" value="">

            <label>Tên sản phẩm <span class="required-mark">*</span></label>
            <input type="text" id="name" name="name" required>

            <label>Hãng xe</label>
            <input type="text" id="brand" name="brand" placeholder="Ví dụ: Toyota, Hyundai...">

            <label>Màu sắc</label>
            <input type="text" id="color" name="color" placeholder="Ví dụ: Trắng, Đen...">

            <label>Mô tả</label>
            <textarea id="description" name="description" rows="4"></textarea>

            <label>Giá từ <span class="required-mark">*</span></label>
            <input type="number" id="price_from" name="price_from" required>

            <label>Năm sản xuất</label>
            <input type="number" id="years" name="years">

            <label>Số lượng</label>
            <input type="number" id="quantity" name="quantity">

            <label>Hình ảnh</label>
            <input type="file" id="images" name="images" multiple>

            <div class="product-form-actions">
                <button type="submit" id="btn-publish" class="btn-submit-product">
                    Đăng sản phẩm
                </button>
                <button type="button" id="btn-save-draft" class="btn-submit-product btn-save-draft">
                    Lưu bản nháp
                </button>
            </div>
        </form>
        <div id="product-message"></div>
    </div>

    <hr class="seller-divider">

    <section class="seller-history-entry">
        <div class="seller-history-entry-copy">
            <h3>Lịch sử bán hàng</h3>
            <p>Xem danh sách đơn đã bán, trạng thái thanh toán và tiến độ xử lý theo từng đơn.</p>
        </div>
        <a href="{{ home_url('/orders/') }}" class="seller-history-open-btn">Mở trang lịch sử bán hàng</a>
    </section>

    <hr class="seller-divider">

    <div>
        <h3 class="mb-4">Sản phẩm của tôi</h3>
        <div id="seller-product-list" class="seller-product-grid"></div>
    </div>

</div>
