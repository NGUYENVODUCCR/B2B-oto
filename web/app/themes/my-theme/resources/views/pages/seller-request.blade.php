@push('styles')
<link rel="stylesheet" href="{{ get_theme_file_uri('/resources/styles/pages/seller-request.css') }}">
@endpush

<div class="seller-request-container py-5">
    <div class="seller-request-card">
        <div class="seller-request-header">
            <h1>Đăng ký tài khoản Seller</h1>
            <p>Vui lòng cung cấp đầy đủ thông tin pháp lý doanh nghiệp để mở tính năng đăng bán ô tô</p>
        </div>
        <div id="sellerPendingBox" class="seller-pending-box is-hidden">
            <div class="seller-pending-content">
                <div class="pending-icon">⏳</div>
                <h2>Bạn đã đăng ký Seller</h2>
                <p>
                    Hồ sơ của bạn đang được xét duyệt.
                    Vui lòng chờ quản trị viên xác minh.
                </p>
                <div class="pending-status"></div>
            </div>
        </div>
        <form id="sellerForm" enctype="multipart/form-data">
            @csrf

            <!-- PHÂN KHU 1 -->
            <div class="form-section-title">1. Thông tin người đại diện</div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Họ và tên người đại diện</label>
                    <input type="text" name="representative_name" placeholder="Ví dụ: Nguyễn Văn A" required>
                </div>
                <div class="form-group">
                    <label>Số Căn cước công dân (CCCD)</label>
                    <input type="text" name="citizen_id_number" placeholder="Nhập 12 số CCCD" required>
                </div>
            </div>

            <!-- PHÂN KHU 2 -->
            <div class="form-section-title">2. Thông tin công ty / Cửa hàng kinh doanh</div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Tên doanh nghiệp / Hộ kinh doanh</label>
                    <input type="text" name="company_name" placeholder="Ví dụ: Công ty TNHH Ô tô B2B" required>
                </div>
                <div class="form-group">
                    <label>Mã số thuế doanh nghiệp</label>
                    <input type="text" name="tax_code" placeholder="Nhập mã số thuế doanh nghiệp" required>
                </div>
                <div class="form-group">
                    <label>Email liên hệ công ty</label>
                    <input type="email" name="company_email" placeholder="email@congty.com" required>
                </div>
                <div class="form-group">
                    <label>Tên ngân hàng nhận tiền</label>
                    <input type="text" name="bank_name" placeholder="Ví dụ: Vietcombank, MB Bank, Techcombank..." required>
                </div>

                <div class="form-group">
                    <label>Số tài khoản nhận tiền</label>
                    <input type="text" name="bank_account" placeholder="Nhập số tài khoản của seller" required>
                </div>
                <div class="form-group full-width">
                    <label>Địa chỉ trụ sở chính</label>
                    <textarea name="address" placeholder="Nhập số nhà, tên đường, phường/xã, quận/huyện, tỉnh/thành phố..." required></textarea>
                </div>
            </div>

            <!-- PHÂN KHU 3 -->
            <div class="form-section-title">3. Hồ sơ đính kèm chứng thực (Bắt buộc)</div>
            <div class="file-upload-grid">
                <div class="file-wrapper">
                    <label>CCCD mặt trước</label>
                    <input type="file" name="citizen_front" accept="image/*" required>
                </div>
                <div class="file-wrapper">
                    <label>CCCD mặt sau</label>
                    <input type="file" name="citizen_back" accept="image/*" required>
                </div>
                <div class="file-wrapper">
                    <label>Giấy phép kinh doanh</label>
                    <input type="file" name="business_license" accept="image/*,.pdf" required>
                </div>
                <div class="file-wrapper">
                    <label>Giấy chứng nhận kiểm định</label>
                    <input type="file" name="inspection_certificate" accept="image/*,.pdf" required>
                </div>
                <div class="file-wrapper file-wrapper-full">
                    <label>Logo thương hiệu công ty (Tùy chọn)</label>
                    <input type="file" name="company_logo" accept="image/*">
                </div>
            </div>

            <div id="response-alert"></div>

            <button type="submit" id="submitBtn" class="submit-btn">
                Gửi hồ sơ đăng ký ngay
            </button>
        </form>
    </div>
</div>
