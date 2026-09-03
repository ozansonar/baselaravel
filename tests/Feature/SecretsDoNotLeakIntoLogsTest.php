<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\SafeUrl;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Tests\TestCase;

/**
 * Kayda düşen adres, içindeki sırrı taşımamalı.
 *
 * Bu projede adres satırında sır taşıyan iki akış var:
 *
 *  - `GET {locale}/sifre-sifirla/{token}` — şifre sıfırlama jetonu **yolun
 *    içinde**. Ele geçiren, o hesabın şifresini değiştirebilir.
 *  - `GET {locale}/e-posta-dogrula/{id}/{hash}` — imzalı adres; `signature`
 *    sorgu dizesinde.
 *
 * Bu adresler dört yere yazılıyordu: `error_logs` (60 gün), `audit_logs`
 * (90 gün), çerez rızası kaydı ve **Telegram** — sonuncusu sunucudan tamamen
 * çıkıyor. O sayfalarda oluşan tek bir hata, geçerli bir sıfırlama jetonunu
 * panele bakan herkesin ve Telegram grubundaki herkesin önüne koyabiliyordu.
 *
 * Neden HTTP isteğiyle değil de doğrudan sınanıyor: test paketi CLI'da koşuyor,
 * `runningInConsole()` true dönüyor ve adres zaten hiç yakalanmıyor. Yani bir
 * HTTP sınavı burada yeşil yanar ve **hiçbir şey kanıtlamaz**. Üretimdeki
 * davranışı ancak isteği elde kurup temizleyiciyi çağırarak sınamak mümkün.
 */
final class SecretsDoNotLeakIntoLogsTest extends TestCase
{
    /**
     * Üretimdeki gibi çözülmüş bir istek: rota eşleşmiş, parametreleri bağlı.
     *
     * @param array<string, string> $parameters
     */
    private function resolved(string $uri, string $pattern, array $parameters): Request
    {
        $request = Request::create($uri, 'GET');

        $route = new RoutingRoute(['GET'], $pattern, static fn (): string => '');
        $route->bind($request);

        foreach ($parameters as $name => $value) {
            $route->setParameter($name, $value);
        }

        $request->setRouteResolver(static fn (): RoutingRoute => $route);

        return $request;
    }

    // ── Yol içindeki sır ──

    public function test_a_reset_token_in_the_path_is_masked(): void
    {
        $url = SafeUrl::forRequest($this->resolved(
            '/tr/sifre-sifirla/COK-GIZLI-SIFIRLAMA-JETONU',
            '{locale}/sifre-sifirla/{token}',
            ['locale' => 'tr', 'token' => 'COK-GIZLI-SIFIRLAMA-JETONU'],
        ));

        $this->assertStringNotContainsString('COK-GIZLI-SIFIRLAMA-JETONU', $url);
        $this->assertStringContainsString(SafeUrl::MASK, $url);

        // Adres okunur kalmalı: hangi sayfada patladığı görülemeyen kayıt
        // işe yaramaz.
        $this->assertStringContainsString('/tr/sifre-sifirla/', $url);
    }

    public function test_a_verification_hash_in_the_path_is_masked(): void
    {
        $url = SafeUrl::forRequest($this->resolved(
            '/tr/e-posta-dogrula/41/9f8a7b6c5d4e3f2a1b0c',
            '{locale}/e-posta-dogrula/{id}/{hash}',
            ['locale' => 'tr', 'id' => '41', 'hash' => '9f8a7b6c5d4e3f2a1b0c'],
        ));

        $this->assertStringNotContainsString('9f8a7b6c5d4e3f2a1b0c', $url);

        // Kullanıcı kimliği sır değil; maskelenirse kayıt kimden bahsettiğini
        // söyleyemez.
        $this->assertStringContainsString('/41/', $url);
    }

    // ── Sorgu dizesindeki sır ──

    public function test_a_signature_in_the_query_string_is_masked(): void
    {
        $request = Request::create('/tr/e-posta-dogrula/41/abc?expires=1788000000&signature=COK-GIZLI-IMZA', 'GET');

        $url = SafeUrl::forRequest($request);

        $this->assertStringNotContainsString('COK-GIZLI-IMZA', $url);
        // Süre sır değil, dursun: hatanın bağlantı geçerliyken mi oluştuğunu
        // anlamak için gerekiyor.
        $this->assertStringContainsString('expires=1788000000', $url);
    }

    /**
     * Temizlik adla çalışıyor, biçimle değil. Değere bakan bir kural
     * (uzunluk, rastgelelik) er geç yanılır.
     */
    public function test_masking_is_driven_by_the_parameter_name(): void
    {
        $request = Request::create('/bir-sayfa?api_token=kisa&otp=123456&code=abcdef', 'GET');

        $url = SafeUrl::forRequest($request);

        foreach (['kisa', '123456', 'abcdef'] as $secret) {
            $this->assertStringNotContainsString($secret, $url, "{$secret} maskelenmemiş.");
        }
    }

    // ── Aşırı temizlik olmamalı ──

    public function test_an_ordinary_url_is_left_alone(): void
    {
        $request = Request::create('/tr/blog?sayfa=2&ara=merhaba&kategori=haber', 'GET');

        $url = SafeUrl::forRequest($request);

        $this->assertStringContainsString('/tr/blog', $url);
        $this->assertStringContainsString('sayfa=2', $url);
        $this->assertStringContainsString('merhaba', $url);
        $this->assertStringNotContainsString(SafeUrl::MASK, $url);
    }

    /**
     * Kısa bir yol parametresi maskelenmemeli: "1" gibi bir kimliği silmek
     * adresi okunmaz yapar ve zaten sır değildir.
     */
    public function test_a_short_path_parameter_is_not_masked(): void
    {
        $url = SafeUrl::forRequest($this->resolved(
            '/tr/kod/7',
            '{locale}/kod/{code}',
            ['locale' => 'tr', 'code' => '7'],
        ));

        $this->assertStringContainsString('/kod/7', $url);
    }

    // ── Sağlamlık ──

    /**
     * Rota çözülmeden oluşan hata (yönlendirmeden önce) çökmemeli.
     */
    public function test_it_works_without_a_resolved_route(): void
    {
        $url = SafeUrl::forRequest(Request::create('/tr/bir-yer?token=GIZLI', 'GET'));

        $this->assertStringNotContainsString('GIZLI', $url);
        $this->assertStringContainsString('/tr/bir-yer', $url);
    }

    /**
     * Referer gibi rotası bilinmeyen ham adresler için ayrı giriş.
     */
    public function test_a_raw_url_can_be_sanitized(): void
    {
        $this->assertStringNotContainsString(
            'GIZLI',
            SafeUrl::sanitize('https://ornek.test/tr/dogrula?signature=GIZLI'),
        );

        $this->assertSame('', SafeUrl::sanitize(''));
    }

    public function test_the_url_is_truncated_to_the_limit(): void
    {
        $long = 'https://ornek.test/tr/' . str_repeat('a', 500);

        $this->assertSame(120, mb_strlen(SafeUrl::forRequest(Request::create($long, 'GET'), 120)));
    }

    // ── Sistemik bekçi ──

    /**
     * Ham adres hiçbir yerde kayda yazılmamalı.
     *
     * Tek tek düzeltmek yetmiyor: yarın eklenen bir kayıt noktası aynı sızıntıyı
     * yeniden açar ve kimse fark etmez. Kural artık basit — kayda giden adres
     * {@see SafeUrl} üzerinden geçer.
     */
    public function test_no_code_writes_a_raw_full_url(): void
    {
        $offenders = [];

        foreach ($this->phpFilesIn(app_path()) as $file) {
            if (str_ends_with($file, 'Support/SafeUrl.php')) {
                continue;
            }

            foreach (file($file) ?: [] as $number => $line) {
                if (preg_match('/(?<!Safe)(Request::|\$request->|request\(\)->)fullUrl\(\)/', $line) !== 1) {
                    continue;
                }

                $offenders[] = str_replace(base_path() . '/', '', $file) . ':' . ($number + 1);
            }
        }

        sort($offenders);

        $this->assertSame(
            [],
            $offenders,
            "Ham adres kayda yazılıyor — App\\Support\\SafeUrl kullanın:\n  "
                . implode("\n  ", $offenders),
        );
    }

    /**
     * @return list<string>
     */
    private function phpFilesIn(string $directory): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
