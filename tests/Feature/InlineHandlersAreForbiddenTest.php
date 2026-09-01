<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Nitelik olarak yazılan olay işleyicisi yasağının bekçisi.
 *
 * `onclick="..."` yazmak kolay ve alışkanlık; bir tanesi geri geldiğinde iki
 * şey birden oluyor:
 *
 *  1. **O düğme çalışmıyor.** İçerik güvenlik politikası
 *     `script-src-attr 'none'` diyor — nitelik değeri betiğin kendisi olduğu
 *     için oraya nonce konulamıyor, dolayısıyla tarayıcı onu çalıştırmıyor.
 *     Üstelik sessizce: ihlal yalnız işleyici tetiklendiğinde bildiriliyor,
 *     sayfa açılışında konsol temiz görünüyor.
 *  2. **Politikayı gevşetme baskısı doğuyor.** "Bir tanecik" için
 *     `'unsafe-inline'` eklemek, 219 işleyiciyi taşıyarak kazanılan korumayı
 *     tek satırda geri veriyor.
 *
 * Davranış artık `data-*` kancalarında ve merkezi bağlayıcıda:
 * `public/assets/admin/js/inline-actions.js`.
 */
class InlineHandlersAreForbiddenTest extends TestCase
{
    /**
     * Aranan nitelikler. `on` ile başlayan her şey değil — yalnız betik
     * taşıyan olay nitelikleri.
     */
    private const HANDLERS = [
        'click', 'change', 'input', 'submit', 'load', 'error',
        'focus', 'blur', 'keyup', 'keydown', 'keypress',
        'mouseover', 'mouseout', 'mouseenter', 'mouseleave',
        'dblclick', 'contextmenu', 'wheel', 'scroll',
    ];

    /**
     * Taramanın dışında kalan dizinler.
     */
    private const EXCLUDED = [
        // Uyarlanmamış hazır tasarımlar; Blade'e dönüşmeden kural aranmaz.
        'admin-theme',
        // E-posta gövdeleri tarayıcıda değil posta istemcisinde açılıyor;
        // orada CSP diye bir şey yok ve bazı istemciler betiği hiç
        // çalıştırmıyor. Yasağın konusu değiller.
        'emails',
        // Çerçevenin kendi görünümleri.
        'vendor',
    ];

    public function test_no_view_carries_an_inline_event_handler(): void
    {
        $views = $this->views();

        // Tarama gerçekten dosya buluyor mu? Yol bozulursa test sessizce
        // hiçbir şeye bakmadan yeşil biterdi.
        $this->assertGreaterThan(100, count($views), 'Görünüm ağacı taranamadı.');

        $pattern = '/\bon(?:' . implode('|', self::HANDLERS) . ')\s*=\s*["\']/i';

        $offenders = [];

        foreach ($views as $relative => $source) {
            foreach (explode("\n", $source) as $index => $line) {
                if (preg_match($pattern, $line) === 1) {
                    $offenders[] = $relative . ':' . ($index + 1) . '  ' . trim($line);
                }
            }
        }

        sort($offenders);

        $this->assertSame(
            [],
            $offenders,
            "Satır içi olay işleyicisi — davranışı data-* kancasına ve\n"
                . "public/assets/admin/js/inline-actions.js dosyasına taşıyın.\n"
                . "Politika script-src-attr 'none' diyor; bu işleyici çalışmaz:\n  "
                . implode("\n  ", array_map(
                    static fn (string $line): string => mb_substr($line, 0, 140),
                    $offenders,
                )),
        );
    }

    /**
     * Merkezi bağlayıcı her panel sayfasında yükleniyor mu?
     *
     * Kancalar yerinde olup bağlayıcı yüklenmezse panel sessizce ölür:
     * hiçbir düğme iş yapmaz ve konsolda tek bir hata bile çıkmaz.
     */
    public function test_the_binder_is_loaded_on_every_admin_page(): void
    {
        $layout = (string) file_get_contents(resource_path('views/layouts/admin.blade.php'));

        $this->assertStringContainsString('assets/admin/js/inline-actions.js', $layout);
    }

    /**
     * Bağlayıcı, kanca değerinden fonksiyon adı türetmemeli.
     *
     * `window[el.dataset.fn]()` biçiminde bir çağrı, sayfaya sızan bir değerin
     * keyfi bir fonksiyonu çalıştırmasına yol açar — politikayı kaldırıp
     * yerine aynı kapıyı açmak olur. Eylem haritası sabit kalmalı.
     */
    public function test_the_binder_never_resolves_a_function_from_markup(): void
    {
        $binder = (string) file_get_contents(public_path('assets/admin/js/inline-actions.js'));

        // Yorumlar taramanın dışında: dosyanın başındaki açıklama tam olarak
        // bu deseni *anlatıyor* ve kendi kuralına takılmamalı.
        $code = (string) preg_replace(['#/\*.*?\*/#s', '#//[^\n]*#'], '', $binder);

        foreach (['window[', 'eval(', 'new Function('] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $code,
                "Bağlayıcı '{$forbidden}' içeriyor; eylem haritası sabit kalmalı.",
            );
        }

        // Çağrılar yazılı olmalı: window.openDeleteModal gibi.
        $this->assertMatchesRegularExpression(
            '/cagir\(window\.[A-Za-z_$][\w$]*/',
            $code,
            'Bağlayıcı fonksiyonları açık adla çağırmıyor.',
        );
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
