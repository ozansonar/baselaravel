<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">

    @php
        $siteName = \App\Models\Setting::getValue('site_name', config('app.name'));
        $siteLogo = \App\Models\Setting::getValue('site_logo');
        $gaId  = \App\Models\Setting::getValue('google_analytics_id');
        $gtmId = \App\Models\Setting::getValue('google_tag_manager_id');
    @endphp

    <title>@yield('title', 'Giriş') | {{ $siteName }}</title>

    @if($gaId)
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}"></script>
    <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','{{ $gaId }}');</script>
    @endif

    @if($gtmId)
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{{ $gtmId }}');</script>
    @endif

    {{-- CSS --}}
    <link href="{{ asset('assets/vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendor/fonts/fonts.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendor/fontawesome/css/all.min.css') }}" rel="stylesheet">
    <link href="{{ versioned_asset('css/app.css') }}" rel="stylesheet">

    @stack('styles')
</head>
<body data-auth-page>
    @if($gtmId)
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $gtmId }}" height="0" width="0" class="d-none"></iframe></noscript>
    @endif

    <div class="auth-wrap">

        {{-- Left: decorative brand panel (lg+ only) --}}
        <aside class="auth-aside">
            <a href="{{ route('home') }}" class="brand text-white">
                <span class="brand__mark"><i class="fa-solid fa-bolt"></i></span>
                <span class="brand__text">{{ $siteName }}</span>
            </a>

            <p class="auth-aside__quote">
                Kurumsal başarı, doğru araçlarla başlar. Ekibinizi tek platformda buluşturun.
            </p>

            <p class="text-white-50 small mb-0">
                &copy; {{ date('Y') }} {{ $siteName }}. Tüm hakları saklıdır.
            </p>
        </aside>

        {{-- Right: form side --}}
        <div class="auth-main">
            <div class="auth-card">

                {{-- Brand --}}
                <a href="{{ route('home') }}" class="auth-brand">
                    <span class="brand__mark"><i class="fa-solid fa-bolt"></i></span>
                    <span class="brand__text">{{ $siteName }}</span>
                </a>

                {{-- Flash Messages --}}
                @include('partials.flash-message')

                @yield('content')
            </div>
        </div>

    </div>

    @include('partials.result-modal')

    {{-- JS --}}
    <script src="{{ asset('assets/vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ versioned_asset('js/app.js') }}"></script>

    @if(app(\App\Services\RecaptchaService::class)->isEnabled())
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    @endif

    @stack('scripts')
</body>
</html>
