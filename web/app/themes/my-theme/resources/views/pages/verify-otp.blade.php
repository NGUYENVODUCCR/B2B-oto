@push('styles')
<link rel="stylesheet" href="{{ get_theme_file_uri('/resources/styles/pages/verify-otp.css') }}">
<style>
 
  .auth-full-screen-wrapper {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;

    background: linear-gradient(rgba(15, 23, 42, 0.65), rgba(15, 23, 42, 0.9)), 
                url('https://images.unsplash.com/photo-1617788138017-80ad40651399?q=80&w=1920&auto=format&fit=crop') no-repeat center center !important;
    background-size: cover !important;
    z-index: 99999 !important;
    padding: 20px !important;
  }


  .auth-page {
    padding: 0 !important;
    background: none !important;
    width: 100% !important;
    max-width: 420px !important;
    margin: auto !important;
  }
</style>
@endpush

{{-- Thẻ div cha thần thánh ép tràn màn hình --}}
<div class="auth-full-screen-wrapper">

  <div class="auth-page">

    <div class="auth-card">

      <h1>Xác thực OTP</h1>

      <p class="auth-subtitle">
        Vui lòng nhập mã OTP được gửi tới số điện thoại của bạn
      </p>

      <form id="verify-form" class="auth-form">

        <input
          type="text"
          name="phone"
          value="{{ $_GET['phone'] ?? '' }}"
          placeholder="Số điện thoại"
        >

        <input
          type="text"
          name="otp"
          placeholder="Nhập mã OTP"
        >

        <button type="submit">
          Xác thực
        </button>

      </form>

      <div id="verify-message"></div>

      <div class="auth-footer">
        Quay lại
        <a href="{{ home_url('/login') }}">
          Đăng nhập
        </a>
      </div>

    </div>

  </div>

</div>