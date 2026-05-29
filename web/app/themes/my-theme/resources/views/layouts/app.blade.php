@php
  $slug = get_post_field('post_name', get_post());
  $authPages = ['login', 'register', 'verify-otp', 'forgot-password', 'reset-password'];
  $isAuthPage = in_array($slug, $authPages, true);
@endphp

<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>{{ $title ?? 'B2B Marketplace' }}</title>

  <?php wp_head(); ?>

  @stack('styles')

  <link
    rel="stylesheet"
    href="{{ get_theme_file_uri('/resources/styles/pages/ui-refresh.css') }}?v={{ filemtime(get_theme_file_path('/resources/styles/pages/ui-refresh.css')) }}"
  >
</head>

<body <?php body_class(); ?> data-page="{{ esc_attr($slug) }}" data-has-navbar="{{ $isAuthPage ? '0' : '1' }}">
  <?php wp_body_open(); ?>

  @if(!$isAuthPage)
    @include('components.navbar')
  @endif

 {{-- Floating AI Box --}}
  @if(!$isAuthPage)
    @include('components.ai-chat')
  @endif
  
  <main class="container">
    @yield('content')
  </main>

  @if(!$isAuthPage)
    @include('components.profile-modal')
  @endif

  @stack('scripts')

  <?php wp_footer(); ?>
</body>

</html>
