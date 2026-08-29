<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menü öğesi ekleme ve düzenleme.
 *
 * Menüye hiçbir öğe eklenemiyordu: öğenin hangi menüye gireceği adreste
 * yazıyor (/admin/menus/{menu}/items) ama doğrulama onu form gövdesinde
 * arıyordu. Form böyle bir alan göndermediği için her ekleme "menu id alanı
 * zorunludur" ile düşüyordu — üstelik kullanıcının hiç görmediği bir alanın
 * adıyla.
 */
final class MenuItemManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        app(\App\Services\LanguageService::class)->clearCache();
        $this->seedAuthorization();

        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('slug', 'admin')->firstOrFail());
        $this->actingAs($this->admin);
    }

    private function menu(string $location = 'header', string $locale = 'tr'): Menu
    {
        return Menu::where('location', $location)->where('locale', $locale)->firstOrFail();
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        // Ekrandaki formun gerçekten gönderdiği alanlar; menu_id yok.
        return array_merge([
            'label'        => 'Deneme Öğesi',
            'link_type'    => 'route',
            'route_name'   => 'home',
            'target'       => '_self',
            'display_type' => 'link',
            'sort_order'   => 0,
            'is_active'    => 1,
        ], $overrides);
    }

    // ── Ekleme ──

    public function test_an_item_is_added_from_the_screens_own_form(): void
    {
        $menu = $this->menu();

        $this->post(route('admin.menus.items.store', $menu), $this->payload())
            ->assertRedirect(route('admin.menus.items.index', $menu))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('menu_items', [
            'menu_id' => $menu->id,
            'label'   => 'Deneme Öğesi',
            'parent_id' => null,
        ]);
    }

    /** Öğe adresteki menüye girmeli, başka bir menüye değil. */
    public function test_the_item_lands_in_the_menu_from_the_address(): void
    {
        $footer = $this->menu('footer');

        $this->post(route('admin.menus.items.store', $footer), $this->payload(['label' => 'Alt Bilgi Ögesi']))
            ->assertSessionHasNoErrors();

        $item = MenuItem::where('label', 'Alt Bilgi Ögesi')->firstOrFail();

        $this->assertSame($footer->id, $item->menu_id);
    }

    /** Gövdeden gelen menu_id adresi ezmemeli. */
    public function test_a_menu_id_in_the_body_cannot_move_the_item_elsewhere(): void
    {
        $header = $this->menu();
        $footer = $this->menu('footer');

        $this->post(route('admin.menus.items.store', $header), $this->payload([
            'label'   => 'Kacak Oge',
            'menu_id' => $footer->id,
        ]))->assertSessionHasNoErrors();

        $this->assertSame($header->id, MenuItem::where('label', 'Kacak Oge')->firstOrFail()->menu_id);
    }

    // ── Alt menü (dropdown) ──

    public function test_a_dropdown_child_is_attached_to_its_parent(): void
    {
        $menu = $this->menu();
        $parent = $menu->rootItems()->firstOrFail();

        $parent->update(['display_type' => 'dropdown']);

        $this->post(route('admin.menus.items.store', $menu), $this->payload([
            'label'     => 'Alt Oge',
            'parent_id' => $parent->id,
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('menu_items', [
            'label'     => 'Alt Oge',
            'parent_id' => $parent->id,
            'menu_id'   => $menu->id,
        ]);
    }

    /**
     * Üst öğe başka bir menüden olamaz: öyle bir alt öğe hiçbir yerde
     * görünmez, sessizce kaybolur.
     */
    public function test_a_parent_from_another_menu_is_refused(): void
    {
        $header = $this->menu();
        $yabanci = $this->menu('footer')->rootItems()->firstOrFail();

        $this->post(route('admin.menus.items.store', $header), $this->payload([
            'label'     => 'Yanlis Baglanti',
            'parent_id' => $yabanci->id,
        ]))->assertSessionHasErrors('parent_id');

        $this->assertDatabaseMissing('menu_items', ['label' => 'Yanlis Baglanti']);
    }

    /** Boş gelen üst öğe "kök" demek, doğrulama hatası değil. */
    public function test_an_empty_parent_means_a_root_item(): void
    {
        $menu = $this->menu();

        $this->post(route('admin.menus.items.store', $menu), $this->payload([
            'label'     => 'Kok Oge',
            'parent_id' => '',
        ]))->assertSessionHasNoErrors();

        $this->assertNull(MenuItem::where('label', 'Kok Oge')->firstOrFail()->parent_id);
    }

    // ── Düzenleme ──

    public function test_an_item_can_be_edited(): void
    {
        $item = $this->menu()->rootItems()->firstOrFail();

        $this->put(route('admin.menus.items.update', $item), $this->payload([
            'label' => 'Yeni Etiket',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('Yeni Etiket', $item->fresh()->label);
    }

    /** Bir öğe kendi altına taşınırsa ağaç kendine kapanır ve menü hiç basılmaz. */
    public function test_an_item_cannot_become_its_own_parent(): void
    {
        $item = $this->menu()->rootItems()->firstOrFail();

        $this->put(route('admin.menus.items.update', $item), $this->payload([
            'label'     => $item->label,
            'parent_id' => $item->id,
        ]))->assertSessionHasErrors('parent_id');

        $this->assertNull($item->fresh()->parent_id);
    }

    // ── Hata mesajları ──

    /**
     * Kullanıcı hiç görmediği bir alanın adıyla ("menu id") karşılaşmamalı;
     * gördüğü alan için gördüğü adla uyarılmalı.
     */
    public function test_the_warnings_name_the_fields_the_user_can_see(): void
    {
        $menu = $this->menu();

        $this->post(route('admin.menus.items.store', $menu), $this->payload(['label' => '']))
            ->assertSessionHasErrors(['label' => 'Menü etiketi zorunludur.']);

        $this->post(route('admin.menus.items.store', $menu), $this->payload([
            'link_type'  => 'url',
            'route_name' => null,
            'url'        => '',
        ]))->assertSessionHasErrors(['url' => 'Özel bağlantı seçtiğinizde adresi yazmalısınız.']);
    }

    // ── Ön yüze yansıma ──

    public function test_a_new_item_shows_up_in_the_header(): void
    {
        $menu = $this->menu();

        $this->post(route('admin.menus.items.store', $menu), $this->payload(['label' => 'Yeni Baglanti']))
            ->assertSessionHasNoErrors();

        $this->assertStringContainsString('Yeni Baglanti', (string) $this->get('/tr')->assertOk()->getContent());
    }

    public function test_a_new_footer_item_shows_up_in_the_footer(): void
    {
        $footer = $this->menu('footer');
        $sutun = $footer->rootItems()->firstOrFail();

        $this->post(route('admin.menus.items.store', $footer), $this->payload([
            'label'     => 'Alt Bilgi Baglantisi',
            'parent_id' => $sutun->id,
        ]))->assertSessionHasNoErrors();

        $html = (string) $this->get('/tr')->assertOk()->getContent();
        $footerHtml = substr($html, (int) strpos($html, '<footer'));

        $this->assertStringContainsString('Alt Bilgi Baglantisi', $footerHtml);
    }
}
