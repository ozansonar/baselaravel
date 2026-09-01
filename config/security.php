<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | İçerik Güvenlik Politikası (CSP)
    |--------------------------------------------------------------------------
    | XSS'e karşı ikinci savunma hattı. Blade'in kaçışı birinci hat; bu başlık,
    | o hattın delindiği durumda saldırganın çalıştırabileceği kodu sınırlar.
    |
    | Politika nonce tabanlı: her istekte bir kerelik anahtar üretiliyor ve
    | sayfadaki satır içi betikler onu taşıyor. Saldırganın enjekte ettiği
    | betik o anahtarı bilemeyeceği için çalışmıyor. Beyaz listeye alınmış
    | alan adları yalnızca gerçekten kullanılanlar.
    */
    'csp' => [

        /*
        | Politika uygulanıyor mu, yoksa yalnız rapor mu ediliyor?
        |
        | Yeni bir kuruluma ya da yeni bir üçüncü taraf betiğe geçerken önce
        | rapor moduyla çıkmak doğru: tarayıcı hiçbir şeyi engellemiyor ama
        | ihlalleri bildiriyor, yani politikanın siteyi kırıp kırmayacağı
        | canlıya zarar vermeden görülüyor. Bu kit'te varsayılan olarak
        | uygulanıyor — politika projeyle birlikte yazıldı ve testleri var.
        */
        'enabled'     => (bool) env('CSP_ENABLED', true),
        'report_only' => (bool) env('CSP_REPORT_ONLY', false),

        /*
        | İhlal raporlarının düştüğü uç. Boş bırakılırsa `report-uri` hiç
        | yazılmaz; rapor toplamak istemeyen bir kurulum kapatabilir.
        */
        'report_route' => (bool) env('CSP_REPORT_ROUTE', true),

        /*
        | Bir dakikada kaç ihlal raporu loglanır? Bozuk bir eklenti ya da
        | saldırgan tarayıcıyı saniyede yüzlerce rapor göndermeye
        | zorlayabiliyor; sınır olmadan log dosyası şişer.
        */
        'report_rate_limit' => (int) env('CSP_REPORT_RATE_LIMIT', 30),

        /*
        |----------------------------------------------------------------------
        | Ek kaynaklar
        |----------------------------------------------------------------------
        | Projeye sonradan eklenen üçüncü taraf araçların alan adları buraya
        | girer; virgülle ayrılır. Politikanın kendisini değiştirmek yerine
        | burayı doldurmak, bir sonraki güncellemede kaybolmamasını sağlar.
        */
        'extra' => [
            'script' => array_filter(explode(',', (string) env('CSP_EXTRA_SCRIPT', ''))),
            'style'  => array_filter(explode(',', (string) env('CSP_EXTRA_STYLE', ''))),
            'img'    => array_filter(explode(',', (string) env('CSP_EXTRA_IMG', ''))),
            'font'   => array_filter(explode(',', (string) env('CSP_EXTRA_FONT', ''))),
            'connect' => array_filter(explode(',', (string) env('CSP_EXTRA_CONNECT', ''))),
            'frame'  => array_filter(explode(',', (string) env('CSP_EXTRA_FRAME', ''))),
        ],

        /*
        |----------------------------------------------------------------------
        | Kullanılan üçüncü taraflar
        |----------------------------------------------------------------------
        | Panelden açılıp kapanabilen araçların alan adları.
        |
        | Panelden açılıp kapanabilen her sağlayıcı `requires` taşır: aracın
        | fiilen kullanımda olup olmadığını söyleyen koşul. Koşul sağlanmıyorsa
        | alan adı politikaya **hiç** yazılmaz. Koşulsuz duran tek sağlayıcı
        | `avatars`; o bir üçüncü taraf aracı değil, avatarı olmayan kullanıcı
        | için her sayfada gereken görsel kaynağı.
        |
        | Bu, nonce'un tek gerçek zayıf noktasını kapatıyor. Politika
        | "jetonu olan satır içi betikler VEYA şu alan adları" diyor; ikinci
        | kısım jeton koşulunu atlıyor. `googletagmanager.com` ve
        | `www.google.com` bilinen CSP atlatma kaynakları — üzerlerinde
        | saldırganın parametreyle kod çalıştırabildiği uç noktalar var. Sayfaya
        | HTML sokabilen biri, jetonu hiç bilmeden o hostlardan betik yükleyip
        | korumayı devre dışı bırakabiliyor.
        |
        | Kullanmadığın bir aracın kapısını sürekli açık tutmak, kazanç olmadan
        | saldırı yüzeyini genişletmek demek. Aracı açtığında kapı kendiliğinden
        | açılıyor; koşul istek anında okunuyor, `config:cache` sonrası da
        | geçerli.
        |
        | `requires` iki biçim alır:
        |   'ayar_adi'          → o ayar panelde dolu mu (kapalı anahtar sayılmaz)
        |   'service:<ad>'      → ilgili servise sor (bileşik koşullar için)
        */
        'vendors' => [
            // Google Analytics 4 — gtag.js googletagmanager'dan yükleniyor,
            // ölçümler google-analytics'e gidiyor. Çerçeve kullanmıyor.
            'analytics' => [
                'requires' => 'google_analytics_id',
                'script'   => ['https://www.googletagmanager.com', 'https://www.google-analytics.com'],
                'img'      => ['https://www.google-analytics.com', 'https://www.googletagmanager.com'],
                'connect'  => ['https://www.google-analytics.com', 'https://www.googletagmanager.com', 'https://analytics.google.com'],
            ],
            // Tag Manager ayrı: GA4'ten farklı olarak betiğe ek bir de
            // `noscript` iframe'i basıyor (ns.html). Tek blokta dursalardı
            // yalnız GA4 kullanan kurulum, hiç basmadığı bir çerçeveye izin
            // vermiş olurdu.
            'tag_manager' => [
                'requires' => 'google_tag_manager_id',
                'script'   => ['https://www.googletagmanager.com'],
                'img'      => ['https://www.googletagmanager.com'],
                'connect'  => ['https://www.googletagmanager.com'],
                'frame'    => ['https://www.googletagmanager.com'],
            ],
            // reCAPTCHA — betik www.google.com'dan, kaynaklar gstatic'ten,
            // kutu bir iframe içinde geliyor.
            //
            // Koşul tek bir ayar değil: açık/kapalı anahtarı, site anahtarı ve
            // gizli anahtarın üçü birden gerekiyor ve hiçbiri panelde yoksa
            // .env'e düşülüyor. Betiği sayfaya basan koşulun aynısı sorulmalı,
            // yoksa CSP ile sayfa ayrışır — biri açıkken diğeri kapalı kalır.
            'recaptcha' => [
                'requires' => 'service:recaptcha',
                'script'   => ['https://www.google.com', 'https://www.gstatic.com'],
                'frame'    => ['https://www.google.com'],
                'img'      => ['https://www.gstatic.com'],
            ],
            // Meta (Facebook) Pixel.
            'meta_pixel' => [
                'requires' => 'facebook_pixel_id',
                'script'   => ['https://connect.facebook.net'],
                'img'      => ['https://www.facebook.com'],
                'connect'  => ['https://www.facebook.com'],
            ],
            // Avatarı olmayan kullanıcı için baş harflerden görsel üreten
            // servis. Yalnız görsel kaynağı.
            'avatars' => [
                'img' => ['https://ui-avatars.com'],
            ],
        ],
    ],

];
