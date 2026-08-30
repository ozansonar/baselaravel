<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Hata sayfaları.
 *
 * Hata sayfasının işi, hatanın kendisinden daha zor bir durumda çalışmak:
 * ziyaretçi zaten bir şey ters gittiği için oraya düşüyor. İki şey isteniyor
 * — sitenin tasarımıyla karşılamak ve doğru durum kodunu döndürmek. İkincisi
 * gözden kaçıyor: 200 dönen bir "sayfa bulunamadı" ekranını arama motoru
 * gerçek bir sayfa sanıp dizine alıyor.
 */
final class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Kendi sayfası olan kodlar.
     *
     * @var list<int>
     */
    private const CODES = [400, 401, 402, 403, 404, 405, 408, 410, 419, 429];

    /**
     * Sunucu hataları kendi sayfalarını taşımıyor, hepsi 5xx yedeğine
     * düşüyor: o sayfa veritabanına gitmediği için hatanın sebebi
     * veritabanıysa bile ayakta kalıyor.
     *
     * @var list<int>
     */
    private const SERVER_CODES = [500, 502, 504];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        app(\App\Services\LanguageService::class)->clearCache();

        // Hata sayfası ancak hata ayıklama kapalıyken basılıyor; açıkken
        // çerçevenin yığın izi ekranı devreye giriyor.
        config(['app.debug' => false]);
    }

    private function throwing(int $status): string
    {
        $url = '/deneme-hata-' . $status;

        Route::get($url, fn () => throw new HttpException($status))->middleware('web');

        return $url;
    }

    public function test_every_common_code_has_a_page_of_its_own(): void
    {
        $eksik = array_values(array_filter(
            self::CODES,
            static fn (int $code): bool => ! view()->exists('errors.' . $code),
        ));

        $this->assertSame([], $eksik, 'Sayfası olmayan kod: ' . implode(', ', $eksik));
    }

    /**
     * Sayfası olmayan bir kod da sitenin tasarımıyla karşılanmalı.
     *
     * Laravel önce errors/{kod} arıyor, bulamazsa errors/4xx ya da errors/5xx
     * görünümüne düşüyor.
     */
    public function test_an_unlisted_code_still_gets_the_sites_design(): void
    {
        $this->assertTrue(view()->exists('errors.4xx'), '4xx yedeği yok');
        $this->assertTrue(view()->exists('errors.5xx'), '5xx yedeği yok');

        $response = $this->get($this->throwing(418));

        $response->assertStatus(418);
        $this->assertStringContainsString('418', (string) $response->getContent());
        $this->assertStringContainsString('empty-state', (string) $response->getContent());
    }

    /** Yedek sayfa gerçek kodu göstermeli, sabit bir sayı değil. */
    public function test_the_fallback_shows_the_real_code(): void
    {
        $html = (string) $this->get($this->throwing(451))->assertStatus(451)->getContent();

        $this->assertStringContainsString('451', $html);
        $this->assertStringNotContainsString('>400<', $html);
    }

    public function test_a_missing_page_answers_with_404_not_200(): void
    {
        $this->get('/tr/boyle-bir-sayfa-yok')->assertStatus(404);
        $this->get('/en/boyle-bir-sayfa-yok')->assertStatus(404);
    }

    public function test_the_error_page_is_kept_out_of_search_results(): void
    {
        $html = (string) $this->get('/tr/boyle-bir-sayfa-yok')->assertStatus(404)->getContent();

        $this->assertStringContainsString('noindex', $html);
    }

    public function test_the_error_page_speaks_the_visitors_language(): void
    {
        $tr = (string) $this->get('/tr/boyle-bir-sayfa-yok')->getContent();
        $en = (string) $this->get('/en/boyle-bir-sayfa-yok')->getContent();

        $this->assertStringContainsString('Sayfa bulunamadı', $tr);
        $this->assertStringContainsString('Page not found', $en);
        $this->assertStringNotContainsString('Sayfa bulunamadı', $en);
    }

    /** Ziyaretçi hata ekranında kalmamalı; siteye dönecek bir yol olmalı. */
    public function test_the_error_page_offers_a_way_back(): void
    {
        $html = (string) $this->get('/tr/boyle-bir-sayfa-yok')->getContent();

        $this->assertMatchesRegularExpression('/<a[^>]+href="[^"]*\/tr[^"]*"[^>]*class="[^"]*btn/', $html);
    }

    /**
     * Sunucu hatası sayfası, düşen bir veritabanına dayanmamalı.
     *
     * Hatanın sebebi veritabanıysa —kopan bağlantı, dolan disk— site düzenini
     * kullanan bir hata sayfası da düşer ve ziyaretçi çerçevenin çıplak
     * ekranını görür. Tam da en kötü anda.
     *
     * Bağlantıyı gerçekten koparmak testin kendi işlemini bozuyor
     * (RefreshDatabase açık bir işlem tutuyor). Onun yerine sayfanın hangi
     * tablolara gittiği ölçülüyor: menü, ayar ve duyuru sorguları korumasız,
     * biri düşerse sayfa da düşer. Çeviri sorgusu bunların dışında — servis
     * onu try/catch içinde yapıp dosyadaki metne düşüyor ve sonucu süresiz
     * önbelleğe alıyor.
     */
    public function test_a_server_error_page_does_not_lean_on_a_database(): void
    {
        $korumasiz = ['settings', 'menus', 'menu_items', 'popups', 'sliders', 'languages'];

        foreach (self::SERVER_CODES as $status) {
            $sorgular = [];

            \Illuminate\Support\Facades\DB::listen(function ($query) use (&$sorgular): void {
                $sorgular[] = $query->sql;
            });

            $html = view('errors.' . $status, ['exception' => new HttpException($status)])->render();

            $riskli = array_values(array_filter(
                $sorgular,
                static fn (string $sql): bool => array_any(
                    $korumasiz,
                    static fn (string $tablo): bool => str_contains($sql, '"' . $tablo . '"'),
                ),
            ));

            $this->assertSame(
                [],
                $riskli,
                "{$status} sayfası düşebilecek bir tabloya gidiyor:\n  " . implode("\n  ", $riskli),
            );

            $this->assertStringContainsString((string) $status, $html);
        }
    }

    /**
     * Sunucu hatası sayfası site düzenini kullanmamalı.
     *
     * Düzen menüyü, ayarları ve duyuruları okuyor; onu kullanan bir sayfa
     * veritabanı düştüğünde basılamaz. Bu, üstteki ölçümün yapısal karşılığı:
     * biri bugünü, öteki yarın eklenecek bir @extends'i yakalıyor.
     */
    public function test_a_server_error_page_stands_on_its_own(): void
    {
        foreach ([...self::SERVER_CODES, '5xx'] as $status) {
            $source = (string) file_get_contents(resource_path("views/errors/{$status}.blade.php"));

            $this->assertStringNotContainsString(
                "@extends('layouts.app')",
                $source,
                "errors/{$status} site düzenine bağlı; veritabanı düşerse basılamaz",
            );
        }
    }

    /** Sunucu hatası sayfası koda özgü metni, yoksa genel metni kullanmalı. */
    public function test_a_server_error_page_names_the_problem(): void
    {
        $bilinen = view('errors.5xx', ['exception' => new HttpException(502)])->render();

        $this->assertStringContainsString(__('site.errors.502_title'), $bilinen);

        // Adı konmamış bir kod da anlamlı bir cümleyle karşılanmalı.
        $bilinmeyen = view('errors.5xx', ['exception' => new HttpException(507)])->render();

        $this->assertStringContainsString('507', $bilinmeyen);
        $this->assertStringContainsString(__('site.errors.generic_server_title'), $bilinmeyen);
        $this->assertStringNotContainsString('site.errors.507', $bilinmeyen);
    }

    /** Sunucu hataları gerçekten o sayfayı basmalı ve kodu korumalı. */
    public function test_a_server_error_answers_with_its_own_code(): void
    {
        foreach (self::SERVER_CODES as $status) {
            $response = $this->get($this->throwing($status));

            $response->assertStatus($status);
            $this->assertStringContainsString((string) $status, (string) $response->getContent());
            $this->assertStringContainsString('empty-state', (string) $response->getContent());
        }
    }
}
