<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">

    <title>@yield('title', 'Giriş Yap') | {{ \App\Models\Setting::getValue('site_name', config('app.name')) }}</title>

    @php
        $gaId  = \App\Models\Setting::getValue('google_analytics_id');
        $gtmId = \App\Models\Setting::getValue('google_tag_manager_id');
    @endphp

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
<body class="d-flex align-items-center justify-content-center min-vh-100" data-auth-page>
    @if($gtmId)
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $gtmId }}" height="0" width="0" class="d-none"></iframe></noscript>
    @endif

<div class="container">
    <div class="row justify-content-center">
        <div class="col-12 col-sm-10 col-md-8 col-lg-5 col-xl-4">

            {{-- Logo --}}
            <div class="text-center mb-4">
                <a href="{{ route('home') }}" class="text-decoration-none">
                    <span class="logo-icon d-inline-flex mx-auto mb-3">
                        <i class="fa-solid fa-leaf"></i>
                    </span>
                    <h4 class="text-green-dark">{{ \App\Models\Setting::getValue('site_name', config('app.name')) }}</h4>
                </a>
            </div>

            {{-- Flash Messages --}}
            @include('partials.flash-message')

            {{-- Card --}}
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body p-4 p-md-5">
                    @yield('content')
                </div>
            </div>

            {{-- Footer --}}
            <p class="text-center text-muted mt-4 small">
                &copy; {{ date('Y') }} {{ \App\Models\Setting::getValue('site_name', config('app.name')) }}
            </p>
        </div>
    </div>
</div>

{{-- JS --}}
<script src="{{ asset('assets/vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
<script src="{{ versioned_asset('js/app.js') }}"></script>

@if(app(\App\Services\RecaptchaService::class)->isEnabled())
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
@endif

@stack('scripts')
</body>
</html>
