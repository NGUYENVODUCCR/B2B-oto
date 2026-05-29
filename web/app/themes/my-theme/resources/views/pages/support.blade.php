@push('styles')
<link rel="stylesheet" href="{{ get_theme_file_uri('/resources/styles/pages/support.css') }}?v={{ filemtime(get_theme_file_path('/resources/styles/pages/support.css')) }}">
@endpush

@push('scripts')
<script type="module" src="{{ get_theme_file_uri('/resources/scripts/pages/support.js') }}?v={{ filemtime(get_theme_file_path('/resources/scripts/pages/support.js')) }}"></script>
@endpush

@php
    $supportRequestedView = strtolower((string) ($_GET['view'] ?? 'help'));
    $supportForcePublic = (string) ($_GET['force_public'] ?? '') === '1';
    $supportInitialView = $supportRequestedView === 'seller-channel' ? 'seller-channel' : 'help';
    $showSellerChannelOnFirstPaint = $supportRequestedView === 'seller-channel' && $supportForcePublic;
@endphp

<div
    id="supportPage"
    class="support-page"
    data-default-view="{{ $supportInitialView }}"
>
    @include('pages.support.public', ['publicHidden' => $showSellerChannelOnFirstPaint])
    @include('pages.support.seller-channel', ['sellerChannelVisible' => $showSellerChannelOnFirstPaint])
</div>
