@push('styles')
<link rel="stylesheet" href="{{ get_theme_file_uri('/resources/styles/pages/payment.css') }}">
@endpush

@push('scripts')
<script type="module" src="{{ get_theme_file_uri('/resources/scripts/pages/payment.js') }}?v={{ filemtime(get_theme_file_path('/resources/scripts/pages/payment.js')) }}"></script>
@endpush

<div id="payment-page" class="payment-page">
  <div id="payment-box"></div>
</div>
