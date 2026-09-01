<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('site.errors.maintenance_mode') }} | {{ $siteName ?? config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    {{-- Varlıklar sunucunun kendisinden. Bakımdaki bir sunucu genelde dışarı
         çıkamayan sunucudur; CDN'e ulaşılamazsa sayfa tam da görüntünün
         önemli olduğu anda biçimsiz açılırdı. Ayrıca CDN'deki sürüm sabit
         yazılıydı ve projenin kendi sürümüyle birlikte güncellenmiyordu. --}}
    <link href="{{ asset('assets/vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendor/fontawesome/css/all.min.css') }}" rel="stylesheet">
    <link href="{{ versioned_asset('css/maintenance.css') }}" rel="stylesheet">
</head>
<body>
    <div class="maintenance">
        <div class="maintenance__particles" aria-hidden="true">
            <span></span><span></span><span></span><span></span><span></span>
        </div>

        <div class="maintenance__card">
            {{-- Site adı ve logo görünüme dışarıdan veriliyor, burada
                 sorulmuyor: Laravel bu sayfayı "php artisan down" için de
                 basıyor ve orada veritabanı düşmüş olabilir. Ayarı okuyan
                 yer, okumanın güvenli olduğunu bilen yer. --}}
            @if(! empty($siteLogo))
                <img src="{{ upload_url($siteLogo) }}" alt="{{ image_alt($siteName ?? null) }}"
                     class="maintenance__logo" loading="lazy" decoding="async">
            @endif

            <div class="maintenance__icon">
                <i class="fa-solid fa-gear maintenance__gear maintenance__gear--lg"></i>
                <i class="fa-solid fa-gear maintenance__gear maintenance__gear--sm"></i>
            </div>

            <h1 class="maintenance__title">{{ __('site.errors.503_title') }}</h1>

            <p class="maintenance__text">
                {{ $maintenanceMessage ?? __('site.errors.503_message') }}
            </p>

            <div class="maintenance__divider"></div>

            <div class="maintenance__info">
                <i class="fa-regular fa-clock"></i>
                <span>{{ __('site.errors.503_sub') }}</span>
            </div>

            <button type="button" class="maintenance__btn" data-action="yenile">
                <i class="fa-solid fa-rotate-right"></i> {{ __('site.misc.retry') }}
            </button>
        </div>

        <footer class="maintenance__footer">
            <p>&copy; {{ date('Y') }} {{ $siteName ?? config('app.name') }}. {{ __('site.misc.rights') }}</p>
        </footer>
    </div>
</body>
</html>
