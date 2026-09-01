<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">

    <title>@yield('title', 'Yönetim Paneli') | Admin</title>

    @php
        $adminFavicon = \App\Models\Setting::getValue('site_favicon');
    @endphp
    @if($adminFavicon)
    <link rel="icon" href="{{ upload_url($adminFavicon) }}">
    <link rel="apple-touch-icon" href="{{ upload_url($adminFavicon) }}">
    @else
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">
    @endif

    {{-- CSS (Self-hosted) --}}
    <link href="{{ asset('assets/vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendor/fontawesome/css/all.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendor/inter/inter.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendor/dm-serif-display/dm-serif-display.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendor/dropzone/dropzone.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendor/aos/aos.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendor/jquery-validation-engine/css/validationEngine.jquery.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendor/select2/css/select2.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendor/select2/css/select2-bootstrap-5-theme.min.css') }}" rel="stylesheet">
    <link href="{{ versioned_asset('assets/admin/css/styles.css') }}" rel="stylesheet">

    @stack('styles')
</head>
<body>

<div class="admin-wrapper">
    @include('partials.admin.sidebar')

    <main class="main-content">
        @include('partials.admin.topbar')

        <div class="page-content">
            {{-- Flash messages rendered via AdminModal.status() --}}
            @if(session('success') || session('error') || session('warning') || session('info'))
            <div id="flashData" class="d-none"
                 data-type="{{ session('success') ? 'success' : (session('error') ? 'danger' : (session('warning') ? 'warning' : 'info')) }}"
                 data-title="{{ session('success') ? 'Başarılı' : (session('error') ? 'Hata' : (session('warning') ? 'Uyarı' : 'Bilgi')) }}"
                 data-message="{{ session('success') ?? session('error') ?? session('warning') ?? session('info') }}"></div>
            @endif

            {{-- A form level validation error has no field to sit next to, so it
                 is shown the same way a flash message is. --}}
            @error('translations')
            <div id="formErrorData" class="d-none" data-message="{{ $message }}"></div>
            @enderror

            @yield('content')
        </div>
    </main>
</div>

{{-- Global Modals --}}
@include('partials.admin.global-modals')

{{-- Sidebar overlay (mobile) --}}
<div class="sidebar-overlay d-lg-none" id="sidebarOverlay"></div>

{{-- JS (Self-hosted) --}}
<script src="{{ asset('assets/vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
{{-- jQuery is here for one reason only: the Validation Engine plugin below. --}}
<script src="{{ asset('assets/vendor/jquery/jquery-3.7.1.min.js') }}"></script>
{{-- Panel dili SetAdminLocale'de sabitleniyor; yardımcı ona uyan dosyayı
     veriyor. Sabit yazılsaydı o sabit değiştiğinde geride kalırdı. --}}
<script src="{{ asset(validation_engine_script()) }}"></script>
<script src="{{ asset('assets/vendor/jquery-validation-engine/js/jquery.validationEngine.js') }}"></script>
<script src="{{ asset('assets/vendor/select2/js/select2.min.js') }}"></script>
<script src="{{ asset('assets/vendor/select2/js/i18n/tr.js') }}"></script>
<script src="{{ asset('assets/vendor/select2/js/i18n/en.js') }}"></script>
<script src="{{ asset('assets/vendor/dropzone/dropzone.min.js') }}"></script>
<script nonce="{{ csp_nonce() }}">Dropzone.autoDiscover = false;</script>
<script src="{{ asset('assets/vendor/sortablejs/Sortable.min.js') }}"></script>
<script src="{{ asset('assets/vendor/aos/aos.js') }}"></script>
<script src="{{ versioned_asset('assets/admin/js/app.js') }}"></script>
<script src="{{ versioned_asset('assets/admin/js/global-modals.js') }}"></script>
<script src="{{ versioned_asset('assets/admin/js/stat-counter.js') }}"></script>
<script src="{{ versioned_asset('assets/admin/js/language-tabs.js') }}"></script>
<script src="{{ versioned_asset('assets/admin/js/section-nav.js') }}"></script>
<script src="{{ versioned_asset('assets/admin/js/form-validation.js') }}"></script>
<script src="{{ versioned_asset('assets/admin/js/file-input.js') }}"></script>
<script src="{{ versioned_asset('assets/admin/js/password-toggle.js') }}"></script>
<script src="{{ versioned_asset('assets/admin/js/select2-init.js') }}"></script>
<script src="{{ versioned_asset('assets/admin/js/export-menu.js') }}"></script>
{{-- Toplu işlem motoru: işaretleri olmayan sayfada hiçbir şey yapmıyor,
     olan her listede aynı şekilde çalışıyor. --}}
<script src="{{ versioned_asset('assets/admin/js/bulk-actions.js') }}"></script>
@if(Route::has('admin.notifications.recent'))
<script src="{{ versioned_asset('assets/admin/js/notification-bell.js') }}"></script>
@endif
<script nonce="{{ csp_nonce() }}">AOS.init({ duration: 600, easing: 'ease-out-cubic', once: true, offset: 50 });</script>
<script nonce="{{ csp_nonce() }}">
(function() {
    if (typeof AdminModal === 'undefined') {
        return;
    }

    var flash = document.getElementById('flashData');
    if (flash) {
        AdminModal.status({
            title: flash.dataset.title,
            message: flash.dataset.message,
            type: flash.dataset.type
        });
        flash.remove();
    }

    var formError = document.getElementById('formErrorData');
    if (formError) {
        AdminModal.status({
            title: 'Eksik bilgi',
            message: formError.dataset.message,
            type: 'danger'
        });
        formError.remove();
    }
})();
</script>
<script nonce="{{ csp_nonce() }}">
    // Sidebar toggle (mobile + desktop)
    (function () {
        const toggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('adminSidebar');
        const mainContent = document.querySelector('.main-content');
        const overlay = document.getElementById('sidebarOverlay');

        if (toggle && sidebar && mainContent) {
            toggle.addEventListener('click', function () {
                var isMobile = window.innerWidth < 992;
                if (isMobile) {
                    sidebar.classList.toggle('show');
                    overlay.classList.toggle('show');
                    document.body.classList.toggle('sidebar-open');
                } else {
                    sidebar.classList.toggle('collapsed');
                    mainContent.classList.toggle('sidebar-collapsed');
                }
            });
        }

        if (overlay && sidebar) {
            overlay.addEventListener('click', function () {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
                document.body.classList.remove('sidebar-open');
            });
        }
    })();
</script>

@stack('scripts')
</body>
</html>
