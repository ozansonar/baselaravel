<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Tarayıcının kendi kutuları (`alert`, `confirm`, `prompt`) kullanılmaz.
 *
 * Üç sebebi var ve üçü de kullanıcının gördüğü şeyle ilgili: kutu sayfayı
 * kilitliyor, ekranın diliyle konuşmuyor (düğmeler tarayıcının dilinde) ve
 * biçimlenmiyor — panelin geri kalanının yanında yabancı duruyor. Yerine
 * `AdminModal` (panel) ve `showConfirmModal` / `showResultModal` (ön yüz) var.
 *
 * Kural CLAUDE.md'de yazılıydı ama bekçisi yoktu. v2 denetimi profil
 * ekranındaki bir `alert()`i temizledi, geriye üç `window.confirm()` ve bir
 * `alert()` kaldı — hepsi "modal yüklenmemişse son çare" gerekçesiyle. Gerekçe
 * makuldü, dayanağı yanlıştı: modal işaretlemesi de betiği de her iki
 * layout'a **koşulsuz** basılıyor, yani o dallara hiç girilmiyordu. Ölü kod
 * olarak durup kuralı çiğniyorlardı.
 *
 * Bu yüzden sınav iki parçalı: kutular yasak **ve** yerlerini alan kutuların
 * gerçekten her sayfada bulunduğu doğrulanıyor. İkincisi olmadan birincisi
 * onay penceresiz bir silme düğmesine dönüşebilirdi.
 */
final class BrowserDialogsAreForbiddenTest extends TestCase
{
    /**
     * Taranan dizinler — yalnız kendi yazdığımız JavaScript.
     *
     * `public/assets/vendor` dışarıda: hazır kütüphanelerin içindekini
     * değiştiremiyoruz ve kural bizim kodumuz için yazılmıştı.
     *
     * @var list<string>
     */
    private const ROOTS = [
        'public/js',
        'public/assets/admin/js',
    ];

    /**
     * Kutuların dayandığı işaretleme: layout => [partial, element kimliği].
     *
     * Kimlikler betiklerin `getElementById` ile aradığı değerlerin ta kendisi.
     * Biri yeniden adlandırılırsa kutu sessizce açılmaz hâle gelirdi ve
     * yasağın dayanağı çökerdi.
     *
     * @var array<string, array<int, array{0: string, 1: string}>>
     */
    private const DIALOG_MARKUP = [
        'views/layouts/admin.blade.php' => [
            ['views/partials/admin/global-modals.blade.php', 'globalConfirmModal'],
            ['views/partials/admin/global-modals.blade.php', 'globalStatusModal'],
        ],
        'views/layouts/app.blade.php' => [
            ['views/partials/confirm-modal.blade.php', 'confirmModal'],
            ['views/partials/result-modal.blade.php', 'resultModal'],
        ],
    ];

    public function test_no_script_of_ours_opens_a_browser_dialog(): void
    {
        $offenders = [];

        foreach ($this->scripts() as $path => $contents) {
            foreach ($this->dialogCalls($contents) as [$line, $call]) {
                $offenders[] = sprintf('%s:%d → %s', $path, $line, $call);
            }
        }

        sort($offenders);

        $this->assertSame(
            [],
            $offenders,
            "Tarayıcı kutusu kullanılmış; AdminModal / showConfirmModal kullanın:\n  "
                . implode("\n  ", $offenders),
        );
    }

    /**
     * Yasağın dayanağı: kutuların işaretlemesi her sayfada basılıyor.
     */
    public function test_every_layout_carries_the_dialogs_that_replace_them(): void
    {
        foreach (self::DIALOG_MARKUP as $layout => $dialogs) {
            $layoutSource = $this->resource($layout);

            foreach ($dialogs as [$partial, $elementId]) {
                $include = str_replace(
                    ['views/', '/', '.blade.php'],
                    ['', '.', ''],
                    $partial,
                );

                $this->assertStringContainsString(
                    "@include('{$include}')",
                    $layoutSource,
                    "{$layout} içinde {$include} yok; kutunun işaretlemesi sayfaya basılmıyor.",
                );

                $this->assertStringContainsString(
                    'id="' . $elementId . '"',
                    $this->resource($partial),
                    "{$partial} içinde #{$elementId} yok; betik kutuyu bulamaz.",
                );
            }
        }
    }

    /**
     * Kutuyu bulamayan bir yol sessizce geçmemeli.
     *
     * Yedek dallar kaldırılırken yerlerine "işlemi yapma ve sebebini yaz"
     * kondu. Sessizce `return` etselerdi düğme tıklanır, hiçbir şey olmaz ve
     * kimse nedenini bilemezdi.
     */
    public function test_the_missing_dialog_paths_say_why_nothing_happened(): void
    {
        $expected = [
            'public/assets/admin/js/bulk-actions.js'   => 'AdminModal yüklenmedi',
            'public/assets/admin/js/inline-actions.js' => 'AdminModal yüklenmedi',
            // Ön yüz betiklerinde artık yazılı metin yok; geliştiriciye
            // bakan konsol satırı İngilizce, ziyaretçiye görünmüyor.
            'public/js/app.js'                         => 'is missing',
        ];

        foreach ($expected as $file => $needle) {
            $this->assertStringContainsString(
                $needle,
                (string) file_get_contents(base_path($file)),
                "{$file} kutuyu bulamadığında sebebini yazmıyor.",
            );
        }
    }

    /**
     * `alert(` / `confirm(` / `prompt(` çağrıları.
     *
     * `AdminModal.confirm(` ve `confirmDelete(` gibi adlar dışarıda kalıyor:
     * aranan şey nokta ya da harf öncesi olmayan çıplak çağrı. `window.` ön
     * eki ayrıca aranıyor, çünkü o da noktayla geliyor ama tarayıcı kutusunun
     * ta kendisi.
     *
     * Anahtar konum, satır değil: aynı satırdaki iki ihlalden biri
     * ötekini silmemeli.
     *
     * @return array<int, string> satır numarası => çağrı
     */
    private function dialogCalls(string $contents): array
    {
        $code = $this->withoutComments($contents);

        $byOffset = [];

        foreach (['/window\.(alert|confirm|prompt)\s*\(/', '/(?<![\w.$])(alert|confirm|prompt)\s*\(/'] as $pattern) {
            if (preg_match_all($pattern, $code, $matches, PREG_OFFSET_CAPTURE) === 0) {
                continue;
            }

            foreach ($matches[0] as [$call, $offset]) {
                $byOffset[$offset] = $call;
            }
        }

        ksort($byOffset);

        $found = [];

        foreach ($byOffset as $offset => $call) {
            $found[] = [substr_count(substr($code, 0, (int) $offset), "\n") + 1, $call];
        }

        return $found;
    }

    /**
     * Yorumları aynı uzunlukta boşlukla değiştirir.
     *
     * Silmek yerine boşaltmak, satır numaralarının kaymamasını sağlıyor —
     * yoksa hata mesajı yanlış satırı gösterirdi. Yorumların taranmaması ise
     * şart: bu dosyanın kendi açıklaması yasakladığı çağrıları *anlatıyor* ve
     * kendi kuralına takılmamalı.
     */
    private function withoutComments(string $code): string
    {
        $blank = static fn (array $m): string => (string) preg_replace('/[^\n]/', ' ', $m[0]);

        $code = (string) preg_replace_callback('#/\*.*?\*/#s', $blank, $code);

        return (string) preg_replace_callback('#//[^\n]*#', $blank, $code);
    }

    /**
     * @return array<string, string>
     */
    private function scripts(): array
    {
        $found = [];

        foreach (self::ROOTS as $root) {
            $directory = base_path($root);

            if (! is_dir($directory)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                if ($file->getExtension() !== 'js') {
                    continue;
                }

                $found[$root . '/' . $file->getFilename()] = (string) file_get_contents($file->getPathname());
            }
        }

        ksort($found);

        return $found;
    }

    private function resource(string $relative): string
    {
        return (string) file_get_contents(resource_path($relative));
    }
}
