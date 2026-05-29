@push('scripts')
<script type="module" src="{{ get_theme_file_uri('/resources/scripts/pages/rfq-list.js') }}?v={{ filemtime(get_theme_file_path('/resources/scripts/pages/rfq-list.js')) }}"></script>
@endpush

<p>Đang chuyển sang trang tin nhắn... <a href="{{ home_url('/chat') }}">Mở ngay</a></p>
