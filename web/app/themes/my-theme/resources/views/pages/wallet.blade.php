@push('styles')
<link rel="stylesheet" href="{{ get_theme_file_uri('/resources/styles/pages/wallet.css') }}?v={{ filemtime(get_theme_file_path('/resources/styles/pages/wallet.css')) }}">
@endpush

@push('scripts')
<script type="module" src="{{ get_theme_file_uri('/resources/scripts/pages/wallet.js') }}?v={{ filemtime(get_theme_file_path('/resources/scripts/pages/wallet.js')) }}"></script>
@endpush

<div class="wallet-page">
    <section class="wallet-header">
        <div class="wallet-header-copy">
            <p class="wallet-kicker">Ví giao dịch</p>
            <h1>Nạp tiền, rút tiền và thanh toán escrow</h1>
            <p>Quản lý số dư dùng để thanh toán đơn hàng. Tiền nạp được ghi nhận qua mã QR ngân hàng, còn tiền escrow được giải ngân sau khi đơn hoàn tất.</p>
        </div>

        <div class="wallet-balance-card">
            <span>Số dư khả dụng</span>
            <strong id="walletBalance">0 VND</strong>
            <small>Cập nhật theo thời gian thực khi có giao dịch mới</small>
        </div>
    </section>

    <section class="wallet-action-grid">
        <div class="wallet-panel wallet-deposit-panel">
            <div class="wallet-panel-head">
                <div>
                    <p>Nạp tiền</p>
                    <h2>Tạo mã QR chuyển khoản</h2>
                </div>
                <span class="wallet-panel-badge">Casso QR</span>
            </div>

            <div class="bank-grid" id="walletBankGrid">
                <button type="button" class="bank-logo is-active" data-bank="vietcombank" aria-label="Vietcombank">
                    <img src="https://api.vietqr.io/img/VCB.png" alt="Vietcombank" loading="lazy" decoding="async">
                    <small>Vietcombank</small>
                </button>
                <button type="button" class="bank-logo" data-bank="MB Bank" aria-label="MB Bank">
                    <img src="https://api.vietqr.io/img/MB.png" alt="MB Bank" loading="lazy" decoding="async">
                    <small>MB Bank</small>
                </button>
                <button type="button" class="bank-logo" data-bank="bidv" aria-label="BIDV">
                    <img src="https://api.vietqr.io/img/BIDV.png" alt="BIDV" loading="lazy" decoding="async">
                    <small>BIDV</small>
                </button>
                <button type="button" class="bank-logo" data-bank="acb" aria-label="ACB">
                    <img src="https://api.vietqr.io/img/ACB.png" alt="ACB" loading="lazy" decoding="async">
                    <small>ACB</small>
                </button>
            </div>

            <form id="walletDepositForm" class="wallet-form">
                <input type="hidden" name="bank" id="walletDepositBank" value="vietcombank">

                <label>
                    Số tiền nạp
                    <input type="number" name="amount" id="walletDepositAmount" min="10000" step="10000" placeholder="Ví dụ: 5000000" required>
                </label>

                <div class="wallet-quick-amounts" aria-label="Chọn nhanh số tiền">
                    <button type="button" data-wallet-amount="1000000">1 triệu</button>
                    <button type="button" data-wallet-amount="5000000">5 triệu</button>
                    <button type="button" data-wallet-amount="10000000">10 triệu</button>
                    <button type="button" data-wallet-amount="50000000">50 triệu</button>
                </div>

                <button type="submit" class="wallet-primary-btn">Tạo QR nạp tiền</button>
            </form>

            <div id="walletDepositQr" class="wallet-deposit-qr" hidden></div>
        </div>

        <div class="wallet-panel wallet-withdraw-panel">
            <div class="wallet-panel-head">
                <div>
                    <p>Rút tiền</p>
                    <h2>Chuyển tiền về tài khoản ngân hàng</h2>
                </div>
            </div>

            <form id="walletWithdrawForm" class="wallet-form">
                <label>
                    Ngân hàng nhận
                    <select name="bank" required>
                        <option value="vietcombank">Vietcombank</option>
                        <option value="MB Bank">MB Bank</option>
                        <option value="mbbank">MB Bank</option>
                        <option value="bidv">BIDV</option>
                        <option value="acb">ACB</option>
                    </select>
                </label>
                <label>
                    Số tài khoản
                    <input type="text" name="account_number" placeholder="Nhập số tài khoản" required>
                </label>
                <label>
                    Số tiền rút
                    <input type="number" name="amount" min="10000" step="10000" placeholder="Ví dụ: 1000000" required>
                </label>
                <button type="submit" class="wallet-secondary-btn">Rút tiền</button>
            </form>
        </div>
    </section>

    <section class="wallet-panel wallet-history-panel">
        <div class="wallet-panel-title">
            <div>
                <p>Lịch sử ví</p>
                <h2>Giao dịch gần đây</h2>
            </div>
            <button type="button" id="walletRefreshBtn">Làm mới</button>
        </div>
        <div id="walletMessage" class="wallet-message"></div>
        <div id="walletTransactions" class="wallet-transactions">
            <div class="wallet-empty">Đang tải lịch sử...</div>
        </div>
    </section>
</div>
