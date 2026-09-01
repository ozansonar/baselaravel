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
        | Panelden açılıp kapanabilen araçların alan adları. Ayar kapalıyken
        | betik zaten sayfaya basılmıyor, ama politika istek anında değil
        | yapılandırmadan okunduğu için liste sabit tutuluyor: aracı açan
        | yöneticinin ayrıca CSP düzenlemesi gerekmiyor.
        */
        'vendors' => [
            // Google Analytics / Tag Manager
            'analytics' => [
                'script'  => ['https://www.googletagmanager.com', 'https://www.google-analytics.com'],
                'img'     => ['https://www.google-analytics.com', 'https://www.googletagmanager.com'],
                'connect' => ['https://www.google-analytics.com', 'https://www.googletagmanager.com', 'https://analytics.google.com'],
                'frame'   => ['https://www.googletagmanager.com'],
            ],
            // reCAPTCHA — betik www.google.com'dan, kaynaklar gstatic'ten,
            // kutu bir iframe içinde geliyor.
            'recaptcha' => [
                'script' => ['https://www.google.com', 'https://www.gstatic.com'],
                'frame'  => ['https://www.google.com'],
                'img'    => ['https://www.gstatic.com'],
            ],
            // Meta (Facebook) Pixel. Yalnız panelde bir Pixel kimliği
            // girilmişken yayılıyor: `requires` alanı dolu olan sağlayıcı,
            // o ayar boşken CSP'ye hiç eklenmiyor. Kullanılmayan bir alan
            // adını sürekli açık tutmak, kazanç olmadan yüzeyi genişletmek
            // olurdu.
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
