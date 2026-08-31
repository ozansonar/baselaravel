<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Onay ve durum pencereleri düz metin gösterir.
 *
 * Kutulara metin textContent ile yazılıyor: mesaja gömülen bir etiket ekranda
 * etiket olarak görünüyordu — "<strong>Bahar Kampanyası</strong> popup'ını
 * silmek istediğinizden emin misiniz?". innerHTML'e geçmek çözüm değil, çünkü
 * mesajlarda kullanıcının girdiği içerik var (duyuru başlığı, dosya adı):
 * sayfaya HTML sokmuş olurduk.
 *
 * İki yönlü denetim. Pencereler etiketleri kendileri atıyor (savunma), ve
 * hiçbir çağrı mesaja etiket gömmüyor (kaynak). İkincisi olmadan ilki
 * çalışsa bile ekranda "Bahar Kampanyası popup'ını..." yerine biçimlendirme
 * niyeti kaybolmuş bir cümle kalırdı; adın gösterileceği yer ayrıntı kutusu.
 */
final class ConfirmDialogsArePlainTextTest extends TestCase
{
    /**
     * Pencerelerin metin yazdığı yerler ve temizleyicileri.
     *
     * @var array<string, string>
     */
    private const DIALOGS = [
        'public/assets/admin/js/global-modals.js' => 'plain',
        'public/js/app.js'                        => 'plainText',
    ];

    /**
     * Mesaj taşıyan seçenekler; bunlara etiket gömülemez.
     *
     * @var list<string>
     */
    private const TEXT_OPTIONS = ['title', 'message', 'warning', 'detailTitle', 'detailMeta', 'confirmText', 'buttonText'];

    public function test_the_dialogs_strip_tags_before_printing(): void
    {
        foreach (self::DIALOGS as $file => $helper) {
            $source = (string) file_get_contents(base_path($file));

            $this->assertMatchesRegularExpression(
                '/function\s+' . preg_quote($helper, '/') . '\s*\(|' . preg_quote($helper, '/') . '\s*=\s*function/',
                $source,
                "{$file} → metni düz metne indirgeyen yardımcı yok",
            );

            // Etiketleri atan adım gerçekten var mı?
            $this->assertStringContainsString(
                'replace(/<[^>]*>/g',
                $source,
                "{$file} → etiketler atılmıyor",
            );
        }
    }

    /**
     * Kutulara metin textContent ile yazılmalı.
     *
     * innerHTML'e dönülürse kullanıcının girdiği başlık sayfaya HTML olarak
     * girer; denetim bunun sessizce olmasını engelliyor.
     */
    public function test_the_dialogs_never_print_their_text_as_html(): void
    {
        foreach (array_keys(self::DIALOGS) as $file) {
            $source = (string) file_get_contents(base_path($file));

            preg_match_all('/(gcm|gsm|resultModalBody|resultModalTitle|confirmModalBody|confirmModalTitle)[^\n]*\.innerHTML\s*=/', $source, $matches);

            $this->assertSame(
                [],
                $matches[0],
                "{$file} → pencere metni HTML olarak basılıyor:\n  " . implode("\n  ", $matches[0]),
            );
        }
    }

    /** Hiçbir çağrı mesaja etiket gömmemeli. */
    public function test_no_caller_puts_markup_into_a_dialog(): void
    {
        $bulgular = [];

        foreach ($this->sources() as $file) {
            $source = (string) file_get_contents($file);
            $relative = str_replace(base_path() . '/', '', $file);

            foreach (self::TEXT_OPTIONS as $option) {
                // "message: '...<b>...'" — tek ya da çift tırnaklı değer içinde etiket
                $pattern = '/\b' . preg_quote($option, '/') . '\s*:\s*'
                    . '((?:\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*")(?:\s*\+\s*[^,\n]+)*)/';

                preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE);

                foreach ($matches[1] as [$value, $offset]) {
                    if (preg_match('/<\/?[a-z][a-z0-9]*(\s[^>]*)?>/i', $value) !== 1) {
                        continue;
                    }

                    $line = substr_count(substr($source, 0, (int) $offset), "\n") + 1;
                    $bulgular[] = "{$relative}:{$line}  {$option}: " . mb_substr(trim($value), 0, 70);
                }
            }
        }

        sort($bulgular);

        $this->assertSame(
            [],
            $bulgular,
            "Onay penceresine gömülmüş etiket — adı detailTitle ile geçirin:\n  " . implode("\n  ", $bulgular),
        );
    }

    /**
     * Denetimin gerçekten dosyaları taradığı.
     *
     * Tarama kökü bozulsa liste boşalır ve denetim hiçbir şey bulmadan yeşil
     * geçerdi.
     */
    public function test_the_check_actually_reads_the_project(): void
    {
        $files = $this->sources();

        $this->assertGreaterThan(40, count($files), 'Kaynak dosyalar okunamıyor; denetim ölçmüyor');

        $cagri = 0;

        foreach ($files as $file) {
            $cagri += preg_match_all('/AdminModal\.(confirm|status)|showResultModal|showConfirmModal/', (string) file_get_contents($file));
        }

        $this->assertGreaterThan(20, $cagri, 'Onay penceresi çağrıları bulunamıyor');
    }

    /**
     * Onay penceresi çağırabilecek her dosya.
     *
     * @return list<string>
     */
    private function sources(): array
    {
        $files = [];

        foreach (['public/js', 'public/assets/admin/js', 'resources/views'] as $directory) {
            $path = base_path($directory);

            if (! is_dir($path)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                $name = $file->getFilename();

                // admin-theme hazır HTML tasarım referansı; vendor kütüphaneleri
                // bizim pencerelerimizi çağırmıyor.
                if (! $file->isFile()
                    || (! str_ends_with($name, '.js') && ! str_ends_with($name, '.blade.php'))
                    || str_contains($file->getPathname(), '/admin-theme/')
                    || str_contains($file->getPathname(), '/vendor/')
                    || str_ends_with($name, '.min.js')) {
                    continue;
                }

                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
