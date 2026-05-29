@push('scripts')
<script type="module" src="{{ get_theme_file_uri('/resources/scripts/pages/product-list.js') }}?v={{ filemtime(get_theme_file_path('/resources/scripts/pages/product-list.js')) }}"></script>
@endpush

<p>Đang chuyển sang trang seller... <a href="{{ home_url('/seller') }}">Mở ngay</a></p>
