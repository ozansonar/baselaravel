<?php

declare(strict_types=1);

namespace Tests\Feature;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Erişilebilirliğin sunucu tarafından denetlenebilen yarısı.
 *
 * Kontrast ve odak sırası tarayıcı işi; ama "bu düğmenin adı var mı",
 * "bu alanın etiketi var mı", "içeriğe atlama bağlantısı duruyor mu"
 * soruları işaretlemeye bakarak yanıtlanabiliyor — ve bunlar ekran
 * okuyucuyla gezen biri için kullanılabilirliğin tamamını belirliyor.
 *
 * Bekçi olarak yazıldı: yeni bir ikon düğmesi eklenip adı unutulduğunda
 * burası düşüyor. Yol üzerinde bir kusur çıkardı — bültenin gönder düğmesi
 * yalnızca bir ikondu ve ekran okuyucuda adsız görünüyordu.
 */
class AccessibilityBaselineTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private const PAGES = [
        '/tr',
        '/tr/blog',
        '/tr/galeri',
        '/tr/iletisim',
        '/tr/sikca-sorulan-sorular',
        '/tr/arama?q=deneme',
        '/tr/giris',
        '/tr/kayit',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
        $this->seed(\Database\Seeders\LanguageSeeder::class);
        $this->seed(\Database\Seeders\SettingSeeder::class);
    }

    private function document(string $url): DOMXPath
    {
        $html = (string) $this->get($url)->assertOk()->getContent();

        $dom = new DOMDocument();
        // HTML5 etiketleri libxml'i uyarıyor; sınav işaretlemenin geçerliliği
        // değil, adların varlığı.
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);

        return new DOMXPath($dom);
    }

    private function accessibleName(DOMElement $element): string
    {
        $name = trim($element->textContent);

        if ($name !== '') {
            return $name;
        }

        foreach (['aria-label', 'title', 'aria-labelledby'] as $attribute) {
            if (trim($element->getAttribute($attribute)) !== '') {
                return trim($element->getAttribute($attribute));
            }
        }

        // İçindeki görselin alt metni de bir addır.
        foreach ($element->getElementsByTagName('img') as $image) {
            if ($image instanceof DOMElement && trim($image->getAttribute('alt')) !== '') {
                return trim($image->getAttribute('alt'));
            }
        }

        return '';
    }

    public function test_every_control_has_an_accessible_name(): void
    {
        $adsiz = [];

        foreach (self::PAGES as $page) {
            $xpath = $this->document($page);

            /** @var iterable<DOMElement> $controls */
            $controls = $xpath->query('//button | //a[@href] | //*[@role="button"]');

            foreach ($controls as $control) {
                if ($this->accessibleName($control) === '') {
                    $adsiz[] = $page . ' → ' . trim(preg_replace('/\s+/', ' ', (string) $control->ownerDocument?->saveHTML($control)) ?? '');
                }
            }
        }

        sort($adsiz);

        $this->assertSame(
            [],
            $adsiz,
            "Ekran okuyucuda adsız görünen kontroller var:\n" . implode("\n", $adsiz),
        );
    }

    public function test_every_field_has_a_label(): void
    {
        $etiketsiz = [];

        foreach (self::PAGES as $page) {
            $xpath = $this->document($page);

            /** @var iterable<DOMElement> $fields */
            $fields = $xpath->query('//input[not(@type="hidden")] | //select | //textarea');

            foreach ($fields as $field) {
                $id = $field->getAttribute('id');

                $hasLabel = $id !== '' && $xpath->query('//label[@for="' . $id . '"]')->length > 0;
                $wrapped = $xpath->query('ancestor::label', $field)->length > 0;
                $aria = trim($field->getAttribute('aria-label')) !== ''
                    || trim($field->getAttribute('aria-labelledby')) !== '';

                if (! $hasLabel && ! $wrapped && ! $aria) {
                    $etiketsiz[] = $page . ' → ' . $field->getAttribute('name');
                }
            }
        }

        sort($etiketsiz);

        $this->assertSame(
            [],
            $etiketsiz,
            "Etiketsiz form alanları var:\n" . implode("\n", $etiketsiz),
        );
    }

    /**
     * Klavyeyle gezen kişi, her sayfada menüyü baştan sona geçmeden içeriğe
     * atlayabilmeli.
     */
    public function test_the_skip_link_is_there_and_points_at_the_content(): void
    {
        foreach (['/tr', '/tr/blog'] as $page) {
            $html = (string) $this->get($page)->assertOk()->getContent();

            $this->assertStringContainsString('href="#main-content"', $html, $page);
            $this->assertStringContainsString('id="main-content"', $html, $page);
        }
    }

    /**
     * Sayfanın dili işaretlenmemişse ekran okuyucu metni yanlış aksanla
     * okuyor — Türkçe içeriği İngilizce sesle.
     */
    public function test_the_page_declares_its_language(): void
    {
        $this->get('/tr')->assertOk()->assertSee('<html lang="tr"', false);
        $this->get('/en')->assertOk()->assertSee('<html lang="en"', false);
    }
}
