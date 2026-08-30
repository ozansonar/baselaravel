<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\ImageAltResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ziyaretçinin gördüğü her görselin alt metni ve yükleme kuralı olmalı.
 *
 * Boş alt, ekran okuyucu için görselin hiç olmaması demek; arama motoru için
 * de görselin ne anlattığını söyleyen tek ipucunun kaybı. loading olmadan ise
 * tarayıcı sayfadaki bütün görselleri açılışta indirmeye kalkıyor.
 *
 * İkisi de tek tek yazıldığı sürece er ya da geç unutuluyor, üstelik sessizce:
 * sayfa gayet düzgün açılıyor. Bu yüzden denetim görünümleri tarıyor —
 * sonradan eklenen bir görsel de aynı kuralı karşılamak zorunda.
 */
final class ImagesCarryAltAndLoadingTest extends TestCase
{
    use RefreshDatabase;

    // ── Zincir ──

    public function test_the_first_filled_candidate_wins(): void
    {
        $this->assertSame('Kapak görseli', $this->resolver()->resolve('Kapak görseli', 'Yazı başlığı'));
        $this->assertSame('Yazı başlığı', $this->resolver()->resolve(null, 'Yazı başlığı'));
        $this->assertSame('Yazı başlığı', $this->resolver()->resolve('   ', 'Yazı başlığı'));
    }

    /** Hiçbir aday yoksa sitenin kendini anlattığı metin devreye giriyor. */
    public function test_the_site_description_is_the_last_resort(): void
    {
        Setting::setValue('site_description', 'Modern kurumsal çözümler');
        Setting::clearSettingsCache();

        $this->assertSame('Modern kurumsal çözümler', $this->resolver()->resolve(null, null));
    }

    public function test_the_keywords_step_in_when_there_is_no_description(): void
    {
        Setting::setValue('site_description', '');
        Setting::setValue('site_keywords', 'laravel, kurumsal, altyapı');
        Setting::clearSettingsCache();

        $this->assertSame('laravel, kurumsal, altyapı', $this->resolver()->resolve(null));
    }

    /** Zincir hiçbir zaman boş bitmiyor: adı olmayan site yok. */
    public function test_the_chain_never_ends_empty(): void
    {
        foreach (['site_description', 'site_keywords'] as $key) {
            Setting::setValue($key, '');
        }

        Setting::clearSettingsCache();

        $this->assertNotSame('', $this->resolver()->resolve(null, null, null));
    }

    /**
     * Alt metni biçimlendirme taşımaz ve tek nefeste okunabilmeli.
     */
    public function test_the_text_is_cleaned_up(): void
    {
        $this->assertSame('Kalın başlık', $this->resolver()->resolve('<strong>Kalın</strong> başlık'));
        $this->assertSame('İki satır', $this->resolver()->resolve("İki\n   satır"));

        $uzun = $this->resolver()->resolve(str_repeat('kelime ', 40));

        $this->assertLessThanOrEqual(125, mb_strlen($uzun));
        $this->assertStringEndsWith('…', $uzun);
    }

    /**
     * Site metni istek başına bir kez seçilmeli.
     *
     * Yüz görsellik bir galeride yüz kez ayar okumak, "veritabanını
     * yormayan" bir çözüm olmazdı.
     */
    public function test_the_site_fallback_is_worked_out_once(): void
    {
        Setting::setValue('site_description', 'İlk metin');
        Setting::clearSettingsCache();

        $resolver = $this->resolver();

        $this->assertSame('İlk metin', $resolver->resolve(null));

        Setting::setValue('site_description', 'Sonraki metin');
        Setting::clearSettingsCache();

        $this->assertSame('İlk metin', $resolver->resolve(null), 'Site metni her çağrıda yeniden okunuyor');

        $resolver->flush();

        $this->assertSame('Sonraki metin', $resolver->resolve(null));
    }

    public function test_the_helper_reaches_the_same_answer(): void
    {
        $this->assertSame(image_alt('Bir başlık'), $this->resolver()->resolve('Bir başlık'));
    }

    // ── Görünümler ──

    public function test_every_front_image_carries_an_alt_and_a_loading_rule(): void
    {
        $altsiz = [];
        $kuralsiz = [];

        foreach ($this->frontViews() as $file) {
            $source = (string) file_get_contents($file);
            $relative = str_replace(resource_path('views') . '/', '', $file);

            foreach ($this->imageTags($source) as [$line, $tag]) {
                if (preg_match('/\balt\s*=\s*"/', $tag) !== 1) {
                    $altsiz[] = "{$relative}:{$line}";
                }

                if (preg_match('/\bloading\s*=\s*"/', $tag) !== 1) {
                    $kuralsiz[] = "{$relative}:{$line}";
                }
            }
        }

        sort($altsiz);
        sort($kuralsiz);

        $this->assertSame([], $altsiz, "alt taşımayan görsel — image_alt() ile doldurun:\n  " . implode("\n  ", $altsiz));
        $this->assertSame([], $kuralsiz, "loading taşımayan görsel — varsayılan lazy:\n  " . implode("\n  ", $kuralsiz));
    }

    /**
     * Boş kalabilecek alt değerleri zincirden geçmeli.
     *
     * alt="{{ $post->title }}" doğru görünüyor ama başlık boşsa alt de boş
     * kalıyor — denetimin yakalayamadığı tek şey buydu, çünkü nitelik yerinde.
     */
    public function test_alt_values_that_could_be_empty_go_through_the_chain(): void
    {
        $ciplak = [];

        foreach ($this->frontViews() as $file) {
            $source = (string) file_get_contents($file);
            $relative = str_replace(resource_path('views') . '/', '', $file);

            foreach ($this->imageTags($source) as [$line, $tag]) {
                if (preg_match('/\balt\s*=\s*"\{\{\s*(.+?)\s*\}\}"/s', $tag, $m) !== 1) {
                    continue;
                }

                if (! str_contains($m[1], 'image_alt(') && ! str_contains($m[1], '$alt')) {
                    $ciplak[] = "{$relative}:{$line}  alt=\"{{ {$m[1]} }}\"";
                }
            }
        }

        sort($ciplak);

        $this->assertSame(
            [],
            $ciplak,
            "Zincirden geçmeyen alt değeri — boş kalabilir:\n  " . implode("\n  ", $ciplak),
        );
    }

    /** Denetimin gerçekten görünümleri okuduğu. */
    public function test_the_check_actually_reads_the_views(): void
    {
        $tags = 0;

        foreach ($this->frontViews() as $file) {
            $tags += count($this->imageTags((string) file_get_contents($file)));
        }

        $this->assertGreaterThan(10, $tags, 'Görsel etiketleri okunamıyor; denetim ölçmüyor');
    }

    // ── Yardımcılar ──

    private function resolver(): ImageAltResolver
    {
        $resolver = app(ImageAltResolver::class);
        $resolver->flush();

        return $resolver;
    }

    /**
     * Bir görünümün <img> etiketleri: [satır, etiket].
     *
     * Blade ifadeleri maskeleniyor; maskelenmezse alt="{{ $x->title }}"
     * içindeki ok işaretinin ">"si etiketi erken kapatıyor ve nitelikler
     * görünmüyor. Uzunluk korunuyor ki satır numarası kaymasın.
     *
     * @return list<array{0: int, 1: string}>
     */
    private function imageTags(string $source): array
    {
        $blank = static fn (array $m): string => (string) preg_replace('/[^\n]/', ' ', $m[0]);
        $masked = $source;

        foreach (['/\{\{--.*?--\}\}/s', '/\{\{.*?\}\}/s', '/\{!!.*?!!\}/s'] as $pattern) {
            $masked = (string) preg_replace_callback($pattern, $blank, $masked);
        }

        $masked = str_replace('->', '  ', $masked);

        preg_match_all('/<img\b[^>]*?>/s', $masked, $matches, PREG_OFFSET_CAPTURE);

        $tags = [];

        foreach ($matches[0] as [$tag, $offset]) {
            $tags[] = [
                substr_count(substr($source, 0, (int) $offset), "\n") + 1,
                substr($source, (int) $offset, strlen($tag)),
            ];
        }

        return $tags;
    }

    /**
     * Ziyaretçinin gördüğü görünümler.
     *
     * Yönetim paneli kapsam dışı (yöneticinin kendi arayüzü), e-posta
     * şablonları da öyle: posta istemcileri loading niteliğini tanımıyor.
     *
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
            $disarida = ['/admin/', '/admin-theme/', '/emails/', '/exports/', '/vendor/'];

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
}
