<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\ValidationEngineLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Doğrulama motorunun uyarıları ziyaretçinin dilinde.
 *
 * Düzenler dil dosyasını sabit yazıyordu: sunucu tarafı çevrilmişken
 * İngilizce sayfadaki bir formda uyarı balonları Türkçe çıkıyordu.
 *
 * Dosyanın varlığına bakmak şart: paket her dil için ayrı dosya taşıyor ve
 * hepsi projede yok. Olmayan bir dosyayı yüklemek motoru hiç kurulmamış
 * hâlde bırakır — form sessizce doğrulamasız kalır, ki bu Türkçe uyarıdan da
 * kötü.
 */
final class ValidationEngineSpeaksTheLocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        app(\App\Services\LanguageService::class)->clearCache();
    }

    private function locale(): ValidationEngineLocale
    {
        return new ValidationEngineLocale();
    }

    public function test_each_language_gets_its_own_file(): void
    {
        $this->assertStringEndsWith('-tr.js', $this->locale()->scriptPath('tr'));
        $this->assertStringEndsWith('-en.js', $this->locale()->scriptPath('en'));
    }

    /** Dosyası olmayan dil sessizce doğrulamasız kalmamalı. */
    public function test_a_language_without_a_file_falls_back(): void
    {
        $this->assertFalse($this->locale()->hasOwnFile('de'));
        $this->assertStringEndsWith('-tr.js', $this->locale()->scriptPath('de'));
    }

    /** Bölgesel kod temel diline düşmeli: en-gb → en. */
    public function test_a_regional_code_falls_back_to_its_base_language(): void
    {
        $this->assertStringEndsWith('-en.js', $this->locale()->scriptPath('en-GB'));
    }

    /** Dil kodu dosya adına giriyor; uydurulmuş bir kod yolda gezinmemeli. */
    public function test_a_made_up_code_cannot_walk_the_file_path(): void
    {
        foreach (['../../etc/passwd', 'tr/../../x', 'ç"><script>'] as $kotu) {
            $yol = $this->locale()->scriptPath($kotu);

            $this->assertStringEndsWith('-tr.js', $yol);
            $this->assertStringNotContainsString('..', $yol);
        }
    }

    public function test_the_files_it_points_at_really_exist(): void
    {
        foreach (['tr', 'en'] as $locale) {
            $this->assertFileExists(public_path($this->locale()->scriptPath($locale)));
        }
    }

    // ── Sayfalarda ──

    public function test_the_page_loads_the_file_for_its_own_language(): void
    {
        $tr = (string) $this->get('/tr/iletisim')->assertOk()->getContent();
        $en = (string) $this->get('/en/iletisim')->assertOk()->getContent();

        $this->assertStringContainsString('jquery.validationEngine-tr.js', $tr);
        $this->assertStringNotContainsString('jquery.validationEngine-en.js', $tr);

        $this->assertStringContainsString('jquery.validationEngine-en.js', $en);
        $this->assertStringNotContainsString('jquery.validationEngine-tr.js', $en);
    }

    public function test_the_sign_in_layout_follows_the_language_too(): void
    {
        $en = (string) $this->get('/en/giris')->assertOk()->getContent();

        $this->assertStringContainsString('jquery.validationEngine-en.js', $en);
    }

    /** Hiçbir düzen dosyayı sabit yazmamalı. */
    public function test_no_layout_hardcodes_a_language_file(): void
    {
        $sabit = [];

        foreach ((array) glob(resource_path('views/layouts/*.blade.php')) as $layout) {
            $source = (string) file_get_contents((string) $layout);

            if (preg_match('/validationEngine-[a-z]{2}(?:-[a-z]{2})?\.js/', $source, $m) === 1) {
                $sabit[] = basename((string) $layout) . ' → ' . $m[0];
            }
        }

        sort($sabit);

        $this->assertSame([], $sabit, "Dil dosyası sabit yazılmış:\n  " . implode("\n  ", $sabit));
    }

    // ── İki dosya birbirinden ayrışmamalı ──

    /**
     * Kural desenleri iki dilde birebir aynı olmalı.
     *
     * Ayrışırlarsa ziyaretçi dile göre farklı davranan bir formla karşılaşır:
     * biri "geçerli" derken öteki "geçersiz" der.
     */
    public function test_the_two_files_validate_exactly_the_same_things(): void
    {
        [$tr, $en] = array_map(
            fn (string $locale): string => (string) file_get_contents(public_path($this->locale()->scriptPath($locale))),
            ['tr', 'en'],
        );

        preg_match_all('~"regex":\s*(/.*?/[a-z]*)~', $tr, $trDesen);
        preg_match_all('~"regex":\s*(/.*?/[a-z]*)~', $en, $enDesen);

        $this->assertNotEmpty($trDesen[1]);
        $this->assertSame($trDesen[1], $enDesen[1], 'İki dilin kural desenleri ayrışmış');
    }

    /** İngilizce dosyada çevrilmemiş Türkçe metin kalmamalı. */
    public function test_the_english_file_carries_no_turkish_text(): void
    {
        $en = (string) file_get_contents(public_path($this->locale()->scriptPath('en')));

        preg_match_all('/"alertText[A-Za-z0-9]*":\s*"([^"]*)"/', $en, $matches);

        $turkce = array_values(array_filter(
            $matches[1],
            static fn (string $text): bool => preg_match('/[çğıöşüÇĞİÖŞÜ]/u', $text) === 1,
        ));

        $this->assertSame([], $turkce, "İngilizce dosyada Türkçe metin:\n  " . implode("\n  ", $turkce));
    }
}
