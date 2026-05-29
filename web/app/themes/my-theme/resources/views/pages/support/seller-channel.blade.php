<section id="supportSellerChannelPage" class="support-seller-channel-page" @if(empty($sellerChannelVisible)) hidden @endif>
    <section class="support-header">
        <div>
            <p>Seller Broadcast Channel</p>
            <h1>Kênh liên lạc chung với Support</h1>
        </div>
        <div class="support-header-actions">
            <a
                id="supportBackToHelpBtn"
                class="support-header-link-btn"
                href="{{ home_url('/seller/') }}"
                hidden
            >
                Quay về trang Seller
            </a>
        </div>
    </section>

    <section class="support-layout support-admin-layout">
        <section class="support-panel support-list-panel seller-channel-panel">
            <div class="support-panel-title">
                <h2>Thông báo thu mua số lượng lớn và phản hồi nhanh</h2>
            </div>
            <div class="seller-channel-shell" data-seller-channel-shell>
                <div class="seller-channel-messages" data-seller-channel-messages>
                    <div class="support-empty">Đang tải kênh chung seller...</div>
                </div>
                <form class="seller-channel-form" data-seller-channel-form>
                    <textarea name="message" rows="3" placeholder="Nhập phản hồi nhanh cho Support..." required></textarea>
                    <button type="submit">Gửi tin nhắn</button>
                </form>
            </div>
        </section>
    </section>
</section>
