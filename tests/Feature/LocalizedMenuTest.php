<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PermissionKey;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\LanguageService;
use App\Services\MenuItemService;
use App\Services\MenuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Navigation is content, so it follows the visitor's language too.
 *
 * Each language owns its menu and item tree — a language may legitimately show
 * fewer links or a different order — and a language without one falls back to
 * the default so the site is never left without navigation.
 */
class LocalizedMenuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        app(LanguageService::class)->clearCache();
        app(MenuService::class)->clearAllCaches();

        // A migration seeds a header menu; these tests build their own so the
        // assertions describe the fixture rather than the seed.
        MenuItem::query()->withTrashed()->forceDelete();
        Menu::query()->withTrashed()->forceDelete();
    }

    private function turkishMenu(): Menu
    {
        $menu = Menu::create([
            'name'      => 'Üst Menü',
            'location'  => 'header',
            'is_active' => true,
        ]);

        MenuItem::create([
            'menu_id'    => $menu->id,
            'label'      => 'Anasayfa',
            'link_type'  => 'route',
            'route_name' => 'home',
            'sort_order' => 0,
            'is_active'  => true,
        ]);

        MenuItem::create([
            'menu_id'    => $menu->id,
            'label'      => 'İletişim',
            'link_type'  => 'route',
            'route_name' => 'contact',
            'sort_order' => 1,
            'is_active'  => true,
        ]);

        return $menu->fresh();
    }

    public function test_a_menu_belongs_to_the_default_language_when_none_is_given(): void
    {
        $menu = $this->turkishMenu();

        $this->assertSame('tr', $menu->locale);
        $this->assertNotEmpty($menu->lang_group_id);
        $this->assertSame('tr', MenuItem::where('menu_id', $menu->id)->first()?->locale);
    }

    public function test_each_language_gets_its_own_menu(): void
    {
        $turkish = $this->turkishMenu();

        $english = Menu::create([
            'locale'        => 'en',
            'lang_group_id' => $turkish->lang_group_id,
            'name'          => 'Main Menu',
            'location'      => 'header',
            'is_active'     => true,
        ]);

        MenuItem::create([
            'locale'     => 'en',
            'menu_id'    => $english->id,
            'label'      => 'Home',
            'link_type'  => 'route',
            'route_name' => 'home',
            'sort_order' => 0,
            'is_active'  => true,
        ]);

        $menus = app(MenuService::class);

        app()->setLocale('tr');
        $this->assertSame('Üst Menü', $menus->getByLocation('header')?->name);

        app()->setLocale('en');
        $this->assertSame('Main Menu', $menus->getByLocation('header')?->name, 'Türkçe menü cache i İngilizceye sızdı');
    }

    public function test_a_language_without_a_menu_falls_back_to_the_default(): void
    {
        $this->turkishMenu();

        app()->setLocale('en');

        $menu = app(MenuService::class)->getByLocation('header');

        $this->assertNotNull($menu, 'Menüsü olmayan dilde navigasyon boş kaldı');
        $this->assertSame('tr', $menu->locale);
    }

    public function test_the_navbar_renders_the_language_its_own_menu(): void
    {
        $turkish = $this->turkishMenu();

        $english = Menu::create([
            'locale'        => 'en',
            'lang_group_id' => $turkish->lang_group_id,
            'name'          => 'Main Menu',
            'location'      => 'header',
            'is_active'     => true,
        ]);

        MenuItem::create([
            'locale'     => 'en',
            'menu_id'    => $english->id,
            'label'      => 'Get in touch',
            'link_type'  => 'route',
            'route_name' => 'contact',
            'sort_order' => 0,
            'is_active'  => true,
        ]);

        $html = $this->get('/en')->getContent();

        $this->assertStringContainsString('Get in touch', $html);
        $this->assertStringNotContainsString('>İletişim<', $html);
    }

    // ── Copying a menu into another language ──

    public function test_copying_a_menu_clones_the_whole_tree(): void
    {
        $turkish = $this->turkishMenu();

        $parent = MenuItem::where('menu_id', $turkish->id)->where('label', 'Anasayfa')->firstOrFail();
        MenuItem::create([
            'menu_id'    => $turkish->id,
            'parent_id'  => $parent->id,
            'label'      => 'Alt Sayfa',
            'link_type'  => 'url',
            'url'        => '/alt',
            'sort_order' => 0,
            'is_active'  => true,
        ]);

        $copy = app(MenuService::class)->copyToLocale($turkish, 'en');

        $this->assertSame('en', $copy->locale);
        $this->assertSame($turkish->lang_group_id, $copy->lang_group_id, 'Kopya kaynak menüyle aynı gruba bağlanmadı');
        $this->assertSame(3, MenuItem::where('menu_id', $copy->id)->count());

        $copiedChild = MenuItem::where('menu_id', $copy->id)->where('label', 'Alt Sayfa')->firstOrFail();
        $copiedParent = MenuItem::where('menu_id', $copy->id)->where('label', 'Anasayfa')->firstOrFail();

        $this->assertSame($copiedParent->id, $copiedChild->parent_id, 'Alt öğe kopyada yanlış ebeveyne bağlandı');
        $this->assertSame('en', $copiedChild->locale);
    }

    public function test_each_copied_item_stays_linked_to_its_source(): void
    {
        $turkish = $this->turkishMenu();
        $copy = app(MenuService::class)->copyToLocale($turkish, 'en');

        $source = MenuItem::where('menu_id', $turkish->id)->where('label', 'Anasayfa')->firstOrFail();
        $copied = MenuItem::where('menu_id', $copy->id)->where('label', 'Anasayfa')->firstOrFail();

        $this->assertSame($source->lang_group_id, $copied->lang_group_id);
    }

    public function test_copying_twice_does_not_duplicate_the_menu(): void
    {
        $turkish = $this->turkishMenu();
        $menus = app(MenuService::class);

        $first = $menus->copyToLocale($turkish, 'en');
        $second = $menus->copyToLocale($turkish, 'en');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Menu::where('locale', 'en')->count());
    }

    /**
     * A copied page link must open the target language's page, otherwise the
     * English menu quietly sends every visitor to the Turkish page.
     */
    public function test_copying_a_menu_translates_its_page_links(): void
    {
        $turkishPage = Page::create([
            'title'   => 'Hakkımızda',
            'slug'    => 'hakkimizda',
            'content' => '<p>Metin</p>',
            'status'  => 'published',
        ]);

        Page::create([
            'locale'        => 'en',
            'lang_group_id' => $turkishPage->lang_group_id,
            'title'         => 'About Us',
            'slug'          => 'about-us',
            'content'       => '<p>Text</p>',
            'status'        => 'published',
        ]);

        $menu = $this->turkishMenu();

        MenuItem::create([
            'menu_id'      => $menu->id,
            'label'        => 'Hakkımızda',
            'link_type'    => 'route',
            'route_name'   => 'pages.show',
            'route_params' => ['slug' => 'hakkimizda'],
            'sort_order'   => 2,
            'is_active'    => true,
        ]);

        $copy = app(MenuService::class)->copyToLocale($menu, 'en');

        $copied = MenuItem::where('menu_id', $copy->id)
            ->where('route_name', 'pages.show')
            ->firstOrFail();

        $this->assertSame(['slug' => 'about-us'], $copied->route_params);
    }

    public function test_copying_keeps_the_slug_when_the_page_is_not_translated(): void
    {
        Page::create([
            'title'   => 'Yalnızca Türkçe',
            'slug'    => 'yalnizca-turkce',
            'content' => '<p>Metin</p>',
            'status'  => 'published',
        ]);

        $menu = $this->turkishMenu();

        MenuItem::create([
            'menu_id'      => $menu->id,
            'label'        => 'Yalnızca Türkçe',
            'link_type'    => 'route',
            'route_name'   => 'pages.show',
            'route_params' => ['slug' => 'yalnizca-turkce'],
            'sort_order'   => 2,
            'is_active'    => true,
        ]);

        $copy = app(MenuService::class)->copyToLocale($menu, 'en');

        $copied = MenuItem::where('menu_id', $copy->id)
            ->where('route_name', 'pages.show')
            ->firstOrFail();

        $this->assertSame(['slug' => 'yalnizca-turkce'], $copied->route_params);
    }

    public function test_the_panel_can_copy_a_menu_into_a_language(): void
    {
        $turkish = $this->turkishMenu();

        $this->actingAs($this->adminWithMenuPermission())
            ->post(route('admin.menus.copy', [$turkish, 'en']))
            ->assertRedirect();

        $this->assertDatabaseHas('menus', ['locale' => 'en', 'location' => 'header']);
    }

    public function test_the_panel_rejects_an_unknown_language(): void
    {
        $turkish = $this->turkishMenu();

        $this->actingAs($this->adminWithMenuPermission())
            ->post(route('admin.menus.copy', [$turkish, 'xx']))
            ->assertRedirect(route('admin.menus.index'))
            ->assertSessionHas('error');

        $this->assertSame(0, Menu::where('locale', '!=', 'tr')->count());
    }

    public function test_a_user_without_the_permission_cannot_copy_a_menu(): void
    {
        $turkish = $this->turkishMenu();

        $this->actingAs(User::factory()->create())
            ->post(route('admin.menus.copy', [$turkish, 'en']))
            ->assertForbidden();

        $this->assertSame(0, Menu::where('locale', '!=', 'tr')->count());
    }

    /**
     * A fallback menu carries the default language's page slugs, which would
     * send an English visitor to the Turkish page.
     */
    public function test_a_fallback_menu_links_to_the_page_in_the_current_language(): void
    {
        $turkishPage = Page::create([
            'title'   => 'Hakkımızda',
            'slug'    => 'hakkimizda',
            'content' => '<p>Metin</p>',
            'status'  => 'published',
        ]);

        Page::create([
            'locale'        => 'en',
            'lang_group_id' => $turkishPage->lang_group_id,
            'title'         => 'About Us',
            'slug'          => 'about-us',
            'content'       => '<p>Text</p>',
            'status'        => 'published',
        ]);

        $menu = $this->turkishMenu();

        $item = MenuItem::create([
            'menu_id'      => $menu->id,
            'label'        => 'Hakkımızda',
            'link_type'    => 'route',
            'route_name'   => 'pages.show',
            'route_params' => ['slug' => 'hakkimizda'],
            'sort_order'   => 2,
            'is_active'    => true,
        ]);

        $items = app(MenuItemService::class);

        app()->setLocale('tr');
        $this->assertStringEndsWith('/hakkimizda', $items->resolveUrl($item));

        app()->setLocale('en');
        $this->assertStringEndsWith('/about-us', $items->resolveUrl($item->fresh()), 'Yedek menü İngilizce ziyaretçiyi Türkçe sayfaya gönderiyor');
    }

    public function test_an_untranslated_page_keeps_its_original_slug(): void
    {
        Page::create([
            'title'   => 'Yalnızca Türkçe',
            'slug'    => 'yalnizca-turkce',
            'content' => '<p>Metin</p>',
            'status'  => 'published',
        ]);

        $menu = $this->turkishMenu();

        $item = MenuItem::create([
            'menu_id'      => $menu->id,
            'label'        => 'Yalnızca Türkçe',
            'link_type'    => 'route',
            'route_name'   => 'pages.show',
            'route_params' => ['slug' => 'yalnizca-turkce'],
            'sort_order'   => 2,
            'is_active'    => true,
        ]);

        app()->setLocale('en');

        $this->assertStringEndsWith('/yalnizca-turkce', app(MenuItemService::class)->resolveUrl($item));
    }

    private function adminWithMenuPermission(): User
    {
        $role = Role::create(['name' => 'Menü Yöneticisi', 'slug' => 'menu-manager']);
        $permission = Permission::firstOrCreate(
            ['key' => PermissionKey::MenusManage->value],
            [
                'name'  => PermissionKey::MenusManage->label(),
                'group' => PermissionKey::MenusManage->group(),
            ],
        );
        $role->permissions()->syncWithoutDetaching([$permission->id]);

        $user = User::factory()->create();
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }
}
