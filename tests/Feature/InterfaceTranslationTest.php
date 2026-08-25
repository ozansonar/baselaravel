<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\LanguageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The interface itself is translated, not just the content.
 *
 * Content lives in the database one row per language; the chrome around it —
 * navigation, buttons, form labels, empty states — comes from lang/{code}/site.php.
 */
class InterfaceTranslationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        app(LanguageService::class)->clearCache();
    }

    /**
     * @return array<string, mixed>
     */
    private function flatten(array $items, string $prefix = ''): array
    {
        $flat = [];

        foreach ($items as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                $flat += $this->flatten($value, $path);

                continue;
            }

            $flat[$path] = $value;
        }

        return $flat;
    }

    /**
     * A missing key silently falls back to the other language, which looks like
     * a half-translated page rather than an error. This catches it instead.
     */
    public function test_every_language_file_carries_the_same_keys(): void
    {
        $turkish = $this->flatten(require lang_path('tr/site.php'));
        $english = $this->flatten(require lang_path('en/site.php'));

        $this->assertSame(
            [],
            array_keys(array_diff_key($turkish, $english)),
            'İngilizce dosyada eksik anahtar var',
        );

        $this->assertSame(
            [],
            array_keys(array_diff_key($english, $turkish)),
            'Türkçe dosyada eksik anahtar var',
        );
    }

    public function test_no_translation_value_is_left_empty(): void
    {
        foreach (['tr', 'en'] as $locale) {
            foreach ($this->flatten(require lang_path("{$locale}/site.php")) as $key => $value) {
                $this->assertNotSame('', trim((string) $value), "{$locale}/site.php içinde {$key} boş");
            }
        }
    }

    /**
     * Every key the views ask for has to exist, otherwise Laravel renders the
     * key itself — "site.nav.home" — straight onto the page.
     */
    public function test_every_key_used_in_a_view_exists(): void
    {
        $turkish = $this->flatten(require lang_path('tr/site.php'));
        $missing = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            preg_match_all("/__\('(site\.[a-z0-9_.]+)'/i", $file->getContents(), $matches);

            foreach ($matches[1] as $key) {
                $lookup = substr($key, strlen('site.'));

                if (! array_key_exists($lookup, $turkish)) {
                    $missing[] = $key . ' (' . $file->getFilename() . ')';
                }
            }
        }

        $this->assertSame([], $missing, 'View de kullanılan ama tanımlı olmayan anahtar: ' . implode(', ', $missing));
    }

    public function test_the_home_page_is_rendered_in_turkish_by_default(): void
    {
        $html = $this->withHeaders(['Accept-Language' => 'tr'])->get('/')->getContent();

        $this->assertStringContainsString(__('site.nav.home', [], 'tr'), $html);
        $this->assertStringContainsString(__('site.actions.get_start', [], 'tr'), $html);
    }

    public function test_the_home_page_is_rendered_in_english_for_an_english_browser(): void
    {
        $html = $this->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])->get('/')->getContent();

        $this->assertStringContainsString('Get Started', $html);
        $this->assertStringContainsString('Explore Content', $html);
        $this->assertStringNotContainsString('Hemen Başlayın', $html, 'İngilizce sayfada Türkçe buton kaldı');
    }

    public function test_the_navigation_follows_the_chosen_language(): void
    {
        $this->get(route('locale.switch', 'en'));

        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('Gallery', $html);
        $this->assertStringContainsString('Contact', $html);
        $this->assertStringNotContainsString('>Galeri<', $html);
    }

    public function test_the_sign_in_page_is_translated(): void
    {
        $html = $this->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])->get('/giris')->getContent();

        $this->assertStringContainsString('Email Address', $html);
        $this->assertStringContainsString('Remember me', $html);
        $this->assertStringNotContainsString('Beni hatırla', $html);
    }

    public function test_the_contact_form_labels_are_translated(): void
    {
        $html = $this->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])->get('/iletisim')->getContent();

        $this->assertStringContainsString('Your Message', $html);
        $this->assertStringContainsString('Working Hours', $html);
    }

    /**
     * The hero headline carries markup so the highlighted words can differ per
     * language; it must render as HTML rather than escaped text.
     */
    public function test_the_hero_headline_renders_its_markup(): void
    {
        $html = $this->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])->get('/')->getContent();

        $this->assertStringContainsString('<span class="grad">to the next level</span>', $html);
        $this->assertStringNotContainsString('&lt;span', $html);
    }

    /**
     * A language with no site.php falls back rather than printing raw keys.
     */
    public function test_a_language_without_a_file_falls_back_instead_of_showing_keys(): void
    {
        \App\Models\Language::where('code', 'de')->update(['is_active' => true]);
        app(LanguageService::class)->clearCache();

        $html = $this->withHeaders(['Accept-Language' => 'de-DE,de;q=0.9'])->get('/')->getContent();

        $this->assertSame('de', app()->getLocale());
        $this->assertStringNotContainsString('site.nav.home', $html, 'Çevrilmemiş dilde ham anahtar görünüyor');
        $this->assertStringContainsString(__('site.nav.home', [], 'tr'), $html);
    }
}
