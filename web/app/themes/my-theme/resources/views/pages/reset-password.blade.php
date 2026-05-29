@push('styles')
<link rel="stylesheet" href="{{ get_theme_file_uri('/resources/styles/pages/reset-password.css') }}">
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


  .auth-container {
    padding: 0 !important;
    background: none !important;
    width: 100% !important;
    max-width: 420px !important;
    margin: auto !important;
  }
</style>
@endpush

{{-- Thẻ div cha thần thánh ép full màn hình --}}
<div class="auth-full-screen-wrapper">

  <div class="auth-container">

    <div class="auth-card">

      <h1>Đặt lại mật khẩu</h1>

      <form id="reset-form">

        <input
          type="email"
          name="email"
          placeholder="Email"
          required
        >

        <input
          type="text"
          name="otp"
          placeholder="OTP"
          required
        >

        <input
          type="password"
          name="password"
          placeholder="Mật khẩu mới"
          required
        >

        <input
          type="password"
          name="confirm_password"
          placeholder="Xác nhận mật khẩu"
          required
        >

        <button type="submit">
          Đổi mật khẩu
        </button>

      </form>

      <div id="reset-message"></div>

      <div class="auth-links">

        <a href="{{ home_url('/login') }}">
          Quay lại đăng nhập
        </a>

      </div>

    </div>

  </div>

</div>