<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Bakım Modu | {{ \App\Models\Setting::getValue('site_name', config('app.name')) }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="{{ versioned_asset('css/maintenance.css') }}">
</head>
<body>
    <div class="maintenance">
        <div class="maintenance__particles" aria-hidden="true">
            <span></span><span></span><span></span><span></span><span></span>
        </div>

        <div class="maintenance__card">
            @php
                $siteLogo = \App\Models\Setting::getValue('site_logo');
            @endphp
            @if($siteLogo)
                <img src="{{ upload_url($siteLogo) }}" alt="{{ \App\Models\Setting::getValue('site_name', config('app.name')) }}" class="maintenance__logo" loading="lazy">
            @endif

            <div class="maintenance__icon">
                <i class="fa-solid fa-gear maintenance__gear maintenance__gear--lg"></i>
                <i class="fa-solid fa-gear maintenance__gear maintenance__gear--sm"></i>
            </div>

            <h1 class="maintenance__title">Bakımdayız</h1>

            <p class="maintenance__text">
                {{ $maintenanceMessage ?? 'Sitemiz şu anda planlı bakım çalışması nedeniyle geçici olarak kullanım dışıdır. Kısa süre içinde tekrar hizmetinizde olacağız.' }}
            </p>

            <div class="maintenance__divider"></div>

            <div class="maintenance__info">
                <i class="fa-regular fa-clock"></i>
                <span>En kısa sürede tekrar hizmetinizde olacağız</span>
            </div>

            <button type="button" class="maintenance__btn" onclick="window.location.reload()">
                <i class="fa-solid fa-rotate-right"></i> Tekrar Dene
            </button>
        </div>

        <footer class="maintenance__footer">
            <p>&copy; {{ date('Y') }} {{ \App\Models\Setting::getValue('site_name', config('app.name')) }}. Tüm hakları saklıdır.</p>
        </footer>
    </div>
</body>
</html>
