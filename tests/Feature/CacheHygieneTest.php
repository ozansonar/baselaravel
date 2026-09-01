<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Services\CachePurger;
use App\Services\FragmentCache;
use App\Services\MenuService;
use App\Support\CacheKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Önbellek hijyeni.
 *
 * Üç ayrı kusur aynı kökten geliyordu: silinecek anahtarların adları önceden
 * bilinmiyor, her sürücü etiket desteklemiyor ve anahtarlar otuz ayrı yerde
 * dizge olarak yazılıydı. Sonuç, analitik ekranındaki bir yenilemenin bütün
 * önbelleği (ayarlar, çeviriler, site haritası, dil listesi, ön yüz içeriği)
 * silmesiydi.
 *
 * Buradaki sınavların ortak sorusu: **bir küme silindiğinde yalnız o küme mi
 * gidiyor?**
 */
class CacheHygieneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
        $this->seed(\Database\Seeders\LanguageSeeder::class);
    }

    // ── Önek bazlı temizlik (S-12) ──

    /**
     * Kusurun kendisi: analitik önbelleği düşerken ayarlar, site haritası ve
     * dil listesi ayakta kalmalı.
     */
    public function test_flushing_analytics_leaves_the_rest_of_the_cache_alone(): void
    {
        Cache::put(CacheKeys::PREFIX_ANALYTICS . 'stats.abc', 'analitik', 600);
        Cache::put(CacheKeys::PREFIX_ANALYTICS . 'daily_chart.def', 'analitik', 600);

        Cache::put(CacheKeys::SETTINGS_ALL, 'ayarlar', 600);
        Cache::put(CacheKeys::SITEMAP_URLS, 'site haritası', 600);
        Cache::put(CacheKeys::LANGUAGES_ACTIVE, 'diller', 600);

        app(AnalyticsService::class)->flushCache();

        $this->assertNull(Cache::get(CacheKeys::PREFIX_ANALYTICS . 'stats.abc'));
        $this->assertNull(Cache::get(CacheKeys::PREFIX_ANALYTICS . 'daily_chart.def'));

        $this->assertSame('ayarlar', Cache::get(CacheKeys::SETTINGS_ALL));
        $this->assertSame('site haritası', Cache::get(CacheKeys::SITEMAP_URLS));
        $this->assertSame('diller', Cache::get(CacheKeys::LANGUAGES_ACTIVE));
    }

    /**
     * Analitik ekranı gerçekten yazdığı anahtarları silebiliyor mu?
     *
     * Önceki test elle konmuş anahtarlarla çalışıyor; bu, servisin kendi
     * ürettiği anahtarları kullanıyor — önek gerçekten tutarlı mı sorusu.
     */
    public function test_the_analytics_screen_can_clear_what_it_wrote(): void
    {
        $analytics = app(AnalyticsService::class);

        $analytics->getStats(now()->subDays(7), now());
        $analytics->getDailyChart(now()->subDays(7), now());

        Cache::put(CacheKeys::SETTINGS_ALL, 'ayarlar', 600);

        $analytics->flushCache();

        $this->assertSame('ayarlar', Cache::get(CacheKeys::SETTINGS_ALL));
        $this->assertSame(
            [],
            $this->keysStartingWith(CacheKeys::PREFIX_ANALYTICS),
            'Analitik anahtarları temizlenmedi.',
        );
    }

    /**
     * Önek eşleşmesi harfi harfine olmalı: alt çizgi joker sayılırsa
     * silinmemesi gereken anahtarlar gider.
     */
    public function test_an_underscore_in_the_prefix_is_taken_literally(): void
    {
        Cache::put('custom_routes.map', 'kalmalı', 600);
        Cache::put('customXroutes.map', 'gitmeli mi? hayır', 600);

        app(CachePurger::class)->forgetPrefix('customX');

        $this->assertSame('kalmalı', Cache::get('custom_routes.map'));
        $this->assertNull(Cache::get('customXroutes.map'));
    }

    public function test_purging_an_empty_prefix_is_harmless(): void
    {
        Cache::put(CacheKeys::SETTINGS_ALL, 'ayarlar', 600);

        app(CachePurger::class)->forgetPrefix('hicbir-sey.');

        $this->assertSame('ayarlar', Cache::get(CacheKeys::SETTINGS_ALL));
    }

    // ── Anahtar kaydı (S-13) ──

    /**
     * Anahtarlar tek yerde toplandı; kodda dizge olarak yazılmış anahtar
     * kalmamalı — kalırsa bir harf farkla ikinci bir anahtar doğar ve o hiçbir
     * zaman temizlenmez.
     */
    public function test_no_cache_key_is_written_as_a_bare_string(): void
    {
        $offenders = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS),
        );

        $scanned = 0;

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace(base_path() . '/', '', $file->getPathname());

            // Kaydın kendisi elbette dizge taşıyor.
            if (str_ends_with($relative, 'Support/CacheKeys.php')) {
                continue;
            }

            ++$scanned;
            $source = (string) file_get_contents($file->getPathname());

            preg_match_all(
                "/Cache::(?:forget|remember|rememberForever|put|get|has)\(\s*'([^']+)'/",
                $source,
                $matches,
                PREG_OFFSET_CAPTURE,
            );

            foreach ($matches[1] as [$key, $offset]) {
                $line = substr_count(substr($source, 0, $offset), "\n") + 1;
                $offenders[] = "{$relative}:{$line}  '{$key}'";
            }
        }

        $this->assertGreaterThan(100, $scanned, 'app/ dizini taranamadı.');

        sort($offenders);

        $this->assertSame(
            [],
            $offenders,
            "Dizge olarak yazılmış önbellek anahtarı — App\\Support\\CacheKeys kullanın:\n  "
                . implode("\n  ", $offenders),
        );
    }

    /**
     * Önek taşıyan anahtarlar doğrudan `Cache::` ile yazılmamalı.
     *
     * Dosya sürücüsünde anahtar adları diskte durmuyor — dosya adı bir hash ve
     * içeriği yalnız değer. Orada "şu önekle başlayan her şeyi sil" diye bir
     * sorgu yok; temizlik, yazarken tutulan kayıttan yürüyor. Yani `Cache::put`
     * ile doğrudan yazılan bir `analytics.*` anahtarı kayda girmiyor ve o
     * sürücüde hiçbir zaman temizlenmiyor — sessizce bayat kalıyor.
     *
     * `CachePurger::rememberWithin()` hem yazıyor hem kaydediyor; önekli
     * anahtarların tek doğru yazma yolu o.
     */
    public function test_prefixed_keys_are_only_written_through_the_purger(): void
    {
        $offenders = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace(base_path() . '/', '', $file->getPathname());

            // Kayıt tutan servisin kendisi doğal olarak Cache::put çağırıyor.
            if (str_ends_with($relative, 'Services/CachePurger.php')) {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());

            foreach (CacheKeys::prefixes() as $prefix) {
                preg_match_all(
                    "/Cache::(?:remember|rememberForever|put)\(\s*[\"']" . preg_quote($prefix, '/') . "/",
                    $source,
                    $matches,
                    PREG_OFFSET_CAPTURE,
                );

                foreach ($matches[0] as [, $offset]) {
                    $line = substr_count(substr($source, 0, $offset), "\n") + 1;
                    $offenders[] = "{$relative}:{$line}  '{$prefix}…'";
                }
            }
        }

        sort($offenders);

        $this->assertSame(
            [],
            $offenders,
            "Önekli anahtar doğrudan yazılmış — CachePurger::rememberWithin() kullanın:\n  "
                . implode("\n  ", $offenders),
        );
    }

    /**
     * İçerik değişti sinyali tek yerden okunuyor: yeni bir içerik türü
     * eklendiğinde neyin düşeceği orada güncelleniyor.
     */
    public function test_the_content_keys_are_cleared_together(): void
    {
        foreach (CacheKeys::contentKeys() as $key) {
            Cache::put($key, 'bayat', 600);
        }

        Cache::put(CacheKeys::SETTINGS_ALL, 'ayarlar', 600);

        app(CachePurger::class)->forgetContent();

        foreach (CacheKeys::contentKeys() as $key) {
            $this->assertNull(Cache::get($key), "{$key} temizlenmedi.");
        }

        // Ayarlar içerik değil: içerik kaydedildi diye düşmemeli.
        $this->assertSame('ayarlar', Cache::get(CacheKeys::SETTINGS_ALL));
    }

    // ── Parça önbelleği (S-14) ──

    public function test_a_fragment_is_rendered_once_and_served_from_the_cache(): void
    {
        $cache = app(FragmentCache::class);
        $renders = 0;

        $render = function () use (&$renders): string {
            ++$renders;

            return '<footer>alt bilgi</footer>';
        };

        $first = $cache->remember('deneme', ['tr'], $render);
        $second = $cache->remember('deneme', ['tr'], $render);

        $this->assertSame('<footer>alt bilgi</footer>', $first);
        $this->assertSame($first, $second);
        $this->assertSame(1, $renders, 'Parça ikinci istekte yeniden çizildi.');
    }

    /**
     * Aynı parçanın farklı dilleri karışmamalı.
     */
    public function test_the_key_parts_separate_the_versions(): void
    {
        $cache = app(FragmentCache::class);

        $cache->remember('deneme', ['tr'], static fn (): string => 'türkçe');
        $english = $cache->remember('deneme', ['en'], static fn (): string => 'english');

        $this->assertSame('english', $english);
        $this->assertSame('türkçe', $cache->remember('deneme', ['tr'], static fn (): string => 'yeniden'));
    }

    /**
     * Oturum açmış kullanıcının gördüğü parça saklanamaz: kendi adını taşıyan
     * bir menü sonraki ziyaretçiye gösterilemez.
     */
    public function test_nothing_is_cached_for_a_signed_in_user(): void
    {
        $this->actingAs($this->member());

        $cache = app(FragmentCache::class);
        $renders = 0;

        $render = function () use (&$renders): string {
            ++$renders;

            return 'kişiye özel';
        };

        $cache->remember('deneme', ['tr'], $render);
        $cache->remember('deneme', ['tr'], $render);

        $this->assertSame(2, $renders, 'Oturum açmış kullanıcı için parça saklandı.');
    }

    /**
     * En kritik kapı: kişiye özel iz taşıyan çıktı hiç saklanmamalı.
     *
     * Bugün böyle bir parça yok, ama yarın birinin alt bilgiye form koyması
     * CSRF anahtarını bütün ziyaretçilere dağıtırdı — sessizce.
     */
    public function test_output_carrying_a_csrf_token_is_never_stored(): void
    {
        $cache = app(FragmentCache::class);
        $renders = 0;

        $render = function () use (&$renders): string {
            ++$renders;

            return '<form><input type="hidden" name="_token" value="gizli"></form>';
        };

        $cache->remember('formlu', ['tr'], $render);
        $cache->remember('formlu', ['tr'], $render);

        $this->assertSame(2, $renders, 'CSRF anahtarı taşıyan parça önbelleğe alındı.');
    }

    /**
     * Aynı kapı, içerik güvenlik politikasının anahtarı için de kapalı: bayat
     * bir nonce, betiği çalıştırılamaz hâle getirir.
     */
    public function test_output_carrying_a_csp_nonce_is_never_stored(): void
    {
        $cache = app(FragmentCache::class);
        $renders = 0;

        $render = function () use (&$renders): string {
            ++$renders;

            return '<script nonce="abc">console.log(1)</script>';
        };

        $cache->remember('betikli', ['tr'], $render);
        $cache->remember('betikli', ['tr'], $render);

        $this->assertSame(2, $renders, 'Nonce taşıyan parça önbelleğe alındı.');
    }

    /**
     * Menü değişince alt bilgi bayat kalmamalı — ziyaretçi bir saat boyunca
     * eski bağlantıları görürdü, hata vermeden.
     */
    public function test_changing_a_menu_drops_the_rendered_fragments(): void
    {
        $cache = app(FragmentCache::class);

        $cache->remember('partials.footer', ['tr'], static fn (): string => 'eski alt bilgi');

        app(MenuService::class)->clearCache('footer');

        $this->assertSame(
            'yeni alt bilgi',
            $cache->remember('partials.footer', ['tr'], static fn (): string => 'yeni alt bilgi'),
        );
    }

    public function test_changing_a_setting_drops_the_rendered_fragments(): void
    {
        $cache = app(FragmentCache::class);

        $cache->remember('partials.footer', ['tr'], static fn (): string => 'eski alt bilgi');

        Setting::clearSettingsCache();

        $this->assertSame(
            'yeni alt bilgi',
            $cache->remember('partials.footer', ['tr'], static fn (): string => 'yeni alt bilgi'),
        );
    }

    /**
     * Uçtan uca: gezinti gerçekten önbellekten geliyor ve sayfa aynı kalıyor.
     */
    public function test_the_navigation_survives_a_round_trip_through_the_cache(): void
    {
        $first = $this->get('/tr');
        $first->assertOk();

        $this->assertNotSame(
            [],
            $this->keysStartingWith(CacheKeys::PREFIX_FRAGMENT),
            'Gezinti parçası önbelleğe alınmadı.',
        );

        $second = $this->get('/tr');
        $second->assertOk();

        // İki yanıttaki gezinti birebir aynı olmalı; önbellekten gelen parça
        // ötekinden farklıysa bir yerde kişiye özel bir şey vardır.
        $this->assertSame(
            $this->navigationOf($first->getContent()),
            $this->navigationOf($second->getContent()),
        );
    }

    /**
     * Alt bilgi bilinçli olarak önbelleğe alınmıyor: içinde bülten formu ve
     * dolayısıyla CSRF anahtarı var. Bu testin işi kararın geri alınmadığını
     * bekçilik etmek — biri alt bilgiyi önbelleğe alırsa o anahtar bütün
     * ziyaretçilere dağılırdı.
     */
    public function test_the_footer_is_deliberately_not_cached(): void
    {
        $this->get('/tr')->assertOk();

        $fragments = $this->keysStartingWith(CacheKeys::PREFIX_FRAGMENT);

        foreach ($fragments as $key) {
            $this->assertStringNotContainsString('footer', $key, 'Alt bilgi önbelleğe alınmış.');
        }
    }

    /**
     * Oturum açmış kullanıcı önbellekten gelen gezintiyi görmemeli: kendi adı
     * ve çıkış formu orada.
     */
    public function test_a_member_gets_a_freshly_rendered_navigation(): void
    {
        // Önce misafir olarak önbelleği doldur.
        $this->get('/tr')->assertOk();

        $member = $this->member();
        $response = $this->actingAs($member)->get('/tr');

        $response->assertOk();
        $response->assertSee($member->full_name, escape: false);
    }

    // ── Yardımcılar ──

    /**
     * @return list<string>
     */
    private function keysStartingWith(string $prefix): array
    {
        // Test paketi dizi sürücüsünde koşuyor: anahtarlar depoda düz duruyor.
        $store = Cache::getStore();

        if (! method_exists($store, 'all')) {
            return [];
        }

        /** @var array<string, mixed> $all */
        $all = $store->all();

        return array_values(array_filter(
            array_keys($all),
            static fn (string $key): bool => str_starts_with($key, $prefix),
        ));
    }

    private function navigationOf(string $html): string
    {
        $start = strpos($html, '<nav');

        return $start === false ? '' : substr($html, $start, 2000);
    }

    private function member(): User
    {
        $user = User::create([
            'first_name' => 'Onbellek',
            'last_name'  => 'Uye',
            'email'      => 'onbellek@example.test',
            'password'   => 'sifre-123456',
            'is_active'  => true,
        ]);
        $user->markEmailAsVerified();
        $user->roles()->attach(Role::where('slug', 'user')->firstOrFail()->id);

        return $user->fresh();
    }
}
