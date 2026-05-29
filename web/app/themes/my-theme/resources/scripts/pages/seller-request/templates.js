function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

export function sellerPendingTemplate(message) {
    return `
        <div class="seller-pending-box">
            <div class="seller-pending-content">
                <div class="pending-icon">⏳</div>
                <h2>Đăng ký Seller thành công</h2>
                <p>
                    Hồ sơ doanh nghiệp của bạn đã được gửi lên hệ thống.
                    Quản trị viên sẽ kiểm tra và xác minh trong thời gian sớm nhất.
                </p>
                <div class="pending-status">
                    ${escapeHtml(message)}
                </div>
            </div>
        </div>
    `;
}
