<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Satır içi stil yasağının bekçisi.
 *
 * Kural: biçim her zaman sınıfla verilir. Yasak yazılıydı ama bekçisi yoktu ve
 * görünümlerde on üç yerde satır içi stil birikmişti — biri iki ayrı ekranda
 * kopyalanmış aynı biçimdi. Böyle biriken stiller tasarımı tek yerden
 * değiştirilemez hâle getiriyor: aynı kutunun rengi ekranın birinde
 * değişiyor, ötekinde kalıyor.
 *
 * İki durum yasağın dışında ve ikisi de kaçınılabilir değil:
 *
 *  - **E-posta gövdeleri.** Posta istemcileri harici stil sayfasını atıyor;
 *    biçim etiketin üstünde olmak zorunda.
 *  - **Çalışma anında hesaplanan değerler.** Bir ilerleme çubuğunun doluluğu
 *    sabit bir sınıfla anlatılamaz. Bunlar CSS değişkeni olarak veriliyor
 *    (`style="--cmp-progress: 40%"`), yani etikete giden şey biçimin kendisi
 *    değil, tek bir sayı; biçim yine stil sayfasında duruyor.
 */
class InlineStylesAreForbiddenTest extends TestCase
{
    /**
     * Taramanın dışında kalan dizinler.
     */
    private const EXCLUDED = [
        // Posta istemcileri harici stili atıyor.
        'emails',
        // Uyarlanmamış hazır tasarımlar.
        'admin-theme',
        // Çerçevenin kendi görünümleri.
        'vendor',
    ];

    /**
     * Gerekçesi yazılı tek tek istisnalar: dosya => açıklama.
     */
    private const ALLOWED = [
        // Panelin stil sayfasının ulaşamadığı bir belgeye yazılıyor.
        'admin/mail-logs/show.blade.php' => 'iframe belgesine yazılan yedek metin',
    ];

    public function test_no_view_carries_an_inline_style(): void
    {
        $views = $this->views();

        $this->assertGreaterThan(100, count($views), 'Görünüm ağacı taranamadı.');

        $offenders = [];

        foreach ($views as $path => $source) {
            if (array_key_exists($path, self::ALLOWED)) {
                continue;
            }

            foreach (explode("\n", $source) as $index => $line) {
                if (preg_match('/\bstyle\s*=\s*"([^"]*)"/', $line, $match) !== 1) {
                    continue;
                }

                // Yalnız CSS değişkeni taşıyan stil, biçim değil değer taşıyor.
                if ($this->onlyCustomProperties($match[1])) {
                    continue;
                }

                $offenders[] = $path . ':' . ($index + 1);
            }
        }

        sort($offenders);

        $this->assertSame(
            [],
            $offenders,
            "Satır içi stil — biçimi sınıfa taşıyın:\n  " . implode("\n  ", $offenders),
        );
    }

    /**
     * İstisna listesi bayatlamasın: adı yazılı dosya duruyor mu?
     */
    public function test_the_allowed_list_still_points_at_real_files(): void
    {
        foreach (array_keys(self::ALLOWED) as $path) {
            $this->assertFileExists(
                resource_path('views/' . $path),
                "İstisna listesindeki {$path} artık yok; satırı kaldırın.",
            );
        }
    }

    /**
     * Bildirilen değer yalnızca CSS değişkeni mi?
     *
     * "--cmp-progress: 40%" geçiyor; "width: 40%" geçmiyor.
     */
    private function onlyCustomProperties(string $declarations): bool
    {
        $parts = array_filter(array_map('trim', explode(';', $declarations)));

        if ($parts === []) {
            return false;
        }

        return array_all($parts, static fn (string $part): bool => str_starts_with($part, '--'));
    }

    /** @return array<string, string> */
    private function views(): array
    {
        $found = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $relative = str_replace(resource_path('views/'), '', $file->getPathname());

            foreach (self::EXCLUDED as $directory) {
                if (str_starts_with($relative, $directory . '/')) {
                    continue 2;
                }
            }

            $found[$relative] = (string) file_get_contents($file->getPathname());
        }

        ksort($found);

        return $found;
    }
}
