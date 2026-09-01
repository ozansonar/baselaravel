<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Belgelerdeki "Test: `X`" göndermeleri gerçek bir dosyayı göstermeli.
 *
 * Yol haritasının her maddesinde bir kabul ölçütü ve onu sınayan testin adı
 * yazıyor. Gönderme tutmadığında ölçüt doğrulanamaz hâle geliyor: okuyan kişi
 * adı arıyor, bulamıyor ve maddenin gerçekten sınanıp sınanmadığını bilemiyor.
 *
 * 1 Eylül 2026'daki taramada altı gönderme boşa çıktı — `ApiPushTokenTest`,
 * `ApiHealthTest`, `PwaManifestTest`, `ServiceWorkerTest`,
 * `ApiAccountDeletionTest`, `ApiAccountCommentsTest`. Hiçbiri yanlış yazılmış
 * değildi; testler yazılırken başka dosyalarda birleştirilmiş, belge yerinde
 * kalmıştı. Kural zaten yazılıydı ("her maddenin en az bir testi var"), bekçisi
 * yoktu.
 */
final class DocsCiteRealTestsTest extends TestCase
{
    /**
     * Test adı gibi görünmeyen, bilerek serbest bırakılmış ifadeler.
     *
     * @var list<string>
     */
    private const PROSE = [
        'mevcut suite',
    ];

    public function test_every_test_named_in_the_docs_exists(): void
    {
        $missing = [];

        foreach ($this->docs() as $path => $contents) {
            foreach ($this->citedTests($contents) as $line => $name) {
                if ($this->existsInSuite($name)) {
                    continue;
                }

                $missing[] = sprintf('%s:%d → %s', $path, $line, $name);
            }
        }

        sort($missing);

        $this->assertSame(
            [],
            $missing,
            "Belgede adı geçen test dosyası yok — ya test taşındı ya belge bayat:\n  "
                . implode("\n  ", $missing),
        );
    }

    /**
     * `Test:` satırlarında anılan sınıf adları.
     *
     * Yalnız `Test:` ile başlayan kabul ölçütü satırlarına bakılıyor: gövde
     * metninde bir test adı anmak (neden yazıldığını anlatmak gibi) serbest ve
     * o metin taşınan bir dosyayla birlikte bayatlamıyor.
     *
     * @return array<int, string> satır numarası => sınıf adı
     */
    private function citedTests(string $contents): array
    {
        $found = [];

        foreach (explode("\n", $contents) as $index => $line) {
            if (! str_contains($line, 'Test: ')) {
                continue;
            }

            $after = substr($line, (int) strpos($line, 'Test: '));

            foreach (self::PROSE as $prose) {
                if (str_contains($after, $prose)) {
                    continue 2;
                }
            }

            if (preg_match_all('/`([A-Za-z][A-Za-z0-9_\/]*Test)`/', $after, $matches) === 0) {
                continue;
            }

            foreach ($matches[1] as $name) {
                $found[$index + 1] = $name;
            }
        }

        return $found;
    }

    /**
     * `Api/ApiAuthTest` gibi yollar da, çıplak sınıf adları da kabul ediliyor:
     * belge dizini yazmak zorunda değil, testin nerede durduğu ayrıntı.
     */
    private function existsInSuite(string $name): bool
    {
        $file = basename($name) . '.php';

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(base_path('tests'), \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $entry) {
            /** @var \SplFileInfo $entry */
            if ($entry->getFilename() === $file) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, string>
     */
    private function docs(): array
    {
        $found = [];

        foreach ((array) glob(base_path('docs/*.md')) as $path) {
            $found['docs/' . basename((string) $path)] = (string) file_get_contents((string) $path);
        }

        $found['README.md'] = (string) file_get_contents(base_path('README.md'));

        ksort($found);

        return $found;
    }
}
