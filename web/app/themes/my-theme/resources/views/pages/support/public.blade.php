<section id="supportPublicPage" class="support-public-page" @if(!empty($publicHidden)) hidden @endif>
    <div class="support-header">
        <div>
            <p>Trung tâm hỗ trợ</p>
            <h1>Gửi yêu cầu hỗ trợ giao dịch</h1>
        </div>
    </div>

    <section class="support-layout support-public-layout">
        <form id="publicSupportTicketForm" data-support-create-form data-message-id="publicSupportMessage" class="support-panel support-form">
            <h2>Tạo yêu cầu hỗ trợ</h2>

            <label>
                Loại yêu cầu
                <select name="type">
                    <option value="payment">Thanh toán / escrow</option>
                    <option value="contract">Hợp đồng</option>
                    <option value="dispute">Tranh chấp giao dịch</option>
                    <option value="bulk_purchase">Thu mua số lượng lớn</option>
                    <option value="other">Khác</option>
                </select>
            </label>

            <label>
                Chọn nhân sự support
                <select name="support_user_id" data-support-agent-select>
                    <option value="">Tự động phân công support</option>
                </select>
            </label>

            <label>
                Mã RFQ hoặc Bulk của bạn
                <input type="text" name="rfq_or_bulk" placeholder="VD: RFQ #42 hoặc Bulk #19">
            </label>

            <label>
                Nội dung
                <textarea name="message" rows="6" placeholder="Mô tả vấn đề bạn cần hỗ trợ" required></textarea>
            </label>

            <button type="submit">Gửi hỗ trợ</button>
            <div id="publicSupportMessage" class="support-message"></div>
        </form>

        <section class="support-panel support-help-copy">
            <p>Hỗ trợ thường</p>
            <h2>Trang này dành cho buyer và seller</h2>
            <div>
                Bạn có thể gửi yêu cầu hỗ trợ giao dịch, thanh toán hoặc hợp đồng. Luồng thu mua số lượng lớn sẽ được xử lý trong trang Tin nhắn.
            </div>
        </section>
    </section>
</section>
