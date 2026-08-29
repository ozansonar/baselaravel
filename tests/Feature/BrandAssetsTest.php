<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Görünümlerin işaret ettiği statik dosyalar gerçekten yerinde mi?
 *
 * İki tanesi değildi: favicon.ico sıfır bayttı (sekmede simge yoktu) ve
 * images/logo.png hiç yoktu — oysa ayarlarda logo tanımlı değilken og:image,
 * twitter:image ve JSON-LD logosu ona işaret ediyordu; yani paylaşılan her
 * bağlantı kırık bir görsel duyuruyordu. Sayfa hatasız açıldığı için ikisi de
 * gözden kaçıyordu.
 *
 * Denetim tek tek dosya saymak yerine görünümleri tarıyor: sonradan eklenen
 * bir asset() çağrısı da aynı kuralı karşılamak zorunda.
 */
final class BrandAssetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_asset_the_views_point_at_exists_and_is_not_empty(): void
    {
        $sorunlu = [];

        foreach ($this->bladeFiles() as $file) {
            $contents = (string) file_get_contents($file);

            preg_match_all('/(?:versioned_)?asset\(\s*[\'"]([^\'"]+)[\'"]/', $contents, $matches);

            foreach (array_unique($matches[1]) as $path) {
                // Dış adresler ve Blade ifadesiyle kurulan yollar kapsam dışı.
                if (str_starts_with($path, 'http') || str_starts_with($path, '//') || str_contains($path, '{')) {
                    continue;
                }

                $full = public_path(ltrim($path, '/'));
                $relative = str_replace(base_path() . '/', '', $file);

                if (! file_exists($full)) {
                    $sorunlu[] = "{$path} yok  ← {$relative}";

                    continue;
                }

                if (filesize($full) === 0) {
                    $sorunlu[] = "{$path} boş (0 bayt)  ← {$relative}";
                }
            }
        }

        $this->assertSame([], $sorunlu, "Kırık varlık referansı:\n" . implode("\n", $sorunlu));
    }

    /**
     * Ayarlarda logo yokken paylaşım görseli varsayılana düşüyor; o dosyanın
     * gerçekten var olması sosyal ağlara kırık bağlantı gitmemesi demek.
     */
    public function test_the_default_share_image_is_served_when_no_logo_is_set(): void
    {
        $this->assertNull(Setting::getValue('site_logo'), 'Bu sınama logosuz durumu ölçüyor');

        $html = (string) $this->get(route('home'))->assertOk()->getContent();

        preg_match('/<meta property="og:image" content="([^"]+)"/', $html, $match);

        $this->assertNotEmpty($match[1] ?? '', 'og:image etiketi yok');

        $path = public_path(ltrim(parse_url($match[1], PHP_URL_PATH) ?? '', '/'));

        $this->assertFileExists($path, 'og:image var olmayan bir dosyayı gösteriyor');
        $this->assertGreaterThan(0, filesize($path));
    }

    /** iOS .ico okumuyor; ana ekran kısayolu için PNG gerekiyor. */
    public function test_the_touch_icon_is_a_png(): void
    {
        foreach ([route('home'), route('login')] as $url) {
            $html = (string) $this->get($url)->assertOk()->getContent();

            preg_match('/<link rel="apple-touch-icon" href="([^"]+)"/', $html, $match);

            $this->assertNotEmpty($match[1] ?? '', "{$url} → apple-touch-icon yok");
            $this->assertStringEndsWith('.png', parse_url($match[1], PHP_URL_PATH) ?? '');
        }
    }

    public function test_the_favicon_is_a_real_icon_file(): void
    {
        $path = public_path('favicon.ico');

        $this->assertFileExists($path);

        // ICO başlığı: 2 bayt rezerve (0), 2 bayt tür (1 = ikon).
        $header = (string) file_get_contents($path, false, null, 0, 4);
        $parts = unpack('vreserved/vtype', $header);

        $this->assertSame(0, $parts['reserved']);
        $this->assertSame(1, $parts['type'], 'favicon.ico geçerli bir ikon dosyası değil');
    }

    /**
     * @return list<string>
     */
    private function bladeFiles(): array
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS),
        );

        $files = [];

        foreach ($iterator as $file) {
            // admin-theme hazır HTML tasarım referansı; Blade olarak servis edilmiyor.
            if (! $file->isFile()
                || ! str_ends_with($file->getFilename(), '.blade.php')
                || str_contains($file->getPathname(), '/admin-theme/')) {
                continue;
            }

            $files[] = $file->getPathname();
        }

        sort($files);

        return $files;
    }
}
