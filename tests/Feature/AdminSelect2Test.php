<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Select2 is served from the project itself — no CDN, no build step — and the
 * panel loads it on every page, so a moved or renamed file has to fail here
 * rather than as a plain <select> in production.
 */
class AdminSelect2Test extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function assets(): array
    {
        $files = [
            'public/assets/vendor/select2/js/select2.min.js',
            'public/assets/vendor/select2/js/i18n/tr.js',
            'public/assets/vendor/select2/js/i18n/en.js',
            'public/assets/vendor/select2/css/select2.min.css',
            'public/assets/vendor/select2/css/select2-bootstrap-5-theme.min.css',
            'public/assets/admin/js/select2-init.js',
        ];

        return array_combine($files, array_map(static fn (string $f): array => [$f], $files));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('assets')]
    public function test_the_library_is_hosted_by_the_project(string $path): void
    {
        $this->assertFileExists(base_path($path), "{$path} eksik — Select2 self-host edilmiş olmalı");
    }

    public function test_the_panel_loads_select2_and_its_theme(): void
    {
        $this->seedAuthorization();

        $admin = User::create([
            'first_name' => 'Select',
            'last_name'  => 'Two',
            'email'      => 'select2@example.com',
            'password'   => 'password',
            'is_active'  => true,
        ]);

        $admin->roles()->attach(Role::where('slug', 'admin')->firstOrFail());

        $html = $this->actingAs($admin)->get('/admin/users')->assertOk()->getContent() ?: '';

        foreach ([
            'assets/vendor/select2/css/select2.min.css',
            'assets/vendor/select2/css/select2-bootstrap-5-theme.min.css',
            'assets/vendor/select2/js/select2.min.js',
            'assets/admin/js/select2-init.js',
        ] as $asset) {
            $this->assertStringContainsString($asset, $html, "{$asset} panel layout'una eklenmemiş");
        }
    }

    /**
     * jQuery has to be in the page before Select2, which is a jQuery plugin.
     */
    public function test_jquery_is_loaded_before_select2(): void
    {
        $layout = file_get_contents(base_path('resources/views/layouts/admin.blade.php')) ?: '';

        $jquery = strpos($layout, 'assets/vendor/jquery/jquery');
        $select2 = strpos($layout, 'assets/vendor/select2/js/select2.min.js');

        $this->assertIsInt($jquery);
        $this->assertIsInt($select2);
        $this->assertLessThan($select2, $jquery, 'Select2 jQuery den önce yükleniyor');
    }

    /**
     * Arama kutusu her listede açık olmalı.
     *
     * Select2'nin kendi varsayılanı kısa listelerde arama kutusunu gizliyor.
     * Panelde bu, aynı görünen iki açılır listeden birinde yazılabilip
     * ötekinde yazılamaması demekti; birkaç ekran bunu tek tek "always" diyerek
     * aşmaya çalışıyordu. Kural artık tek yerde ve burada bekleniyor.
     */
    public function test_the_search_box_is_on_for_every_list(): void
    {
        $init = file_get_contents(base_path('public/assets/admin/js/select2-init.js')) ?: '';

        $this->assertMatchesRegularExpression(
            '/minimumResultsForSearch:\s*0\b/',
            $init,
            'Select2 varsayılanı arama kutusunu kısa listelerde gizliyor',
        );
    }

    /**
     * Hiçbir alan Select2'nin dışında kalmamalı.
     *
     * Init dosyası paneldeki her <select>'i kendiliğinden kuruyor; tek kaçış
     * yolu data-no-select2 / .no-select2. Bir ekran bunu kullanırsa o alan
     * ötekilerden farklı görünür ve aranamaz kalır, o yüzden burada duruyor.
     */
    public function test_no_admin_select_opts_out_of_select2(): void
    {
        $kacanlar = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views/admin'), \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());

            if (preg_match('/data-no-select2|class="[^"]*\bno-select2\b/', $contents)) {
                $kacanlar[] = str_replace(base_path() . '/', '', $file->getPathname());
            }
        }

        $this->assertSame([], $kacanlar, "Select2 dışında bırakılmış alan:\n" . implode("\n", $kacanlar));
    }

    /**
     * The panel's own stylesheet carries the dark/light overrides, so it has to
     * win over the Bootstrap 5 theme's hard-coded light colours.
     */
    public function test_the_panel_stylesheet_comes_after_the_select2_theme(): void
    {
        $layout = file_get_contents(base_path('resources/views/layouts/admin.blade.php')) ?: '';

        $theme = strpos($layout, 'select2-bootstrap-5-theme.min.css');
        $panel = strpos($layout, 'assets/admin/css/styles.css');

        $this->assertIsInt($theme);
        $this->assertIsInt($panel);
        $this->assertLessThan($panel, $theme, 'Panel stilleri Select2 temasından önce yükleniyor');
    }
}
