<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\TranslationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ön yüzdeki her yazı panelden değiştirilebilmeli.
 *
 * "Dil Yazıları" ekranı lang/{dil}/site.php'deki anahtarları listeliyor ve
 * yöneticinin değişikliğini veritabanına yazıyor. Bu düzenin tek açığı, bir
 * metnin Blade'e doğrudan yazılması: ekranda görünür, panelde görünmez,
 * değiştirmek için koda dokunmak gerekir. Sessizce olur, çünkü sayfa gayet
 * düzgün açılır.
 *
 * Bu yüzden denetim tek tek metin saymak yerine görünümleri tarıyor: sonradan
 * eklenen bir yazı da aynı kuralı karşılamak zorunda.
 */
final class FrontTextsAreEditableTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ziyaretçinin okuduğu, dolayısıyla çevrilmesi gereken nitelikler.
     *
     * @var list<string>
     */
    private const VISIBLE_ATTRIBUTES = ['placeholder', 'aria-label', 'title', 'alt'];

    /**
     * Metin sayılmayan değerler: teknik terimler ve ekranda söz olarak
     * okunmayan simgeler.
     *
     * @var list<string>
     */
    private const NOT_PROSE = ['breadcrumb', 'button', 'true', 'false', 'null'];

    public function test_no_front_view_writes_a_visitor_facing_string_by_hand(): void
    {
        $bulgular = [];

        foreach ($this->frontViews() as $file) {
            $masked = $this->maskCode((string) file_get_contents($file));
            $relative = str_replace(base_path() . '/', '', $file);

            foreach ($this->hardcodedStrings($masked) as [$line, $where, $text]) {
                $bulgular[] = "{$relative}:{$line}  ({$where})  {$text}";
            }
        }

        $this->assertSame(
            [],
            $bulgular,
            "Panelden değiştirilemeyen yazı — lang/*/site.php'ye anahtar açıp __() ile çağırın:\n"
                . implode("\n", $bulgular),
        );
    }

    /**
     * Görünümlerin çağırdığı her site.* anahtarı gerçekten tanımlı olmalı.
     *
     * Tanımsız anahtar hata vermiyor: Laravel anahtarın kendisini basıyor, yani
     * ekranda "site.blog.eyebrow" yazıyor ve panelde düzenlenecek bir satır
     * çıkmıyor.
     */
    public function test_every_key_the_views_call_actually_exists(): void
    {
        $tanimli = app(TranslationService::class)->fileLines('tr', 'site');
        $eksik = [];

        foreach ($this->frontViews() as $file) {
            $contents = (string) file_get_contents($file);
            $relative = str_replace(base_path() . '/', '', $file);

            preg_match_all("/__\(\s*'site\.([a-z0-9_.]+)'/i", $contents, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[1] as $match) {
                if (! array_key_exists($match[0], $tanimli)) {
                    $line = substr_count(substr($contents, 0, (int) $match[1]), "\n") + 1;
                    $eksik[] = "{$relative}:{$line}  site.{$match[0]}";
                }
            }
        }

        $this->assertSame([], $eksik, "Tanımsız çeviri anahtarı:\n" . implode("\n", $eksik));
    }

    /**
     * Panel ekranı anahtarları dosyadan okuyor; kodda açılan bir anahtar
     * kendiliğinden düzenlenebilir bir satır olarak görünmeli.
     */
    public function test_the_panel_lists_the_keys_the_views_use(): void
    {
        $anahtarlar = app(TranslationService::class)->keysFrom('site');

        foreach (['nav.home', 'blog.eyebrow', 'gallery.all', 'misc.main_nav', 'login.email_ph'] as $key) {
            $this->assertArrayHasKey($key, $anahtarlar, "Panel {$key} anahtarını listelemiyor");
        }

        $this->assertContains('site', TranslationService::EDITABLE_GROUPS);
    }

    /**
     * @return list<string>
     */
    private function frontViews(): array
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS),
        );

        $files = [];

        foreach ($iterator as $file) {
            $path = $file->getPathname();

            // Yönetim paneli kapsam dışı: oradaki yazılar yöneticinin kendi
            // arayüzü, ziyaretçi görmüyor ve tek dilde tutuluyor. admin-theme
            // hazır HTML tasarım referansı, Blade olarak servis edilmiyor.
            // E-posta gövdeleri ve dışa aktarma şablonlarının kendi modülleri var.
            $disarida = ['/admin/', '/admin-theme/', '/components/', '/emails/', '/exports/', '/vendor/'];

            if (! $file->isFile()
                || ! str_ends_with($file->getFilename(), '.blade.php')
                || str_contains($path, 'layouts/admin')
                || array_any($disarida, static fn (string $parca): bool => str_contains($path, $parca))) {
                continue;
            }

            $files[] = $path;
        }

        sort($files);

        return $files;
    }

    /**
     * Kodu boşlukla değiştirir, düz metni bırakır.
     *
     * Satır numaraları korunsun diye silinen her parça aynı sayıda satır sonu
     * bırakıyor; yoksa bulgu dosyanın yanlış satırını gösterirdi.
     */
    private function maskCode(string $source): string
    {
        $blank = static fn (array $m): string => (string) preg_replace('/[^\n]/', '', $m[0]);

        foreach ([
            '/\{\{--.*?--\}\}/s',      // Blade yorumu
            '/@php.*?@endphp/s',
            '/<script\b.*?<\/script>/si',
            '/<style\b.*?<\/style>/si',
            '/\{\{.*?\}\}/s',          // yankılanan ifade
            '/\{!!.*?!!\}/s',
        ] as $pattern) {
            $source = (string) preg_replace_callback($pattern, $blank, $source);
        }

        $source = $this->maskDirectives($source);

        return (string) preg_replace_callback('/@\w+/', $blank, $source);
    }

    /**
     * @directive(...) çağrılarını, parantezleri sayarak siler.
     *
     * Düzenli ifadeyle yapılamıyor: @section('og_image', $post->image ? url(...) : '')
     * gibi iç içe parantezli çağrılar var ve kalıp yanlış yerde kapanınca geriye
     * kalan " : '')" parçası metin sanılıyordu.
     */
    private function maskDirectives(string $source): string
    {
        $result = '';
        $length = strlen($source);
        $i = 0;

        while ($i < $length) {
            if (! preg_match('/\G@\w+\s*\(/', $source, $m, 0, $i)) {
                $result .= $source[$i];
                $i++;

                continue;
            }

            $start = $i;
            $i += strlen($m[0]);
            $depth = 1;

            while ($i < $length && $depth > 0) {
                $depth += match ($source[$i]) {
                    '(' => 1,
                    ')' => -1,
                    default => 0,
                };
                $i++;
            }

            $result .= (string) preg_replace('/[^\n]/', '', substr($source, $start, $i - $start));
        }

        return $result;
    }

    /**
     * @return list<array{int, string, string}>
     */
    private function hardcodedStrings(string $masked): array
    {
        $bulgular = [];

        $topla = function (string $pattern, string $where) use ($masked, &$bulgular): void {
            preg_match_all($pattern, $masked, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[1] as $match) {
                if (! $this->isProse($match[0])) {
                    continue;
                }

                $bulgular[] = [
                    substr_count(substr($masked, 0, (int) $match[1]), "\n") + 1,
                    $where,
                    trim($match[0]),
                ];
            }
        };

        $topla('/>([^<>]+)</', 'metin');

        foreach (self::VISIBLE_ATTRIBUTES as $attribute) {
            $topla('/' . preg_quote($attribute, '/') . '="([^"]*)"/', $attribute);
        }

        return $bulgular;
    }

    /** Ziyaretçinin söz olarak okuduğu bir şey mi? */
    private function isProse(string $text): bool
    {
        // Varlık imleri simge, söz değil: "&copy; {{ date('Y') }} {{ $ad }}."
        // kalıbından geriye kalan "&copy; ." harf taşıdığı için yazı sanılıyordu.
        $text = trim((string) preg_replace('/&[a-z]+;|&#\d+;/i', '', $text));

        if (mb_strlen($text) < 3 || in_array(mb_strtolower($text), self::NOT_PROSE, true)) {
            return false;
        }

        // İçinde harf olmayan (rakam, simge, madde imi) değerler yazı değil.
        return preg_match('/\p{L}/u', $text) === 1;
    }
}
