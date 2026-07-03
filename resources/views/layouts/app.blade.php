<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        $siteName  = \App\Models\Setting::getValue('site_name', config('app.name'));
        $siteTitle = \App\Models\Setting::getValue('site_title', $siteName);
        $siteDesc  = \App\Models\Setting::getValue('site_description', 'Doğal ve organik çiftlik ürünleri. Taze süt, köy peyniri, tereyağı, yumurta ve bal.');
        $siteKeywords = \App\Models\Setting::getValue('site_keywords');
        $siteFavicon  = \App\Models\Setting::getValue('site_favicon');
        $ogTitle = \App\Models\Setting::getValue('og_title', $siteTitle);
        $ogDesc  = \App\Models\Setting::getValue('og_description', $siteDesc);
        $ogImage = \App\Models\Setting::getValue('og_image');
        $customHead = \App\Models\Setting::getValue('custom_head_code');
        $gaId  = \App\Models\Setting::getValue('google_analytics_id');
        $gtmId = \App\Models\Setting::getValue('google_tag_manager_id');

        $siteLogo = \App\Models\Setting::getValue('site_logo');
        $logoUrl = $siteLogo ? upload_url($siteLogo) : asset('images/logo.png');
    @endphp

    <title>@yield('title', $siteTitle . ' | Çorum Doğal Köy Ürünleri')</title>
    <meta name="description" content="@yield('meta_description', $siteDesc)">
    @if($siteKeywords)
    <meta name="keywords" content="{{ $siteKeywords }}">
    @endif
    <meta name="robots" content="@yield('robots', 'index, follow')">
    @hasSection('canonical')
    <link rel="canonical" href="@yield('canonical')">
    @endif
    <link rel="alternate" hreflang="tr" href="{{ url()->current() }}">
    <link rel="alternate" type="application/rss+xml" title="{{ $siteName }} Blog" href="{{ route('feed') }}">
    @stack('pagination-meta')

    {{-- Open Graph --}}
    <meta property="og:title" content="@yield('og_title', trim(View::yieldContent('title', $ogTitle)))">
    <meta property="og:description" content="@yield('og_description', trim(View::yieldContent('meta_description', $ogDesc)))">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:locale" content="tr_TR">
    <meta property="og:site_name" content="{{ $siteTitle }}">
    <meta property="og:url" content="@yield('canonical', url()->current())">
    @hasSection('og_image')
    <meta property="og:image" content="@yield('og_image')">
    @elseif($ogImage)
    <meta property="og:image" content="{{ url(upload_url($ogImage)) }}">
    @else
    <meta property="og:image" content="{{ url($logoUrl) }}">
    @endif

    {{-- Twitter Card --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('og_title', trim(View::yieldContent('title', $ogTitle)))">
    <meta name="twitter:description" content="@yield('og_description', trim(View::yieldContent('meta_description', $ogDesc)))">
    @hasSection('og_image')
    <meta name="twitter:image" content="@yield('og_image')">
    @elseif($ogImage)
    <meta name="twitter:image" content="{{ url(upload_url($ogImage)) }}">
    @else
    <meta name="twitter:image" content="{{ url($logoUrl) }}">
    @endif

    {{-- Favicon & Icons --}}
    @if($siteFavicon)
    <link rel="icon" href="{{ upload_url($siteFavicon) }}">
    <link rel="apple-touch-icon" href="{{ upload_url($siteFavicon) }}">
    @else
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('favicon.ico') }}">
    @endif
    <meta name="theme-color" content="#4a7c43">

    {{-- DNS Prefetch & Preconnect --}}
    <link rel="dns-prefetch" href="//www.googletagmanager.com">
    <link rel="dns-prefetch" href="//www.google-analytics.com">
    <link rel="preconnect" href="https://www.googletagmanager.com" crossorigin>

    {{-- Preload critical images --}}
    <link rel="preload" as="image" href="{{ $logoUrl }}" fetchpriority="high">
    @stack('preload')

    {{-- CSS --}}
    <link href="{{ asset('assets/vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendor/fonts/fonts.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendor/fontawesome/css/all.min.css') }}" rel="stylesheet">
    <link href="{{ versioned_asset('css/app.css') }}" rel="stylesheet">

    @stack('styles')

    @if($gaId)
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}"></script>
    <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','{{ $gaId }}');</script>
    @endif

    @if($gtmId)
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{{ $gtmId }}');</script>
    @endif

    @if($customHead)
    {!! $customHead !!}
    @endif

    {{-- LocalBusiness JSON-LD (Çorum yerel SEO için kritik) --}}
    @php
        $orgPhone   = \App\Models\Setting::getValue('contact_phone');
        $orgEmail   = \App\Models\Setting::getValue('contact_email');
        $orgAddress = \App\Models\Setting::getValue('contact_address', 'Büyük Palabıyık Köyü Çorum/Merkez');
        $whWeekday  = \App\Models\Setting::getValue('working_hours_weekday', '08:00 - 18:00');
        $whSaturday = \App\Models\Setting::getValue('working_hours_saturday', '09:00 - 16:00');
        $whSunday   = \App\Models\Setting::getValue('working_hours_sunday', 'Kapalı');

        $orgJsonLd = [
            '@context'    => 'https://schema.org',
            '@type'       => 'LocalBusiness',
            'additionalType' => 'https://schema.org/Farm',
            'name'        => $siteName,
            'url'         => url('/'),
            'description' => $siteDesc,
            'address'     => [
                '@type'           => 'PostalAddress',
                'streetAddress'   => 'Büyük Palabıyık Köyü',
                'addressLocality' => 'Çorum',
                'addressRegion'   => 'Çorum',
                'postalCode'      => '19100',
                'addressCountry'  => 'TR',
            ],
            'geo' => [
                '@type'     => 'GeoCoordinates',
                'latitude'  => 40.5506,
                'longitude' => 34.9556,
            ],
            'areaServed' => [
                ['@type' => 'City',           'name' => 'Çorum'],
                ['@type' => 'AdministrativeArea', 'name' => 'İç Anadolu Bölgesi'],
                ['@type' => 'Country',        'name' => 'Türkiye'],
            ],
            'priceRange' => '₺₺',
            'image'       => $ogImage ? url(upload_url($ogImage)) : url($logoUrl),
            'logo'        => url($logoUrl),
        ];
        if ($orgPhone) {
            $orgJsonLd['telephone'] = $orgPhone;
        }
        if ($orgEmail) {
            $orgJsonLd['email'] = $orgEmail;
        }
        // Açılış saatleri
        $openingHours = [];
        if ($whWeekday && stripos($whWeekday, 'kapalı') === false) {
            $openingHours[] = [
                '@type'     => 'OpeningHoursSpecification',
                'dayOfWeek' => ['Monday','Tuesday','Wednesday','Thursday','Friday'],
                'opens'     => trim(explode('-', $whWeekday)[0] ?? '08:00'),
                'closes'    => trim(explode('-', $whWeekday)[1] ?? '18:00'),
            ];
        }
        if ($whSaturday && stripos($whSaturday, 'kapalı') === false) {
            $openingHours[] = [
                '@type'     => 'OpeningHoursSpecification',
                'dayOfWeek' => 'Saturday',
                'opens'     => trim(explode('-', $whSaturday)[0] ?? '09:00'),
                'closes'    => trim(explode('-', $whSaturday)[1] ?? '16:00'),
            ];
        }
        if ($whSunday && stripos($whSunday, 'kapalı') === false) {
            $openingHours[] = [
                '@type'     => 'OpeningHoursSpecification',
                'dayOfWeek' => 'Sunday',
                'opens'     => trim(explode('-', $whSunday)[0] ?? '10:00'),
                'closes'    => trim(explode('-', $whSunday)[1] ?? '14:00'),
            ];
        }
        if (!empty($openingHours)) {
            $orgJsonLd['openingHoursSpecification'] = $openingHours;
        }

        $socialLinks = collect([
            \App\Models\Setting::getValue('social_facebook'),
            \App\Models\Setting::getValue('social_instagram'),
            \App\Models\Setting::getValue('social_twitter'),
            \App\Models\Setting::getValue('social_youtube'),
        ])->filter()->values()->all();

        if (!empty($socialLinks)) {
            $orgJsonLd['sameAs'] = $socialLinks;
        }
    @endphp
    <script type="application/ld+json">{!! json_encode($orgJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>

    {{-- WebSite JSON-LD --}}
    @php
        $websiteJsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $siteName,
            'url' => url('/'),
            'description' => $siteDesc,
            'inLanguage' => 'tr',
        ];
    @endphp
    <script type="application/ld+json">{!! json_encode($websiteJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>

    @stack('json-ld')
</head>
<body>
    <a href="#main-content" class="skip-to-content">Ana içeriğe geç</a>

    @if($gtmId)
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $gtmId }}" height="0" width="0" class="d-none"></iframe></noscript>
    @endif

    @include('partials.navbar')

    <main id="main-content">
        @yield('content')
    </main>

    @include('partials.footer')

    {{-- WhatsApp Button --}}
    @php
        $whatsappUrl = \App\Models\Setting::getValue('social_whatsapp');
    @endphp
    @if($whatsappUrl)
    <a href="{{ whatsapp_url($whatsappUrl, 'Merhaba, bilgi almak istiyorum.') }}" class="whatsapp-float" target="_blank" rel="noopener noreferrer"
       aria-label="WhatsApp ile iletişime geçin">
        <i class="fa-brands fa-whatsapp"></i>
        <span class="whatsapp-float-text">Bize Yazın</span>
    </a>
    @endif

    {{-- Scroll to top --}}
    <button class="scroll-top" aria-label="Yukarı çık">
        <i class="fa-solid fa-arrow-up"></i>
    </button>

    {{-- Global Result Modal --}}
    @include('partials.result-modal')

    {{-- Global Confirm Modal --}}
    @include('partials.confirm-modal')

    {{-- JS --}}
    <script src="{{ asset('assets/vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ versioned_asset('js/app.js') }}"></script>
    @include('partials.flash-message')

    @if(app(\App\Services\RecaptchaService::class)->isEnabled())
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    @endif

    {{-- Analytics tracker (async, 3sn delay; admin/staff skip via server-side check) --}}
    @unless(auth()->check() && auth()->user()->hasAnyRole(['admin','editor','moderator']))
    <script src="{{ versioned_asset('js/analytics-tracker.js') }}" defer></script>
    @endunless

    @stack('scripts')
</body>
</html>
