<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\CacheKeys;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

/**
 * Her sayfada aynı çıkan parçaları çizilmiş hâliyle saklar.
 *
 * Sorgu düzeyinde önbellek zaten iyi kurulmuştu (ayarlar 24 saat, site
 * haritası 1 saat, çeviriler süresiz) ama anonim bir ziyaretçinin gördüğü her
 * sayfa yine tam bir çizim döngüsüydü: menü ağacı, alt bilgi sütunları, dil
 * listesi her istekte yeniden kuruluyordu. İçeriğin ezici çoğunluğu o
 * ziyaretçi için birebir aynı; paylaşımlı hostingde en büyük kazanç burada.
 *
 * ## Neyin önbelleğe alınmayacağı
 *
 * Bir parçayı saklamak, onu **başka ziyaretçilere de** sunmak demek. Bu yüzden
 * üç kapı var ve üçü de kapalıysa parça hiç saklanmıyor:
 *
 *  - **Oturum açmış kullanıcı.** Kendi adını taşıyan bir menü, sonraki
 *    ziyaretçiye gösterilemez.
 *  - **GET olmayan istek.** Form gönderiminden sonra çizilen sayfa o isteğe
 *    özgü.
 *  - **Kişiye özel iz taşıyan çıktı.** CSRF anahtarı ya da CSP nonce'u içeren
 *    bir parça saklanırsa iki ayrı hata birden doğar: başkasının anahtarını
 *    taşıyan form reddedilir, bayat nonce ise betiği çalıştırılamaz hâle
 *    getirir. Çıktı yazılmadan önce denetleniyor — bugün böyle bir parça yok,
 *    ama yarın birinin alt bilgiye form koyması bunu sessizce bozardı.
 */
final class FragmentCache
{
    /** Parça ömrü; içerik değiştiğinde zaten önek bazlı düşürülüyor. */
    private const TTL = 3600;

    /**
     * Kişiye özel iz arayan kalıplar. Biri bulunursa parça saklanmıyor.
     *
     * @var list<string>
     */
    private const PERSONAL_MARKERS = ['name="_token"', 'nonce="', 'csrf-token'];

    public function __construct(
        private readonly CachePurger $purger,
    ) {}

    /**
     * Bir görünümü önbellekten verir; yoksa çizip saklar.
     *
     * `@cachedInclude` direktifinin arkası. Önbellek dolu olduğunda görünüm
     * hiç çizilmiyor — kazanç buradan geliyor, sonucu saklayıp yine de her
     * seferinde çizmek bir şey kazandırmazdı.
     *
     * @param  array<string, mixed> $data Görünüme geçen değişkenler
     * @param  array<int, string|int|null> $keyParts Aynı parçanın farklı
     *         sürümlerini ayıran değerler — dil, aktif rota, sayfa türü
     */
    public function renderCached(string $view, array $data = [], array $keyParts = []): string
    {
        return $this->remember(
            $view,
            $keyParts,
            static fn (): string => view($view, $data)->render(),
        );
    }

    /**
     * Parçayı önbellekten verir; yoksa çizip saklar.
     *
     * @param  array<int, string|int|null> $keyParts Aynı parçanın farklı
     *         sürümlerini ayıran değerler — dil, aktif rota, sayfa türü.
     * @param  Closure(): string $render
     */
    public function remember(string $name, array $keyParts, Closure $render): string
    {
        if (! $this->cacheable()) {
            return $render();
        }

        $key = $this->key($name, $keyParts);
        $cached = Cache::get($key);

        if (is_string($cached)) {
            return $cached;
        }

        $html = $render();

        if ($this->carriesPersonalData($html)) {
            Log::warning('Parça önbelleğe alınmadı: kişiye özel iz taşıyor', ['fragment' => $name]);

            return $html;
        }

        $this->purger->remember(CacheKeys::PREFIX_FRAGMENT, $key);
        Cache::put($key, $html, self::TTL);

        return $html;
    }

    /**
     * Bu istek parça önbelleğinden yararlanabilir mi?
     */
    public function cacheable(): bool
    {
        if (! (bool) config('cache.fragments', true)) {
            return false;
        }

        return Auth::guest() && Request::isMethod('GET');
    }

    /**
     * @param array<int, string|int|null> $keyParts
     */
    private function key(string $name, array $keyParts): string
    {
        $parts = array_map(static fn (mixed $part): string => (string) $part, $keyParts);

        return CacheKeys::PREFIX_FRAGMENT . $name . '.' . md5(implode('|', $parts));
    }

    private function carriesPersonalData(string $html): bool
    {
        return array_any(
            self::PERSONAL_MARKERS,
            static fn (string $marker): bool => str_contains($html, $marker),
        );
    }
}
