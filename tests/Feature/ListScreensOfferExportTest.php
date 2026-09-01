<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Kayıt listeleyen her panel ekranı dışa aktarma sunar.
 *
 * Kural projenin kendi şartı: yönetici gördüğü listeyi Excel, CSV ya da PDF
 * olarak indirebilmeli. Bugün otuz küsur liste bu kurala uyuyor — ama uyduğunu
 * kimse sınamıyordu. Yeni bir ekran eklendiğinde eksikliği fark etmenin tek
 * yolu birinin gözüne çarpmasıydı.
 *
 * Kapsam elle yazılmış bir listeden değil **görünümlerin kendisinden**
 * besleniyor: tablo çizen her panel görünümü sınava giriyor. Yarın eklenen
 * ekran kendiliğinden kapsama giriyor; girmemesi gerekiyorsa gerekçesi
 * aşağıya yazılıyor ve o gerekçenin bayatlamadığı ayrıca sınanıyor.
 *
 * @see \Tests\Feature\ListExportTest dışa aktarmanın kendisi orada sınanıyor
 */
final class ListScreensOfferExportTest extends TestCase
{
    /**
     * Dışa aktarma sunmayan, sunması da gerekmeyen ekranlar.
     *
     * Her satırın gerekçesi yazılı: liste olmayan bir ekranı dosyaya dökmek
     * anlamsız, listesi başka ekranda olanı iki kez sunmak gereksiz.
     *
     * @var array<string, string>
     */
    private const EXEMPT = [
        'admin/analytics/index.blade.php' => 'Grafik panosu; satırları özet, ham ziyaret listesi analytics/visits ekranında ve orada dışa aktarma var.',
        'admin/analytics/live.blade.php'  => 'Son birkaç dakikayı gösteren canlı ekran; içeriği saniyede değişiyor, dosyaya dökülen şey indirildiği anda eskimiş olur. Ekran "Tüm Kayıtlar" ile visits listesine götürüyor.',
        'admin/audit-logs/show.blade.php' => 'Tek bir denetim kaydının detayı; liste değil. Listenin dışa aktarması audit-logs/index ekranında.',
    ];

    /**
     * Dışa aktarma sunulduğunu gösteren işaretler.
     *
     * Bileşen olağan yol; rapor merkezi kendi düğmelerini basıyor çünkü hangi
     * raporun indirileceği adres satırındaki `type` ile geliyor.
     *
     * @var list<string>
     */
    private const SIGNALS = [
        '<x-export-menu',
        "route('admin.export'",
    ];

    public function test_every_admin_screen_with_a_table_offers_an_export(): void
    {
        $missing = [];

        foreach ($this->adminViewsWithTables() as $path => $contents) {
            if (isset(self::EXEMPT[$path])) {
                continue;
            }

            foreach (self::SIGNALS as $signal) {
                if (str_contains($contents, $signal)) {
                    continue 2;
                }
            }

            $missing[] = $path;
        }

        sort($missing);

        $this->assertSame(
            [],
            $missing,
            "Tablo çizen ekranda dışa aktarma yok — <x-export-menu> ekleyin ya da\n"
            . "gerekçesiyle birlikte bu sınıftaki EXEMPT listesine yazın:\n  "
                . implode("\n  ", $missing),
        );
    }

    /**
     * İstisna listesi bayatlamamalı.
     *
     * İki yönü var: listedeki dosya silinmiş olabilir (o zaman satır ölü), ya
     * da ekrana sonradan dışa aktarma eklenmiş olabilir (o zaman istisna
     * yanlış yere bakıyor). İkisi de listeyi sessizce güvenilmez yapar —
     * bekçinin kör noktası tam olarak burasıdır.
     */
    public function test_the_exemption_list_does_not_go_stale(): void
    {
        $views = $this->adminViewsWithTables();

        foreach (self::EXEMPT as $path => $reason) {
            $this->assertArrayHasKey(
                $path,
                $views,
                "{$path} artık tablo çizmiyor ya da silinmiş; istisna listesinden çıkarın.",
            );

            foreach (self::SIGNALS as $signal) {
                $this->assertStringNotContainsString(
                    $signal,
                    $views[$path],
                    "{$path} artık dışa aktarma sunuyor; istisna listesinden çıkarın.",
                );
            }

            $this->assertNotSame('', trim($reason), "{$path} için gerekçe yazılmamış.");
        }
    }

    /**
     * Sınav gerçekten bir şeye bakıyor olmalı.
     *
     * Tarayıcı bir gün yanlış dizine bakarsa (yol değişir, süzgeç bozulur)
     * boş küme üzerinde koşar ve sessizce yeşil kalır. Kapsamın dolu olduğunu
     * doğrulamak, bekçinin kör olup olmadığını sınamak demek.
     */
    public function test_the_check_actually_covers_the_panel(): void
    {
        $views = $this->adminViewsWithTables();

        $this->assertGreaterThan(
            20,
            count($views),
            'Tablo çizen panel ekranı sayısı beklenenden az; tarayıcı yanlış yere bakıyor olabilir.',
        );
    }

    /**
     * Tablo çizen panel görünümleri.
     *
     * @return array<string, string> yol => içerik
     */
    private function adminViewsWithTables(): array
    {
        $found = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views/admin'), \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());

            if (! str_contains($contents, '<table')) {
                continue;
            }

            $relative = str_replace(resource_path('views/'), '', $file->getPathname());

            $found[$relative] = $contents;
        }

        ksort($found);

        return $found;
    }
}
