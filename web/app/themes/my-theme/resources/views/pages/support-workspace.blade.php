@push('styles')
<link rel="stylesheet" href="{{ get_theme_file_uri('/resources/styles/pages/support.css') }}?v={{ filemtime(get_theme_file_path('/resources/styles/pages/support.css')) }}">
@endpush

@push('scripts')
<script type="module" src="{{ get_theme_file_uri('/resources/scripts/pages/support.js') }}?v={{ filemtime(get_theme_file_path('/resources/scripts/pages/support.js')) }}"></script>
@endpush

<div
    id="supportPage"
    class="support-page"
    data-default-view="workspace"
>
    @include('pages.support.workspace')
</div>
