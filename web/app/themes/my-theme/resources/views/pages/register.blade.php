@push('styles')
<link rel="stylesheet" href="{{ get_theme_file_uri('/resources/styles/pages/register.css') }}">
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
    overflow-y: auto !important;
    padding: 30px 15px !important;
  }


  .auth-container {
    padding: 0 !important;
    background: none !important;
    width: 100% !important;
    max-width: 480px !important; 
    margin: auto !important;
  }
</style>
@endpush

{{-- Thẻ bọc ngoài cùng ép tràn màn hình 100vw x 100vh --}}
<div class="auth-full-screen-wrapper">

  <div class="auth-container">

    <div class="auth-card">

      <h1>Đăng ký tài khoản</h1>

      <p class="auth-subtitle">
        Tạo tài khoản B2B Marketplace
      </p>

      <form id="register-form">

        <input
          type="text"
          name="name"
          placeholder="Họ và tên"
          required
        >

        <input
          type="email"
          name="email"
          placeholder="Email"
          required
        >

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

        <input
          type="password"
          name="confirm_password"
          placeholder="Nhập lại mật khẩu"
          required
        >

        <input
          type="text"
          name="company_name"
          placeholder="Tên công ty"
          required
        >

        <input
          type="text"
          name="tax_code"
          placeholder="Mã số thuế"
        >

        <textarea
          name="address"
          placeholder="Địa chỉ"
          required
        ></textarea>

        <button type="submit">
          Đăng ký
        </button>

      </form>

      <div id="register-message"></div>

      <p class="auth-link">
        Đã có tài khoản?
        <a href="{{ home_url('/login') }}">
          Đăng nhập
        </a>
      </p>

    </div>

  </div>

</div>