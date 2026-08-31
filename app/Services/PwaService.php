<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\File;

/**
 * Siteyi telefona kurulabilir hâle getiren iki dosya: uygulama bildirimi
 * (manifest) ve servis çalışanı.
 *
 * İkisi de rotadan üretiliyor, dosya olarak durmuyor. Sebebi aynı:
 *
 *   - Bildirimdeki ad, renk ve ikon panelden geliyor. Sabit dosya olsaydı
 *     bu kit'ten türeyen her proje onu elle düzenlemek zorunda kalırdı ve
 *     yöneticinin panelden değiştirdiği site adı telefonda eski hâliyle
 *     kalırdı.
 *   - Servis çalışanının önbellek sürümü, varlıkların son değişme
 *     zamanından üretiliyor. Elle yazılan bir sürüm numarası er ya da geç
 *     unutuluyor ve ziyaretçi haftalarca eski CSS'i görüyor.
 *
 * İkonlar kaynaktan bir kez üretilip `public/uploads/pwa/` altında duruyor:
 * manifest'in bildirdiği boyut ile dosyanın gerçek boyutu birbirini
 * tutmazsa Chrome kurulumu reddediyor, o yüzden kare ve tam ölçüde
 * üretiliyorlar.
 */
final class PwaService
{
    /** Manifest'in bildirdiği ölçüler. İkisi de Chrome'un aradığı asgari. */
    private const ICON_SIZES = [192, 512];

    private const ICON_DIR = 'pwa';

    public function __construct(
        private readonly UploadService $uploads,
    ) {}

    public function isEnabled(): bool
    {
        return Setting::getValue('pwa_enabled', '1') === '1';
    }

    /**
     * Uygulama bildirimi (manifest).
     *
     * @return array<string, mixed>
     */
    public function manifest(): array
    {
        $name = (string) Setting::getValue('site_name', (string) config('app.name'));
        $shortName = (string) (Setting::getValue('pwa_short_name') ?: $name);

        return [
            'name'       => $name,
            'short_name' => mb_substr($shortName, 0, 12),
            'description' => (string) Setting::getValue('site_description', ''),
            // Dil, kurulan uygulamanın adının hangi dilde okunacağını söylüyor.
            'lang'      => app()->getLocale(),
            'dir'       => 'auto',
            // Kurulan uygulama sitenin köküne açılıyor; dil ön ekini sabitlemek
            // kurulumu yapan kişinin dilini herkese dayatırdı.
            'start_url' => '/',
            'scope'     => '/',
            'display'   => 'standalone',
            'orientation' => 'any',
            'theme_color'      => (string) Setting::getValue('pwa_theme_color', '#4f46e5'),
            'background_color' => (string) Setting::getValue('pwa_background_color', '#ffffff'),
            'icons'            => $this->icons(),
        ];
    }

    /**
     * Manifest ikonları — gerekiyorsa üretilerek.
     *
     * `maskable` amaçlı ayrı bir giriş var: Android ikonu kendi maskesiyle
     * kırpıyor ve bunu bildirmeyen ikonlar beyaz bir kare içinde küçücük
     * görünüyor.
     *
     * @return list<array<string, string>>
     */
    public function icons(): array
    {
        $this->ensureIcons();

        $icons = [];

        foreach (self::ICON_SIZES as $size) {
            $icons[] = [
                'src'     => upload_url(self::ICON_DIR . '/icon-' . $size . '.png'),
                'sizes'   => $size . 'x' . $size,
                'type'    => 'image/png',
                'purpose' => 'any',
            ];
        }

        $icons[] = [
            'src'     => upload_url(self::ICON_DIR . '/icon-512.png'),
            'sizes'   => '512x512',
            'type'    => 'image/png',
            'purpose' => 'maskable',
        ];

        return $icons;
    }

    /**
     * Servis çalışanının gövdesi.
     *
     * Kabuk önbelleğe alınıyor ama HTML "önce ağ" ile geliyor: içerik sitesi
     * bu, ve önbellekten servis edilen bir haber bayat haberdir. Ağ
     * ulaşılamazsa önbellekteki kopya, o da yoksa çevrimdışı sayfası
     * gösteriliyor.
     */
    public function serviceWorker(): string
    {
        $version = $this->assetVersion();
        $offline = route('offline', absolute: false);

        $precacheJson = json_encode(
            array_merge([$offline], $this->shellAssets()),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        return <<<JS
        /**
         * Servis çalışanı — elle yazıldı, üretilmedi.
         *
         * Sürüm damgası varlıkların son değişme zamanından geliyor
         * (PwaService::assetVersion). Yeni sürüm yayınlandığında bu dosyanın
         * içeriği değişiyor, tarayıcı farkı görüp yeni çalışanı kuruyor ve
         * eski önbellek siliniyor.
         */
        const CACHE = 'app-{$version}';
        const OFFLINE_URL = '{$offline}';
        const PRECACHE = {$precacheJson};

        self.addEventListener('install', (event) => {
            event.waitUntil(
                caches.open(CACHE)
                    .then((cache) => cache.addAll(PRECACHE))
                    // Bekleme sırası atlanıyor: kullanıcı sekmeyi kapatıp
                    // açmadan da güncel sürümü almalı.
                    .then(() => self.skipWaiting())
            );
        });

        self.addEventListener('activate', (event) => {
            event.waitUntil(
                caches.keys()
                    .then((keys) => Promise.all(
                        keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))
                    ))
                    .then(() => self.clients.claim())
            );
        });

        self.addEventListener('fetch', (event) => {
            const request = event.request;

            if (request.method !== 'GET') return;

            const url = new URL(request.url);

            // Başka alan adları bizim işimiz değil.
            if (url.origin !== self.location.origin) return;

            // Panel, hesap alanı ve API hiç önbelleğe girmiyor: biri kişiye
            // özel veri taşıyor, öteki her istekte taze olmalı.
            if (/^\\/(admin|api)\\b/.test(url.pathname) || /\\/(hesabim|account)\\b/.test(url.pathname)) return;

            // Sayfalar: önce ağ. Bir içerik sitesinde önbellekten gelen sayfa
            // bayat sayfadır.
            if (request.mode === 'navigate') {
                event.respondWith(
                    fetch(request)
                        .then((response) => {
                            const copy = response.clone();
                            caches.open(CACHE).then((cache) => cache.put(request, copy));
                            return response;
                        })
                        .catch(() => caches.match(request).then((cached) => cached || caches.match(OFFLINE_URL)))
                );
                return;
            }

            // Varlıklar: önbellekten ver, arkada tazele.
            //
            // Kendi dosyalarımızın adresi sürüm damgası taşıyor, ama vendor
            // dosyalarınınki taşımıyor: yalnız "önce önbellek" olsaydı
            // güncellenen bir kütüphane hiçbir zaman yenilenmezdi.
            event.respondWith(
                caches.match(request).then((cached) => {
                    const network = fetch(request).then((response) => {
                        if (response.ok && response.type === 'basic') {
                            const copy = response.clone();
                            caches.open(CACHE).then((cache) => cache.put(request, copy));
                        }
                        return response;
                    }).catch(() => cached);

                    return cached || network;
                })
            );
        });
        JS;
    }

    /**
     * Önbelleğe alınacak kabuk varlıkları — sayfanın istediği adreslerin
     * birebir aynısı.
     *
     * Bu "birebir aynısı" şartı yol üzerinde öğrenildi: önbelleğe damgalı
     * adres konup sayfa damgasız isteyince (ya da tersi) her istek ıskalıyor
     * ve çevrimdışı sayfa stilsiz açılıyordu. Bu yüzden liste, düzenin kendi
     * yazdığı biçimi izliyor: kendi dosyalarımız versioned_asset(), vendor
     * dosyaları düz asset().
     *
     * Liste bilerek kısa. Yazı tipleri ve ikon fontu dışarıda: hepsi
     * megabaytlarca ve siteyi ilk açan herkes bunu kuruluma peşin ödemek
     * zorunda kalırdı. Çevrimdışı sayfası da bu yüzden ikon fontuna değil
     * satır içi SVG'ye dayanıyor.
     *
     * @return list<string>
     */
    public function shellAssets(): array
    {
        $versioned = ['css/app.css', 'js/app.js'];
        $plain = ['assets/vendor/bootstrap/bootstrap.min.css', 'assets/vendor/bootstrap/bootstrap.bundle.min.js'];

        $urls = [];

        foreach ($versioned as $path) {
            if (File::exists(public_path($path))) {
                $urls[] = $this->toRelative(versioned_asset($path));
            }
        }

        foreach ($plain as $path) {
            if (File::exists(public_path($path))) {
                $urls[] = $this->toRelative(asset($path));
            }
        }

        return $urls;
    }

    /**
     * Mutlak adresi köke göre hâle indirger; ters vekil arkasında alan adı
     * değiştiğinde önbellek anahtarı bozulmasın.
     */
    private function toRelative(string $url): string
    {
        $parts = parse_url($url);

        return ($parts['path'] ?? $url) . (isset($parts['query']) ? '?' . $parts['query'] : '');
    }

    /**
     * Önbellek sürümü: kabuk varlıklarının en son değişme zamanı.
     *
     * Elle yazılan sürüm numarası unutulur; dosyanın kendisi unutmuyor.
     */
    public function assetVersion(): string
    {
        $latest = 0;

        foreach (['css/app.css', 'js/app.js'] as $path) {
            if (File::exists(public_path($path))) {
                $latest = max($latest, (int) File::lastModified(public_path($path)));
            }
        }

        return (string) $latest;
    }

    /**
     * İkonlar yoksa ya da kaynak değiştiyse yeniden üretiliyor.
     *
     * Kaynağın imzası ikonların yanında bir dosyada duruyor; her istekte
     * yeniden üretmek, manifest'i her açan için iki görüntü işlemi demekti.
     */
    private function ensureIcons(): void
    {
        $source = $this->iconSource();
        $dir = rtrim((string) config('uploads.path', public_path('uploads')), '/') . '/' . self::ICON_DIR;
        $stamp = $dir . '/source.txt';
        $signature = $source . '|' . (File::exists($source) ? File::lastModified($source) : 0);

        if (File::exists($stamp) && File::get($stamp) === $signature) {
            return;
        }

        if (! File::exists($source)) {
            return;
        }

        File::ensureDirectoryExists($dir, 0755);

        foreach (self::ICON_SIZES as $size) {
            $this->uploads->writeSquarePng($source, $dir . '/icon-' . $size . '.png', $size);
        }

        File::put($stamp, $signature);
    }

    /**
     * İkonun kaynağı: panelden yüklenen görsel, yoksa kit'in kendi logosu.
     */
    private function iconSource(): string
    {
        foreach (['pwa_icon', 'site_logo', 'site_favicon'] as $key) {
            $value = Setting::getValue($key);

            if (is_string($value) && $value !== '') {
                $path = rtrim((string) config('uploads.path', public_path('uploads')), '/') . '/' . ltrim($value, '/');

                if (File::exists($path)) {
                    return $path;
                }
            }
        }

        return public_path('images/logo.png');
    }
}
