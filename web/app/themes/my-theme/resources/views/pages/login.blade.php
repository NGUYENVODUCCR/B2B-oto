@push('styles')
<link rel="stylesheet"
      href="{{ get_theme_file_uri('/resources/styles/pages/login.css') }}">
<style>

  .auth-full-screen-wrapper {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    
   
    background: linear-gradient(rgba(15, 23, 42, 0.65), rgba(15, 23, 42, 0.9)), 
                url('https://images.unsplash.com/photo-1617788138017-80ad40651399?q=80&w=1920&auto=format&fit=crop') no-repeat center center;
    background-size: cover;
    z-index: 9999; 
  }

  
  .auth-container {
    padding: 0;
    background: none !important;
    width: 100%;
    max-width: 420px; 
  }
</style>
@endpush

{{-- Thẻ div cha mới để bao phủ toàn bộ màn hình --}}
<div class="auth-full-screen-wrapper">
  
  <div class="auth-container">

    <div class="auth-card">

      <h1>Đăng nhập</h1>

      <p class="auth-subtitle">
        Hệ thống giao dịch Ô tô B2B
      </p>

      <form id="login-form">

        <input
          type="text"
          name="phone"
          placeholder="Số điện thoại"
          required
        >

        <input
          type="password"
          name="password"
          placeholder="Mật khẩu"
          required
        >

        <button type="submit">
          Đăng nhập
        </button>

      </form>

      <div id="login-message"></div>

      <div class="auth-links">

        <a href="{{ home_url('/forgot-password') }}">
          Quên mật khẩu?
        </a>

        <a href="{{ home_url('/register') }}">
          Đăng ký tài khoản
        </a>

      </div>

    </div>

  </div>

</div>