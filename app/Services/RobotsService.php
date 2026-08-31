<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Route;

/**
 * robots.txt'in içeriği.
 *
 * Dosya `public/robots.txt` altında sabit duruyordu ve iki şekilde yanlıştı.
 * Birincisi kopyalanabilir olması: bu depodan türeyen her proje `Sitemap:`
 * satırında başka bir sitenin alan adını taşıyordu, üstelik sökülmüş
 * modüllerden kalan yolları (/sepet, /siparis) hâlâ yasaklıyordu. İkincisi
 * eskimesi: adresler artık panelden açılıyor (bkz. CustomRoute), yani elle
 * yazılmış bir liste her yeni adreste geride kalıyordu.
 *
 * Buradaki liste rotaların kendisinden üretiliyor. Bir rotanın yolu
 * değiştiğinde robots.txt kendiliğinden takip ediyor; kimsenin bu dosyayı
 * hatırlaması gerekmiyor.
 *
 * Önbelleğe alınmıyor: dayandığı iki kaynak (aktif diller ve özel adres
 * haritası) zaten kendi önbelleklerinden geliyor, geri kalanı bellekteki
 * rota koleksiyonu. Üçüncü bir önbellek yalnızca geçersizleştirilecek bir
 * yüzey daha eklerdi.
 */
final class RobotsService
{
    /**
     * Dizine girmemesi gereken alanların rota adları.
     *
     * Ad üzerinden gidiliyor, yol üzerinden değil: yollar Türkçe yazılmış ve
     * değişebilir, rota adı ise koda bağlı. Adı bulunamayan rota sessizce
     * atlanıyor — bu listede kalmış eski bir ad sayfayı bozmamalı.
     *
     * @var list<string>
     */
    private const PRIVATE_ROUTES = [
        // Kimlik doğrulama
        'login',
        'register',
        'password.request',
        'password.reset',
        'password.update',
        'logout',
        'verification.notice',
        'verification.verify',
        'verification.send',

        // Üyenin kendi alanı
        'account.dashboard',
        'account.profile',

        // İçerik olmayan uç noktalar: ikisi de tek iş yapıp yönlendiriyor.
        // Bülten çıkışı ayrıca giriş istemeyen ve durum değiştiren bir GET —
        // bir tarayıcı robotunun izlemesi gereken en son bağlantı.
        'locale.switch',
        'newsletter.unsubscribe',
    ];

    public function __construct(
        private readonly LanguageService $languages,
        private readonly CustomRouteService $customRoutes,
    ) {}

    public function content(): string
    {
        return app()->environment('production')
            ? $this->openToSearchEngines()
            : $this->closedToSearchEngines();
    }

    /**
     * Canlı olmayan her kopya tümüyle kapalı.
     *
     * Staging bir kopyası da `Allow: /` deseydi aynı içerik iki alan adında
     * dizine girer ve canlı siteyle kopya içerik çakışması üretirdi.
     */
    private function closedToSearchEngines(): string
    {
        return implode("\n", [
            '# ' . app()->environment() . ' kopyası — arama motorlarına kapalı.',
            '# Canlıda (APP_ENV=production) tam liste basılır.',
            '',
            'User-agent: *',
            'Disallow: /',
            '',
        ]);
    }

    private function openToSearchEngines(): string
    {
        $paths = $this->disallowedPaths();

        $lines = [
            'User-agent: *',
            'Allow: /',
            '',
            '# Yönetim paneli',
            'Disallow: /admin/',
        ];

        if ($paths['localized'] !== []) {
            $lines[] = '';
            $lines[] = '# Hesap ve kimlik doğrulama alanları — yayındaki her dil önekinde';
            foreach ($paths['localized'] as $path) {
                $lines[] = 'Disallow: ' . $path;
            }
        }

        if ($paths['legacy'] !== []) {
            $lines[] = '';
            $lines[] = '# Dil önekinden önceki adresler (kalıcı yönlendirme ile karşılanır)';
            foreach ($paths['legacy'] as $path) {
                $lines[] = 'Disallow: ' . $path;
            }
        }

        if ($paths['global'] !== []) {
            $lines[] = '';
            $lines[] = '# Dil taşımayan uç noktalar';
            foreach ($paths['global'] as $path) {
                $lines[] = 'Disallow: ' . $path;
            }
        }

        $lines[] = '';
        $lines[] = 'Sitemap: ' . route('sitemap');
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * @return array{localized: list<string>, legacy: list<string>, global: list<string>}
     */
    private function disallowedPaths(): array
    {
        $locales   = $this->locales();
        $localized = [];
        $legacy    = [];
        $global    = [];

        foreach (self::PRIVATE_ROUTES as $name) {
            $uri = Route::getRoutes()->getByName($name)?->uri();

            if ($uri === null) {
                continue;
            }

            $prefix = '{locale}/';

            if (! str_starts_with($uri, $prefix)) {
                $global[] = '/' . $this->cutAtFirstParameter($uri);

                continue;
            }

            $path = $this->cutAtFirstParameter(substr($uri, strlen($prefix)));

            foreach ($locales as $locale) {
                $localized[] = '/' . $locale . '/' . $path;
            }

            // Dil öneki gelmeden önceki adresler hâlâ cevap veriyor
            // (LegacyUrlController kalıcı olarak yenisine gönderiyor), yani
            // hâlâ taranabilir durumdalar.
            $legacy[] = '/' . $path;
        }

        foreach ($this->customRoutePaths($locales) as $path) {
            $localized[] = $path;
        }

        return [
            'localized' => $this->tidy($localized),
            'legacy'    => $this->tidy($legacy),
            'global'    => $this->tidy($global),
        ];
    }

    /**
     * Panelden özel alanlara açılmış adresler.
     *
     * Yerleşik yolların Türkçe olduğu yerde İngilizce karşılıkları buradan
     * geliyor (/en/contact gibi). Aynı mekanizmayla /en/login açılırsa robots
     * onu da yasaklamalı — asıl adresi yasaklayıp takma adını açık bırakmak
     * hiçbir işe yaramaz.
     *
     * @param  list<string> $locales
     * @return list<string>
     */
    private function customRoutePaths(array $locales): array
    {
        $paths = [];

        foreach ($this->customRoutes->map()['incoming'] as $key => $route) {
            if (! in_array($route['route'], self::PRIVATE_ROUTES, true)) {
                continue;
            }

            // Anahtar "dil|slug"; dil yerine * varsa kayıt her dilde geçerli.
            $locale = explode('|', (string) $key, 2)[0];
            $targets = $locale === '*' ? $locales : [$locale];

            foreach ($targets as $target) {
                if (! in_array($target, $locales, true)) {
                    continue;
                }

                $paths[] = '/' . $target . '/' . ltrim((string) $route['slug'], '/');
            }
        }

        return $paths;
    }

    /**
     * Yolun parametre başlayana kadarki kısmı.
     *
     * `sifre-sifirla/{token}` → `sifre-sifirla/`. Robots kuralları önek
     * eşleşmesiyle çalıştığı için parametrenin kendisi gereksiz; sondaki
     * bölü çizgisi altındaki her adresi kapsıyor.
     */
    private function cutAtFirstParameter(string $uri): string
    {
        $position = strpos($uri, '{');

        if ($position === false) {
            return $uri;
        }

        return rtrim(substr($uri, 0, $position), '/') . '/';
    }

    /**
     * Tekrarları at, kısa önek uzun olanı zaten kapsıyorsa uzun olanı at,
     * kalanı sırala.
     *
     * `/tr/hesabim` yazılmışsa `/tr/hesabim/profil` fazladan satır: robots
     * önek eşleştiriyor, ikincisi hiçbir şey eklemiyor.
     *
     * @param  list<string> $paths
     * @return list<string>
     */
    private function tidy(array $paths): array
    {
        $paths = array_values(array_unique($paths));
        sort($paths);

        $kept = [];

        foreach ($paths as $path) {
            $covered = array_any(
                $kept,
                static fn (string $existing): bool => str_starts_with($path, $existing),
            );

            if (! $covered) {
                $kept[] = $path;
            }
        }

        return $kept;
    }

    /**
     * Yayındaki diller. Tablo henüz boşsa (taze kurulum, migration ortası)
     * varsayılan dile düşülüyor; liste hiçbir zaman boş kalmıyor.
     *
     * @return list<string>
     */
    private function locales(): array
    {
        $codes = $this->languages->activeCodes();

        return $codes === [] ? [$this->languages->defaultCode()] : array_values($codes);
    }
}
