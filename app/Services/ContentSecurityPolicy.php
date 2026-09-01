<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Str;

/**
 * İçerik güvenlik politikasını kurar ve istek boyunca nonce'u taşır.
 *
 * Politika iki parçadan doğuyor: sabit iskelet (hangi yönerge neye izin verir)
 * ve yapılandırmadan gelen alan adları. İkisini ayırmanın sebebi, projeye yeni
 * bir üçüncü taraf eklendiğinde politikanın kendisine dokunulmaması — o zaman
 * bir sonraki güncellemede eklenen satır kaybolurdu.
 *
 * Nonce istek başına bir kez üretilip burada tutuluyor; hem başlığa hem de
 * sayfadaki satır içi betiklere aynı değer gidiyor. Servis istek kapsamında
 * (scoped) bağlanıyor, yani aynı istekte kaç kez sorulursa sorulsun aynı
 * anahtar dönüyor.
 */
final class ContentSecurityPolicy
{
    private ?string $nonce = null;

    /**
     * Bu isteğin bir kerelik anahtarı.
     *
     * 16 bayt rastgelelik base64'e çevriliyor — CSP spesifikasyonu en az 128
     * bit öneriyor. Tahmin edilebilir bir nonce, nonce olmamakla aynı şey.
     */
    public function nonce(): string
    {
        return $this->nonce ??= Str::random(24);
    }

    public function enabled(): bool
    {
        return (bool) config('security.csp.enabled', true);
    }

    /**
     * Politikanın yazılacağı başlık adı.
     *
     * Rapor modunda tarayıcı hiçbir şeyi engellemiyor, yalnızca ihlalleri
     * bildiriyor — yeni bir politikayı canlıya zarar vermeden denemenin yolu.
     */
    public function headerName(): string
    {
        return (bool) config('security.csp.report_only', false)
            ? 'Content-Security-Policy-Report-Only'
            : 'Content-Security-Policy';
    }

    /**
     * Başlığın değeri.
     *
     * Panel ile ön yüz aynı politikayı paylaşmıyor: panelde zengin metin
     * editörü (TinyMCE) kendi stillerini çalışma anında yazıyor ve `blob:`
     * kaynaklı görsel üretiyor. Ön yüze o izinleri vermek, ziyaretçinin
     * gördüğü yüzeyi gereksiz genişletirdi.
     */
    public function header(bool $forAdmin = false): string
    {
        $nonce = "'nonce-" . $this->nonce() . "'";

        $directives = [
            // Hiçbir şey açıkça izin verilmediyse yasak.
            'default-src' => ["'self'"],

            // Satır içi betikler yalnız bu isteğin anahtarını taşıyorsa
            // çalışıyor. 'strict-dynamic' bilinçli olarak yok: onunla birlikte
            // alan adı beyaz listesi tarayıcı tarafından yok sayılıyor ve
            // reCAPTCHA'nın kendi yüklediği alt betikler kırılıyor.
            'script-src' => array_merge(
                ["'self'", $nonce],
                $this->vendorSources('script'),
                $this->extraSources('script'),
            ),

            // Nitelik olarak yazılan olay işleyicileri tamamen yasak.
            //
            // Bir süre burada `script-src-attr 'unsafe-inline'` duruyordu:
            // panelde 219 satır içi işleyici vardı (`onclick`, `onchange`,
            // `oninput`) ve nitelik değeri betiğin kendisi olduğu için oraya
            // nonce konulamıyordu. Hepsi `data-*` kancalarına ve merkezi bir
            // bağlayıcıya taşındıktan sonra taviz kalktı — artık enjekte
            // edilen bir `onerror=` niteliği de çalışmıyor.
            'script-src-attr' => ["'none'"],

            // Stil tarafında nonce yetmiyor: Bootstrap'in konumlandırıcısı
            // (Popper) açılır menü ve ipuçlarını yerleştirirken elemanın
            // `style` niteliğini doğrudan yazıyor. Bunu engellemek panelin
            // yarısını kırar; betik tarafındaki koruma ise etkilenmiyor.
            'style-src' => array_merge(
                ["'self'", "'unsafe-inline'"],
                $this->vendorSources('style'),
                $this->extraSources('style'),
            ),

            'img-src' => array_merge(
                ["'self'", 'data:', 'blob:'],
                $this->vendorSources('img'),
                $this->extraSources('img'),
            ),

            'font-src' => array_merge(
                ["'self'", 'data:'],
                $this->extraSources('font'),
            ),

            'connect-src' => array_merge(
                ["'self'"],
                $this->vendorSources('connect'),
                $this->extraSources('connect'),
            ),

            'frame-src' => array_merge(
                ["'self'"],
                $this->vendorSources('frame'),
                $this->extraSources('frame'),
            ),

            // Eklenti ve gömülü nesne yok; ikisi de tarihsel saldırı yüzeyi.
            'object-src' => ["'none'"],

            // Sayfaya enjekte edilen bir <base> etiketi bütün göreli adresleri
            // saldırganın sunucusuna çevirebilir.
            'base-uri' => ["'self'"],

            // Form gönderimi başka bir alana kaçırılamasın.
            'form-action' => ["'self'"],

            // X-Frame-Options'ın modern karşılığı; ikisi birlikte duruyor
            // çünkü eski tarayıcılar yalnız ötekini biliyor.
            'frame-ancestors' => ["'self'"],
        ];

        if ($forAdmin) {
            // TinyMCE görsel önizlemesini ve kendi araç çubuğu ikonlarını
            // blob/data kaynaklarından üretiyor; editörün iskeleti de bir
            // iframe içinde açılıyor.
            $directives['frame-src'][] = 'blob:';
            $directives['worker-src'] = ["'self'", 'blob:'];
        }

        $policy = [];

        foreach ($directives as $name => $sources) {
            $unique = array_values(array_unique(array_filter($sources)));

            if ($unique !== []) {
                $policy[] = $name . ' ' . implode(' ', $unique);
            }
        }

        if ((bool) config('security.csp.report_route', true)) {
            $policy[] = 'report-uri ' . route('csp.report');
        }

        return implode('; ', $policy);
    }

    /**
     * Yapılandırmadaki üçüncü taraf listelerinden bir yönergenin kaynakları.
     *
     * @return list<string>
     */
    private function vendorSources(string $directive): array
    {
        /** @var array<string, array<string, list<string>>> $vendors */
        $vendors = config('security.csp.vendors', []);

        $sources = [];

        foreach ($vendors as $vendor) {
            // Koşullu sağlayıcı: ilgili ayar boşken alan adı hiç yayılmıyor.
            // Kullanılmayan bir kaynağı sürekli açık tutmak, politikayı
            // gereksiz yere genişletmek olurdu.
            if (($vendor['requires'] ?? null) !== null && ! $this->settingIsFilled((string) $vendor['requires'])) {
                continue;
            }

            foreach ($vendor[$directive] ?? [] as $source) {
                $sources[] = $source;
            }
        }

        return $sources;
    }

    /**
     * Bir ayar dolu mu?
     *
     * Politika her istekte kuruluyor; ayarlar zaten önbellekte ve istek içinde
     * bir kez okunuyor, yani bu soru bir dizi araması. Veritabanı yoksa
     * (taze klon, göç öncesi) cevap "hayır" — politika dar kalıyor, ki
     * güvenli taraf o.
     */
    private function settingIsFilled(string $key): bool
    {
        try {
            $value = \App\Models\Setting::getValue($key);
        } catch (\Throwable) {
            return false;
        }

        return $value !== null && $value !== '';
    }

    /**
     * `.env` üzerinden eklenen kaynaklar.
     *
     * @return list<string>
     */
    private function extraSources(string $directive): array
    {
        /** @var list<string> $extra */
        $extra = config('security.csp.extra.' . $directive, []);

        return array_values(array_filter(array_map('trim', $extra)));
    }
}
