<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\HelpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Panel içi yardım.
 *
 * Asıl bekçi burada: sidebar'a yeni bir modül eklenip kılavuzu yazılmadığında
 * bu sınıf düşüyor. Panelde otuzdan fazla ekran var; devralan kişi bunların ne
 * işe yaradığını koddan çıkarmak zorunda kalmamalı.
 */
class AdminHelpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
        $this->seed(\Database\Seeders\LanguageSeeder::class);
    }

    private function admin(): User
    {
        $user = User::create([
            'first_name' => 'Yardim', 'last_name' => 'Yonetici',
            'email' => 'yardim@example.test', 'password' => 'sifre-123456', 'is_active' => true,
        ]);
        $user->markEmailAsVerified();
        $user->roles()->attach(Role::where('slug', 'admin')->firstOrFail()->id);

        return $user->fresh();
    }

    /**
     * Sidebar'daki her modülün bir kılavuzu olmalı.
     */
    public function test_every_module_in_the_sidebar_has_a_guide(): void
    {
        $sidebar = (string) file_get_contents(resource_path('views/partials/admin/sidebar.blade.php'));

        preg_match_all("/route\('(admin\.[a-z0-9.\-]+)'\)/", $sidebar, $matches);

        $modules = array_values(array_unique($matches[1]));

        $documented = array_column((array) config('help.guides', []), 'route');

        // Yardım ekranının kendisi listede olmak zorunda değil: kendi kendini
        // anlatan bir kılavuz kartı gereksiz.
        $modules = array_diff($modules, ['admin.help.index']);

        $missing = array_values(array_diff($modules, $documented));

        sort($missing);

        $this->assertSame(
            [],
            $missing,
            "Sidebar'da olup yardım ekranında anlatılmayan modüller var:\n" . implode("\n", $missing),
        );
    }

    /**
     * Kılavuzun bağlandığı rota gerçekten var olmalı; yoksa kart tıklanınca
     * hata verir.
     */
    public function test_every_guide_points_at_a_route_that_exists(): void
    {
        $bozuk = [];

        foreach ((array) config('help.guides', []) as $guide) {
            if (! Route::has((string) $guide['route'])) {
                $bozuk[] = (string) $guide['route'];
            }
        }

        $this->assertSame([], $bozuk, 'Yardım kılavuzu olmayan rotaya bağlı: ' . implode(', ', $bozuk));
    }

    public function test_every_faq_links_to_a_route_that_exists(): void
    {
        $bozuk = [];

        foreach ((array) config('help.faqs', []) as $faq) {
            if (isset($faq['route']) && ! Route::has((string) $faq['route'])) {
                $bozuk[] = (string) $faq['route'];
            }
        }

        $this->assertSame([], $bozuk);
    }

    public function test_the_screen_opens_and_lists_the_guides(): void
    {
        $this->actingAs($this->admin())->get('/admin/yardim')
            ->assertOk()
            ->assertSee('Yardım Merkezi')
            ->assertSee('Rapor Merkezi')
            ->assertSee('Sık Sorulan Sorular');
    }

    /**
     * Yardım yetki istemiyor: panele girebilen herkes panelin nasıl
     * çalıştığını okuyabilmeli.
     */
    public function test_any_panel_user_can_read_the_help(): void
    {
        $moderator = User::create([
            'first_name' => 'Yardim', 'last_name' => 'Moderator',
            'email' => 'moderator-yardim@example.test', 'password' => 'sifre-123456', 'is_active' => true,
        ]);
        $moderator->markEmailAsVerified();
        $moderator->roles()->attach(Role::where('slug', 'moderator')->firstOrFail()->id);

        $this->actingAs($moderator->fresh())->get('/admin/yardim')->assertOk();
    }

    public function test_the_search_narrows_both_guides_and_questions(): void
    {
        $help = app(HelpService::class);

        $guides = $help->guides('yedek');
        $faqs = $help->faqs('yedek');

        $this->assertNotEmpty($guides);
        $this->assertNotEmpty($faqs);
        $this->assertLessThan(count($help->guides()), count($guides));
    }

    /**
     * Türkçe "İ" küçültüldüğünde birleşik noktalı bir harf çıkıyor; klavyeden
     * yazılan "i" ile eşleşmezse arama sessizce boş dönerdi.
     */
    public function test_the_search_is_case_insensitive_in_turkish(): void
    {
        $help = app(HelpService::class);

        $this->assertNotEmpty($help->guides('İÇERİK'));
        $this->assertNotEmpty($help->guides('içerik'));
    }

    public function test_the_faq_category_filter_works(): void
    {
        $help = app(HelpService::class);

        $system = $help->faqs(null, 'system');

        $this->assertNotEmpty($system);

        foreach ($system as $faq) {
            $this->assertSame('system', $faq['category']);
        }
    }

    public function test_the_environment_block_reports_the_running_versions(): void
    {
        $environment = app(HelpService::class)->environment();

        $values = array_column($environment, 'value');

        $this->assertContains(PHP_VERSION, $values);
        $this->assertContains((string) config('app.env'), $values);
    }
}
