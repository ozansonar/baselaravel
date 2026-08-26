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
