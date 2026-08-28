<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\GalleryType;
use App\Models\GalleryItem;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Galeri listesinde toplu silme ve geri yükleme.
 *
 * Galeriye toplu yükleme vardı ama toplu silme yoktu: yüz fotoğraflık bir
 * yüklemeyi tek tek silmek gerekiyordu. İşlem çeviri grubu üzerinden yürüyor —
 * listede her grup tek satır, dolayısıyla bir satırı silmek o öğenin bütün
 * dillerini siler; Türkçesi gidip İngilizcesi kalsaydı ön yüzde sahipsiz bir
 * çeviri kalırdı.
 */
final class GalleryBulkActionTest extends TestCase
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

    /**
     * Türkçe + İngilizce satırı olan bir galeri öğesi.
     *
     * @return array{tr: GalleryItem, en: GalleryItem}
     */
    private function item(string $title): array
    {
        $groupId = (string) Str::uuid();

        return [
            'tr' => GalleryItem::create([
                'locale' => 'tr', 'lang_group_id' => $groupId, 'title' => $title,
                'type' => GalleryType::Photo, 'image' => 'gallery/x.webp', 'is_active' => true,
            ]),
            'en' => GalleryItem::create([
                'locale' => 'en', 'lang_group_id' => $groupId, 'title' => $title . ' EN',
                'type' => GalleryType::Photo, 'image' => 'gallery/x.webp', 'is_active' => true,
            ]),
        ];
    }

    public function test_selected_items_are_deleted_with_all_their_translations(): void
    {
        $birinci = $this->item('Birinci');
        $ikinci  = $this->item('İkinci');
        $duran   = $this->item('Duran');

        $this->delete(route('admin.gallery-items.bulk-destroy'), [
            'ids' => [$birinci['tr']->id, $ikinci['tr']->id],
        ])->assertRedirect()->assertSessionHas('success', '2 galeri öğesi silindi.');

        // Seçilen satırların İngilizce kardeşleri de gitti.
        foreach ([$birinci, $ikinci] as $silinen) {
            $this->assertSoftDeleted('gallery_items', ['id' => $silinen['tr']->id]);
            $this->assertSoftDeleted('gallery_items', ['id' => $silinen['en']->id]);
        }

        // Seçilmeyen öğeye dokunulmadı.
        $this->assertNotSoftDeleted('gallery_items', ['id' => $duran['tr']->id]);
        $this->assertNotSoftDeleted('gallery_items', ['id' => $duran['en']->id]);
    }

    public function test_two_rows_of_the_same_item_are_counted_once(): void
    {
        $item = $this->item('Tek öğe');

        // Aynı grubun iki dili birden seçilirse grup bir kez siliniyor; sayı
        // seçilen satır sayısını değil gerçekten silinen öğeyi söylemeli.
        $this->delete(route('admin.gallery-items.bulk-destroy'), [
            'ids' => [$item['tr']->id, $item['en']->id],
        ])->assertSessionHas('success', '1 galeri öğesi silindi.');
    }

    public function test_deleted_items_can_be_brought_back_in_bulk(): void
    {
        $item = $this->item('Geri gelecek');

        $this->delete(route('admin.gallery-items.bulk-destroy'), ['ids' => [$item['tr']->id]]);
        $this->assertSoftDeleted('gallery_items', ['id' => $item['en']->id]);

        $this->patch(route('admin.gallery-items.bulk-restore'), ['ids' => [$item['tr']->id]])
            ->assertRedirect()
            ->assertSessionHas('success', '1 galeri öğesi geri yüklendi.');

        $this->assertNotSoftDeleted('gallery_items', ['id' => $item['tr']->id]);
        $this->assertNotSoftDeleted('gallery_items', ['id' => $item['en']->id]);
    }

    /**
     * Toplu işlemden sonra listenin başına düşmek, uzun listede kullanıcının
     * yerini kaybettiriyor; süzgeç ve sayfa korunuyor.
     */
    public function test_the_filters_survive_the_bulk_action(): void
    {
        $item = $this->item('Süzgeçli');

        $this->delete(route('admin.gallery-items.bulk-destroy', ['status' => 'active', 'page' => 2]), [
            'ids' => [$item['tr']->id],
        ])->assertRedirect(route('admin.gallery-items.index', ['status' => 'active', 'page' => 2]));
    }

    public function test_an_empty_selection_is_refused(): void
    {
        $this->from(route('admin.gallery-items.index'))
            ->delete(route('admin.gallery-items.bulk-destroy'), ['ids' => []])
            ->assertSessionHasErrors('ids');
    }

    public function test_an_unknown_id_is_refused(): void
    {
        $this->from(route('admin.gallery-items.index'))
            ->delete(route('admin.gallery-items.bulk-destroy'), ['ids' => [999999]])
            ->assertSessionHasErrors('ids.0');
    }

    public function test_a_role_without_the_delete_permission_cannot_bulk_delete(): void
    {
        $item = $this->item('Korunan');

        $editor = User::factory()->create();
        $editor->roles()->attach(Role::where('slug', 'editor')->firstOrFail());

        $this->actingAs($editor)
            ->delete(route('admin.gallery-items.bulk-destroy'), ['ids' => [$item['tr']->id]])
            ->assertForbidden();

        $this->assertNotSoftDeleted('gallery_items', ['id' => $item['tr']->id]);
    }

    public function test_the_list_offers_the_selection_column_and_the_bulk_bar(): void
    {
        $this->item('Listede');

        $html = (string) $this->get(route('admin.gallery-items.index'))->assertOk()->getContent();

        $this->assertStringContainsString('id="selectAll"', $html);
        $this->assertStringContainsString('gallery-checkbox', $html);
        $this->assertStringContainsString('id="bulkActions"', $html);
        $this->assertStringContainsString('id="bulkDeleteForm"', $html);
    }

    /** Silinmişler sekmesinde "sil" anlamsız; orada toplu işlem geri yükleme. */
    public function test_the_trash_tab_offers_restore_instead_of_delete(): void
    {
        $item = $this->item('Çöpte');
        $this->delete(route('admin.gallery-items.bulk-destroy'), ['ids' => [$item['tr']->id]]);

        $html = (string) $this->get(route('admin.gallery-items.index', ['status' => 'trashed']))
            ->assertOk()->getContent();

        $this->assertStringContainsString('bulkGalleryAction(\'restore\')', $html);
        $this->assertStringNotContainsString('bulkGalleryAction(\'delete\')', $html);
    }

    public function test_the_grid_view_lists_the_same_items(): void
    {
        $this->item('Izgarada');

        $html = (string) $this->get(route('admin.gallery-items.index', ['view' => 'grid']))
            ->assertOk()->getContent();

        $this->assertStringContainsString('id="galleryGrid"', $html);
        $this->assertStringContainsString('gl-card', $html);
        // Seçim ızgarada da çalışıyor: kutular aynı sınıfı taşıyor.
        $this->assertStringContainsString('gallery-checkbox', $html);
    }
}
