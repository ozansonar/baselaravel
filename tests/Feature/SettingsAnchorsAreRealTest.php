<?php

declare(strict_types=1);

namespace Tests\Feature;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Ayarlar ekranına atılan çapalar gerçekten var olan sekmeleri gösteriyor.
 *
 * Panelin birkaç yeri kullanıcıyı ayarların belirli bir sekmesine gönderiyor
 * (`.../settings#stg-telegram`). Sekme kimliği yanlış yazıldığında hiçbir şey
 * kırılmıyor: sayfa açılıyor, çapa hiçbir yere denk gelmiyor ve kullanıcı
 * aradığı alanı bulamadan ilk sekmeye bakıyor. Sessiz olduğu için de gözden
 * kaçıyor — nitekim kaçtı: "Telegram Bildirimleri" düğmesi `#stg-system`'e
 * gidiyordu, oysa Telegram'ın kendi sekmesi var.
 */
final class SettingsAnchorsAreRealTest extends TestCase
{
    public function test_every_settings_anchor_points_at_a_real_tab(): void
    {
        $settings = (string) file_get_contents(
            resource_path('views/admin/settings/index.blade.php'),
        );

        preg_match_all('/class="stg-panel" id="(stg-[a-z-]+)"/', $settings, $found);
        $panels = $found[1];

        $this->assertNotEmpty($panels, 'Ayarlar ekranında hiç sekme bulunamadı; tarayıcı yanlış yere bakıyor.');

        $broken = [];

        foreach ($this->adminViews() as $path => $contents) {
            if (str_ends_with($path, 'admin/settings/index.blade.php')) {
                continue;
            }

            // `{{ route('admin.settings.index') }}#stg-telegram` — arada kaç
            // karakter olduğu Blade'in yazımına göre değişiyor, o yüzden orası
            // serbest bırakıldı.
            preg_match_all('/settings\.index.{0,30}?#(stg-[a-z-]+)/', $contents, $anchors);

            foreach ($anchors[1] as $anchor) {
                if (! in_array($anchor, $panels, true)) {
                    $broken[] = $path . ' → #' . $anchor;
                }
            }
        }

        sort($broken);

        $this->assertSame(
            [],
            $broken,
            "Ayarlar ekranında böyle bir sekme yok:\n  " . implode("\n  ", $broken)
            . "\n\nVar olanlar: " . implode(', ', $panels),
        );
    }

    /**
     * @return array<string, string>
     */
    private function adminViews(): array
    {
        $views = [];

        foreach (Finder::create()->files()->in(resource_path('views/admin'))->name('*.blade.php') as $file) {
            $views[str_replace(resource_path('views') . '/', '', $file->getPathname())] = $file->getContents();
        }

        return $views;
    }
}
