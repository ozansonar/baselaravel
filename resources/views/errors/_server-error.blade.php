{{--
    Sunucu hataları — 500, 502, 504 ve sayfası açılmamış her 5xx.

    Sunucu hatasında sayfanın kendisi veritabanına gitmemeli: hatanın sebebi
    veritabanıysa (kopan bağlantı, dolan disk) site düzenini kullanan bir hata
    sayfası da düşer ve ziyaretçi çerçevenin çıplak ekranını görür — tam da en
    kötü anda. Bu yüzden layouts.app kullanılmıyor; sayfa kendi başına ayakta
    duruyor, görünümü aynı CSS'ten geliyor.

    Metin koda özgü karşılığı varsa onu, yoksa genel olanı kullanıyor: 502 ve
    504 "sunucu yanıt vermedi" derken, adı konmamış bir kod da anlamlı bir
    cümleyle karşılanıyor.
--}}
@php
    $status = $exception?->getStatusCode() ?? 500;

    // Metin açık bir eşlemeden geliyor, anahtar adı koddan kurulmuyor.
    // Kurulsaydı hiçbir tarama bu anahtarların kullanıldığını göremez,
    // "kimse çağırmıyor" diye silinebilirlerdi — nitekim denetim uyardı.
    [$baslik, $metin] = match ($status) {
        500     => [__('site.errors.500_title'), __('site.errors.500')],
        502     => [__('site.errors.502_title'), __('site.errors.502')],
        504     => [__('site.errors.504_title'), __('site.errors.504')],
        default => [__('site.errors.generic_server_title'), __('site.errors.generic_server')],
    };
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $status }} - {{ $baslik }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link href="{{ asset('assets/vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
</head>
<body>
    <main class="section">
        <div class="container">
            <div class="empty-state">
                <div class="display-1 fw-bold text-brand mb-2">{{ $status }}</div>
                <h1 class="section__title">{{ $baslik }}</h1>
                <p class="section__lead mx-auto mb-4">{{ $metin }}</p>
                <a href="{{ url(app()->getLocale()) }}" class="btn btn-primary btn-lg">
                    <i class="fa-solid fa-house"></i> {{ __('site.nav.home') }}
                </a>
            </div>
        </div>
    </main>
</body>
</html>
