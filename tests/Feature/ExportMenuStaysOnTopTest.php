<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Dışa aktarma menüsü altındaki bloğun arkasında kalmamalı.
 *
 * AOS animasyonu bittiğinde `transform: translateZ(0)` bırakıyor ve o transform
 * kalıcı: `data-aos` taşıyan her eleman kalıcı bir **yığın bağlamı** oluşturuyor.
 * Menünün `z-index: 1055` değeri o bağlamın içinde kalıyor, dolayısıyla tek
 * başına dışarıdaki bir kardeşi aşamıyor. Menüyü taşıyan kap da yükseltilmezse
 * menü, kendisinden sonra gelen (ve o da `data-aos` taşıyan) bloğun arkasında
 * kalıyor.
 *
 * Sistem sağlık ekranında tam olarak bu oldu: menü bir kartın içinde değil
 * sayfa başlığında duruyor ve hemen altındaki durum bloğu iki seçeneği
 * örtüyordu. Aynı gizli kusur dokuz ekranda daha vardı.
 *
 * Bu sınav yığın sırasını ölçemez — onu tarayıcı bilir. Ölçebildiği şey daha
 * yararlı: menünün konduğu her kap türünün CSS'te yükseltilmiş olması. Yarın
 * biri menüyü yeni bir kaba koyduğunda burası düşüyor ve sebebini söylüyor.
 */
final class ExportMenuStaysOnTopTest extends TestCase
{
    /**
     * Menünün içine konabildiği, CSS'te yükseltilmesi gereken kap sınıfları.
     *
     * @var list<string>
     */
    private const KNOWN_CONTAINERS = ['card-dark', 'card-glass', 'page-header'];

    private function css(): string
    {
        return (string) file_get_contents(public_path('assets/admin/css/styles.css'));
    }

    /**
     * @return array<string, string> görünüm yolu => kap sınıfı
     */
    private function containers(): array
    {
        $found = [];

        /** @var list<string> $views */
        $views = glob(resource_path('views/admin/**/*.blade.php'), GLOB_BRACE) ?: [];

        foreach ($views as $view) {
            $source = (string) file_get_contents($view);

            foreach ($this->positionsOf('<x-export-menu', $source) as $position) {
                $before = substr($source, 0, $position);

                preg_match_all(
                    '/class="([^"]*(?:' . implode('|', self::KNOWN_CONTAINERS) . ')[^"]*)"/',
                    $before,
                    $matches,
                );

                $classes = $matches[1] === [] ? '' : (string) end($matches[1]);

                $container = 'BİLİNMEYEN';

                foreach (self::KNOWN_CONTAINERS as $known) {
                    if (str_contains($classes, $known)) {
                        $container = $known;

                        break;
                    }
                }

                $found[str_replace(resource_path('views/'), '', $view)] = $container;
            }
        }

        return $found;
    }

    /**
     * @return list<int>
     */
    private function positionsOf(string $needle, string $haystack): array
    {
        $positions = [];
        $offset = 0;

        while (($position = strpos($haystack, $needle, $offset)) !== false) {
            $positions[] = $position;
            $offset = $position + 1;
        }

        return $positions;
    }

    public function test_the_menu_itself_is_raised(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.export-menu\s*\{[^}]*z-index:\s*\d+/',
            $this->css(),
            'export-menu kendi z-index değerini kaybetmiş.',
        );
    }

    /**
     * Menünün konduğu her kap türü CSS'te yükseltilmiş olmalı.
     */
    public function test_every_container_the_menu_sits_in_is_raised(): void
    {
        $css = $this->css();

        foreach (array_unique(array_values($this->containers())) as $container) {
            $this->assertNotSame(
                'BİLİNMEYEN',
                $container,
                'Dışa aktarma menüsü tanınmayan bir kabın içinde; kabı ' .
                'ExportMenuStaysOnTopTest::KNOWN_CONTAINERS listesine ekleyin ve ' .
                'styles.css içinde yükseltin.',
            );

            $this->assertStringContainsString(
                ".{$container}:has(.export-menu)",
                $css,
                "styles.css içinde .{$container}:has(.export-menu) kuralı yok — " .
                'o ekranlarda menü altındaki bloğun arkasında kalır.',
            );
        }
    }

    /**
     * Yükseltme kuralı `position` olmadan işe yaramaz: z-index yalnız
     * konumlandırılmış elemanlarda geçerli.
     */
    public function test_the_raising_rule_also_positions_the_container(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.page-header:has\(\.export-menu\)\s*\{[^}]*position:\s*relative[^}]*z-index:\s*\d+/s',
            $this->css(),
            'Kap yükseltiliyor ama konumlandırılmıyor; z-index etkisiz kalır.',
        );
    }

    /**
     * Bu ekran hatanın ilk göründüğü yer; menüsünü kaybetmesin.
     */
    public function test_the_system_health_screen_still_offers_the_export_menu(): void
    {
        $this->assertStringContainsString(
            '<x-export-menu',
            (string) file_get_contents(resource_path('views/admin/system-health/index.blade.php')),
        );
    }
}
