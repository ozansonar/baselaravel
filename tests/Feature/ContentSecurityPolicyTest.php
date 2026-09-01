<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\ContentSecurityPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * İçerik güvenlik politikası.
 *
 * Politikanın değeri tek bir şeye bağlı: sayfadaki her satır içi betiğin
 * başlıktaki anahtarı taşıması. Bir tanesi taşımazsa iki kötü sonuçtan biri
 * çıkıyor — ya o betik çalışmıyor ve sayfa kırılıyor, ya da birileri
 * `'unsafe-inline'` ekleyip politikayı tamamen anlamsızlaştırıyor.
 *
 * Bu yüzden sınavın merkezinde tek soru var: **başlıktaki nonce ile sayfadaki
 * nonce aynı mı, ve nonce'suz betik kaldı mı?**
 */
class ContentSecurityPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
        $this->seed(\Database\Seeders\LanguageSeeder::class);
    }

    /**
     * Politikadaki alan adı listesi.
     */
    private function policy(): string
    {
        return (string) $this->get('/tr')->assertOk()->headers->get('Content-Security-Policy');
    }

    /**
     * Bir yönergenin içeriği — `script-src ...` parçası.
     */
    private function directive(string $name): string
    {
        foreach (explode(';', $this->policy()) as $part) {
            $part = trim($part);

            if (str_starts_with($part, $name . ' ')) {
                return $part;
            }
        }

        return '';
    }

    // ── Üçüncü taraflar: kullanılmayan aracın kapısı açık durmuyor ──

    /**
     * Bu bölüm nonce'un tek gerçek zayıf noktasını bekliyor.
     *
     * Politika "jetonu olan satır içi betikler **VEYA** şu alan adları" diyor;
     * ikinci kısım jeton koşulunu atlıyor. `googletagmanager.com` ve
     * `www.google.com` bilinen CSP atlatma kaynakları — sayfaya HTML sokabilen
     * biri jetonu hiç bilmeden o hostlardan betik yükleyip korumayı devre dışı
     * bırakabiliyor.
     *
     * Kullanılmayan bir aracın alan adını politikada tutmak, kazanç olmadan
     * saldırı yüzeyini genişletmek demek.
     */
    public function test_a_fresh_install_allows_no_third_party_script_host(): void
    {
        $script = $this->directive('script-src');

        foreach ([
            'googletagmanager.com',
            'google-analytics.com',
            'www.google.com',
            'gstatic.com',
        ] as $host) {
            $this->assertStringNotContainsString(
                $host,
                $script,
                "Kurulumda kullanılmayan {$host} politikada açık duruyor.",
            );
        }
    }

    public function test_analytics_hosts_appear_once_an_id_is_entered(): void
    {
        Setting::setValue('google_analytics_id', 'G-SINAMA123');

        $script = $this->directive('script-src');

        $this->assertStringContainsString('https://www.googletagmanager.com', $script);
        $this->assertStringContainsString('https://www.google-analytics.com', $script);
    }

    /**
     * GA4 çerçeve kullanmıyor; `noscript` iframe'i basan Tag Manager.
     * İkisi tek blokta dursaydı yalnız GA4 kullanan kurulum, hiç basmadığı bir
     * çerçeveye izin vermiş olurdu.
     */
    public function test_analytics_alone_does_not_open_a_frame(): void
    {
        Setting::setValue('google_analytics_id', 'G-SINAMA123');

        $this->assertStringNotContainsString('googletagmanager.com', $this->directive('frame-src'));
    }

    public function test_tag_manager_opens_the_frame_it_actually_prints(): void
    {
        Setting::setValue('google_tag_manager_id', 'GTM-SINAMA');

        $this->assertStringContainsString('https://www.googletagmanager.com', $this->directive('frame-src'));
    }

    // ── reCAPTCHA: koşul bileşik ──

    /**
     * @param array<string, string> $extra
     */
    private function enableRecaptcha(array $extra = []): void
    {
        foreach (array_merge([
            'recaptcha_enabled'    => '1',
            'recaptcha_site_key'   => 'site-anahtari',
            'recaptcha_secret_key' => 'gizli-anahtar',
        ], $extra) as $key => $value) {
            Setting::setValue($key, $value);
        }
    }

    public function test_recaptcha_hosts_appear_when_it_is_switched_on(): void
    {
        $this->enableRecaptcha();

        $script = $this->directive('script-src');

        $this->assertStringContainsString('https://www.google.com', $script);
        $this->assertStringContainsString('https://www.gstatic.com', $script);
    }

    /**
     * Kapatılmış bir anahtar "dolu" sayılmamalı.
     *
     * Anahtarlar `'0'` olarak saklanıyor ve `'0'` boş dizge değil. Bu ayrım
     * yapılmasaydı, reCAPTCHA'yı kapatan bir kurulumda `www.google.com`
     * politikada açık kalırdı — üstelik anahtarlar kayıtlı olduğu için
     * yönetici aracı kapattığını sanarak.
     */
    public function test_switching_recaptcha_off_closes_its_hosts(): void
    {
        $this->enableRecaptcha(['recaptcha_enabled' => '0']);

        $this->assertStringNotContainsString('https://www.google.com', $this->directive('script-src'));
    }

    /**
     * Politika ile sayfa aynı soruyu sormalı.
     *
     * Ayrışırlarsa iki kötü sonuçtan biri çıkıyor: betik basılıyor ama CSP onu
     * engelliyor (araç sessizce çalışmaz, sebebi görünmez), ya da betik hiç
     * basılmıyor ama alan adı açık duruyor (bedavaya yüzey). reCAPTCHA'nın
     * koşulu bileşik olduğu için burada ayrışma riski en yüksek.
     */
    public function test_the_policy_and_the_page_agree_on_recaptcha(): void
    {
        $needle = 'https://www.google.com/recaptcha/api.js';

        $this->get('/tr')->assertOk()->assertDontSee($needle, false);
        $this->assertStringNotContainsString('https://www.google.com', $this->directive('script-src'));

        $this->enableRecaptcha();

        $this->get('/tr')->assertOk()->assertSee($needle, false);
        $this->assertStringContainsString('https://www.google.com', $this->directive('script-src'));
    }

    // ── Başlık ──

    public function test_the_policy_is_sent_with_every_page(): void
    {
        $response = $this->get('/tr');

        $response->assertOk();
        $response->assertHeader('Content-Security-Policy');

        $policy = (string) $response->headers->get('Content-Security-Policy');

        foreach (["default-src 'self'", "object-src 'none'", "base-uri 'self'",
            "form-action 'self'", "frame-ancestors 'self'"] as $directive) {
            $this->assertStringContainsString($directive, $policy);
        }
    }

    /**
     * Nonce tek kullanımlık olmalı; tahmin edilebilir bir anahtar, anahtar
     * olmamakla aynı şey.
     *
     * İstek sınırı elle simüle ediliyor: test paketi bütün istekleri tek bir
     * uygulama örneği üzerinde koşturuyor, oysa üretimde her istek kendi
     * sürecinde doğuyor. `forgetScopedInstances()` çerçevenin uzun ömürlü
     * süreçlerde (kuyruk işçisi, Octane) her istek başında çağırdığı metot —
     * yani burada taklit edilen şey uydurma değil, gerçek yaşam döngüsü.
     */
    public function test_the_nonce_changes_on_every_request(): void
    {
        $first = $this->nonceOf($this->get('/tr')->headers->get('Content-Security-Policy'));

        $this->app->forgetScopedInstances();

        $second = $this->nonceOf($this->get('/tr')->headers->get('Content-Security-Policy'));

        $this->assertNotSame('', $first);
        $this->assertNotSame($first, $second);
        // 128 bitin altındaki bir nonce kaba kuvvetle tahmin edilebilir.
        $this->assertGreaterThanOrEqual(16, strlen($first));
    }

    /**
     * Aynı istek içinde ise nonce sabit kalmalı — başlığa yazılan anahtar ile
     * sayfadaki betiklerin taşıdığı anahtar aynı olmak zorunda.
     */
    public function test_the_nonce_stays_the_same_within_one_request(): void
    {
        $response = $this->get('/tr');

        $nonce = $this->nonceOf($response->headers->get('Content-Security-Policy'));

        $this->assertNotSame('', $nonce);
        $this->assertStringContainsString('nonce="' . $nonce . '"', (string) $response->getContent());
    }

    /**
     * Politikanın tek gerçek sınavı: sayfadaki betikler başlıktaki anahtarı
     * taşıyor mu, ve taşımayan kaldı mı?
     */
    public function test_every_inline_script_on_a_page_carries_the_header_nonce(): void
    {
        $pages = ['/tr', '/tr/blog', '/tr/iletisim', '/tr/giris', '/tr/kayit'];

        foreach ($pages as $page) {
            $response = $this->get($page);
            $response->assertOk();

            $nonce = $this->nonceOf($response->headers->get('Content-Security-Policy'));
            $ruleless = $this->inlineScriptsWithoutNonce($response->getContent(), $nonce);

            $this->assertSame([], $ruleless, "{$page} sayfasında anahtarsız betik var.");
        }
    }

    public function test_the_panel_pages_carry_the_nonce_too(): void
    {
        $admin = $this->admin();

        // Panelin en ağır betik yüzeyi: zengin metin editörü, dosya seçici,
        // grafikler ve toplu yükleme aynı sayfalarda.
        $screens = ['/admin', '/admin/blog-posts/create', '/admin/settings',
            '/admin/analytics', '/admin/gallery-items/toplu-yukleme'];

        foreach ($screens as $screen) {
            $response = $this->actingAs($admin)->get($screen);
            $response->assertOk();

            $nonce = $this->nonceOf($response->headers->get('Content-Security-Policy'));

            $this->assertSame(
                [],
                $this->inlineScriptsWithoutNonce($response->getContent(), $nonce),
                "{$screen} ekranında anahtarsız betik var.",
            );
        }
    }

    /**
     * Panel, editörün ihtiyaç duyduğu birkaç ek kaynağa izin veriyor; ön yüz
     * aynı izinleri almamalı — kazanç olmadan yüzeyi genişletirdi.
     */
    public function test_the_panel_policy_is_wider_than_the_public_one(): void
    {
        $public = (string) $this->get('/tr')->headers->get('Content-Security-Policy');
        $panel = (string) $this->actingAs($this->admin())
            ->get('/admin')->headers->get('Content-Security-Policy');

        $this->assertStringNotContainsString('worker-src', $public);
        $this->assertStringContainsString('worker-src', $panel);
    }

    /**
     * Kaldırılan başlık: güncel hiçbir tarayıcı desteklemiyor ve bazı eski
     * sürümlerde filtrenin kendisi XSS'i kolaylaştırıyordu.
     */
    public function test_the_dead_xss_header_is_no_longer_sent(): void
    {
        $this->get('/tr')->assertHeaderMissing('X-XSS-Protection');
    }

    public function test_the_other_security_headers_are_still_sent(): void
    {
        $response = $this->get('/tr');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    // ── Modlar ──

    /**
     * Rapor modu, yeni bir politikayı canlıya zarar vermeden denemenin yolu:
     * tarayıcı hiçbir şeyi engellemiyor, yalnız ihlalleri bildiriyor.
     */
    public function test_report_only_mode_switches_the_header(): void
    {
        config(['security.csp.report_only' => true]);

        $response = $this->get('/tr');

        $response->assertHeader('Content-Security-Policy-Report-Only');
        $response->assertHeaderMissing('Content-Security-Policy');
    }

    public function test_the_policy_can_be_switched_off_entirely(): void
    {
        config(['security.csp.enabled' => false]);

        $response = $this->get('/tr');

        $response->assertOk();
        $response->assertHeaderMissing('Content-Security-Policy');
        $response->assertHeaderMissing('Content-Security-Policy-Report-Only');
    }

    /**
     * Projeye sonradan eklenen bir araç, politikanın kendisine dokunmadan
     * beyaz listeye girebilmeli.
     */
    public function test_extra_sources_come_from_the_configuration(): void
    {
        config(['security.csp.extra.script' => ['https://ornek.test']]);

        $policy = (string) $this->get('/tr')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('https://ornek.test', $policy);
    }

    // ── Rapor ucu ──

    public function test_a_violation_report_reaches_the_log(): void
    {
        $log = Log::spy();

        $this->postJson('/csp-ihlali', ['csp-report' => [
            'document-uri'       => 'https://ornek.test/sayfa',
            'violated-directive' => "script-src 'self'",
            'blocked-uri'        => 'inline',
        ]])->assertNoContent();

        $log->shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context): bool => $message === 'CSP ihlali bildirildi'
                && ($context['blocked-uri'] ?? null) === 'inline')
            ->once();
    }

    /**
     * Rapor gövdesi saldırganın yazdığı metni taşıyabiliyor: loga yalnız
     * tanınan alanlar, kırpılmış ve kontrol karakterlerinden arınmış girer.
     */
    public function test_unknown_fields_never_reach_the_log(): void
    {
        $log = Log::spy();

        $this->postJson('/csp-ihlali', ['csp-report' => [
            'blocked-uri' => "inline\n\x00zararlı",
            'script-sample' => str_repeat('A', 5000),
            'uydurma-alan'  => 'buraya yazılmamalı',
        ]])->assertNoContent();

        $log->shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context): bool {
                return ! array_key_exists('uydurma-alan', $context)
                    && ! array_key_exists('script-sample', $context)
                    && ! str_contains((string) $context['blocked-uri'], "\n");
            })
            ->once();
    }

    public function test_a_broken_body_is_swallowed_quietly(): void
    {
        $log = Log::spy();

        $this->call('POST', '/csp-ihlali', [], [], [], [], 'bu json değil')
            ->assertNoContent();

        $log->shouldNotHaveReceived('warning');
    }

    /**
     * Bozuk bir eklenti saniyede yüzlerce rapor gönderebiliyor; sınır olmadan
     * log dosyası şişer.
     */
    public function test_the_report_endpoint_is_rate_limited(): void
    {
        config(['security.csp.report_rate_limit' => 2]);
        RateLimiter::clear('csp-report:127.0.0.1');

        $log = Log::spy();

        foreach (range(1, 5) as $ignored) {
            $this->postJson('/csp-ihlali', ['csp-report' => ['blocked-uri' => 'inline']])
                ->assertNoContent();
        }

        $log->shouldHaveReceived('warning')->twice();
    }

    /**
     * Raporu gönderen bizim formumuz değil, tarayıcının kendisi: oturum çerezi
     * ve dolayısıyla CSRF anahtarı taşımıyor.
     */
    public function test_the_report_endpoint_does_not_ask_for_a_csrf_token(): void
    {
        $this->withMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->postJson('/csp-ihlali', ['csp-report' => ['blocked-uri' => 'inline']])
            ->assertNoContent();
    }

    // ── Bekçi ──

    /**
     * Görünüm ağacında anahtarsız satır içi betik kalmamalı.
     *
     * Sayfa testleri yalnız çizilen sayfaları görüyor; bu tarama, henüz hiçbir
     * testin açmadığı bir ekranda unutulan betiği de yakalıyor.
     */
    public function test_no_view_carries_an_inline_script_without_a_nonce(): void
    {
        $offenders = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS),
        );

        $scanned = 0;

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $relative = str_replace(resource_path('views/'), '', $file->getPathname());

            // Uyarlanmamış hazır tasarımlar Blade'e dönüşmeden kural aranmaz.
            if (str_starts_with($relative, 'admin-theme/')) {
                continue;
            }

            ++$scanned;
            $source = (string) file_get_contents($file->getPathname());

            preg_match_all('/<script(?:\s[^>]*)?>/', $source, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[0] as [$tag, $offset]) {
                if (str_contains($tag, 'src=') || str_contains($tag, 'nonce=')) {
                    continue;
                }

                $line = substr_count(substr($source, 0, $offset), "\n") + 1;
                $offenders[] = "{$relative}:{$line}";
            }
        }

        $this->assertGreaterThan(100, $scanned, 'Görünüm ağacı taranamadı.');

        sort($offenders);

        $this->assertSame(
            [],
            $offenders,
            "Anahtarsız satır içi betik — nonce=\"{{ csp_nonce() }}\" ekleyin:\n  "
                . implode("\n  ", $offenders),
        );
    }

    /**
     * Politika `'unsafe-inline'` ile sulandırılmamalı — betik tarafında o
     * anahtar, CSP'yi olmamakla eşitler.
     */
    public function test_the_script_policy_never_allows_unsafe_inline(): void
    {
        $policy = app(ContentSecurityPolicy::class)->header();

        preg_match('/script-src ([^;]+)/', $policy, $match);

        $this->assertArrayHasKey(1, $match);
        $this->assertStringNotContainsString("'unsafe-inline'", $match[1]);
        $this->assertStringNotContainsString("'unsafe-eval'", $match[1]);
    }

    /**
     * Nitelik olarak yazılan olay işleyicileri tamamen yasak.
     *
     * Bir süre burada `'unsafe-inline'` duruyordu: panelde 219 satır içi
     * işleyici vardı ve nitelik değeri betiğin kendisi olduğu için oraya nonce
     * konulamıyordu. Hepsi `data-*` kancalarına taşındıktan sonra taviz
     * kalktı — bu test onun geri gelmesini engelliyor.
     */
    public function test_attribute_handlers_are_forbidden_outright(): void
    {
        $policy = app(ContentSecurityPolicy::class)->header();

        $this->assertStringContainsString("script-src-attr 'none'", $policy);
        $this->assertStringNotContainsString("script-src-attr 'unsafe-inline'", $policy);
    }

    // ── Yardımcılar ──

    private function nonceOf(?string $policy): string
    {
        return preg_match("/'nonce-([^']+)'/", (string) $policy, $match) === 1 ? $match[1] : '';
    }

    /**
     * Sayfadaki, verilen anahtarı taşımayan satır içi betikler.
     *
     * @return list<string>
     */
    private function inlineScriptsWithoutNonce(string $html, string $nonce): array
    {
        preg_match_all('/<script(?:\s[^>]*)?>/', $html, $matches);

        $offenders = [];

        foreach ($matches[0] as $tag) {
            if (str_contains($tag, 'src=')) {
                continue;
            }

            if ($nonce !== '' && str_contains($tag, 'nonce="' . $nonce . '"')) {
                continue;
            }

            $offenders[] = $tag;
        }

        return $offenders;
    }

    private function admin(): User
    {
        $user = User::create([
            'first_name' => 'Csp',
            'last_name'  => 'Yonetici',
            'email'      => 'csp@example.test',
            'password'   => 'sifre-123456',
            'is_active'  => true,
        ]);
        $user->markEmailAsVerified();
        $user->roles()->attach(Role::where('slug', 'admin')->firstOrFail()->id);

        return $user->fresh();
    }
}
