<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\GalleryType;
use App\Models\GalleryCategory;
use App\Models\GalleryItem;
use App\Models\Role;
use App\Models\User;
use App\Services\UploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Galeriye toplu fotoğraf yükleme.
 *
 * Tekli form bir etkinliğin yüz fotoğrafı için kullanılamıyordu. Burada ortak
 * alanlar bir kez seçiliyor, her dosya kendi isteğiyle kaydediliyor ve başlık
 * dosya adından türüyor.
 *
 * Kayıt yüklemeyle birlikte doğuyor, "Hepsini Kaydet"i beklemiyor: bekletilseydi
 * tarayıcı kapandığında yüz yükleme çöpe giderdi.
 */
class GalleryBulkUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        app(\App\Services\LanguageService::class)->clearCache();
        $this->seedAuthorization();

        $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Yönetici']);

        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($role);

        $this->actingAs($this->admin);
    }

    private function category(string $locale = 'tr'): GalleryCategory
    {
        return GalleryCategory::factory()->create([
            'locale' => $locale,
            'name'   => 'Etkinlikler ' . $locale,
            'slug'   => 'etkinlikler-' . $locale,
        ]);
    }

    private function image(string $name): UploadedFile
    {
        return UploadedFile::fake()->image($name, 800, 600);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'image'      => $this->image('bahar-senligi-01.jpg'),
            'locale'     => 'tr',
            'is_active'  => 1,
            'sort_order' => 0,
        ], $overrides);
    }

    // ── Yükleme ──

    public function test_a_dropped_photo_becomes_a_gallery_item_at_once(): void
    {
        $category = $this->category();

        $response = $this->postJson(route('admin.gallery-items.bulk.store'), $this->payload([
            'gallery_category_id' => $category->id,
            'sort_order'          => 7,
        ]));

        $response->assertOk();

        $item = GalleryItem::sole();

        // Kayıt "kaydet"i beklemiyor; yükleme anında doğuyor.
        $this->assertSame('tr', $item->locale);
        $this->assertSame(GalleryType::Photo, $item->type);
        $this->assertSame($category->id, $item->gallery_category_id);
        $this->assertTrue($item->is_active);
        $this->assertSame(7, $item->sort_order);
        $response->assertJsonPath('id', $item->id);
    }

    public function test_the_title_is_derived_from_the_file_name(): void
    {
        // Yüz dosya için yüz başlık yazmak mümkün değil; ad dosyadan türüyor ve
        // ızgarada düzeltilebiliyor.
        $this->postJson(route('admin.gallery-items.bulk.store'), $this->payload([
            'image' => $this->image('bahar-senligi-01.jpg'),
        ]))->assertOk();

        $this->assertSame('Bahar Senligi 01', GalleryItem::sole()->title);
    }

    public function test_a_file_name_that_leaves_nothing_still_gets_a_title(): void
    {
        // Başlık zorunlu bir sütun; adı boşalan dosya kaydı düşürmemeli.
        $this->postJson(route('admin.gallery-items.bulk.store'), $this->payload([
            'image' => $this->image('-_-.png'),
        ]))->assertOk();

        $this->assertSame('Görsel', GalleryItem::sole()->title);
    }

    public function test_the_upload_is_converted_and_stored_under_uploads(): void
    {
        $this->postJson(route('admin.gallery-items.bulk.store'), $this->payload())->assertOk();

        $item = GalleryItem::sole();

        // Tekli formla aynı yol: WebP'ye çevriliyor ve public/uploads altına iniyor.
        $this->assertStringStartsWith('gallery/', (string) $item->image);
        $this->assertStringEndsWith('.webp', (string) $item->image);
        $this->assertFileExists(UploadService::basePath((string) $item->image));
    }

    public function test_only_images_are_accepted(): void
    {
        // Video galeride yüklenen bir dosya değil, YouTube/Vimeo adresi.
        $this->postJson(route('admin.gallery-items.bulk.store'), $this->payload([
            'image' => UploadedFile::fake()->createWithContent('tanitim.mp4', 'x'),
        ]))->assertStatus(422);

        $this->assertSame(0, GalleryItem::count());
    }

    public function test_a_category_from_another_language_is_refused(): void
    {
        // Kategoriler de çevrilmiş: Türkçe öğe İngilizce kategoriye bağlanmamalı.
        $english = $this->category('en');

        $this->postJson(route('admin.gallery-items.bulk.store'), $this->payload([
            'locale'              => 'tr',
            'gallery_category_id' => $english->id,
        ]))->assertStatus(422)->assertJsonValidationErrors('gallery_category_id');

        $this->assertSame(0, GalleryItem::count());
    }

    public function test_each_file_keeps_the_sort_order_it_was_given(): void
    {
        // Sıra istemciden geliyor: yüklemeler paralel gittiği için sunucuda
        // "en büyük + 1" iki dosyaya aynı numarayı verirdi.
        foreach ([10, 11, 12] as $index => $sort) {
            $this->postJson(route('admin.gallery-items.bulk.store'), $this->payload([
                'image'      => $this->image("kare-{$index}.jpg"),
                'sort_order' => $sort,
            ]))->assertOk();
        }

        $this->assertSame([10, 11, 12], GalleryItem::orderBy('id')->pluck('sort_order')->all());
    }

    // ── Başlıkları kaydetme ──

    public function test_titles_edited_in_the_grid_are_saved_together(): void
    {
        foreach (['bir.jpg', 'iki.jpg'] as $name) {
            $this->postJson(route('admin.gallery-items.bulk.store'), $this->payload(['image' => $this->image($name)]))->assertOk();
        }

        [$first, $second] = GalleryItem::orderBy('id')->get()->all();

        $this->putJson(route('admin.gallery-items.bulk.update'), [
            'titles' => [
                $first->id  => 'Bahar Şenliği — Açılış',
                $second->id => 'Bahar Şenliği — Kortej',
            ],
        ])->assertOk()->assertJsonPath('updated', 2);

        $this->assertSame('Bahar Şenliği — Açılış', $first->refresh()->title);
        $this->assertSame('Bahar Şenliği — Kortej', $second->refresh()->title);
    }

    public function test_an_empty_title_is_refused(): void
    {
        $this->postJson(route('admin.gallery-items.bulk.store'), $this->payload())->assertOk();

        $item = GalleryItem::sole();

        $this->putJson(route('admin.gallery-items.bulk.update'), [
            'titles' => [$item->id => ''],
        ])->assertStatus(422);

        $this->assertSame('Bahar Senligi 01', $item->refresh()->title);
    }

    // ── Kaldırma ──

    public function test_an_item_removed_from_the_grid_is_soft_deleted(): void
    {
        $this->postJson(route('admin.gallery-items.bulk.store'), $this->payload())->assertOk();

        $item = GalleryItem::sole();

        $this->deleteJson(route('admin.gallery-items.bulk.destroy', $item))->assertOk();

        $this->assertSame(0, GalleryItem::count());
        $this->assertSame(1, GalleryItem::onlyTrashed()->count());
    }

    // ── Yetki ──

    public function test_a_user_without_the_permission_cannot_upload(): void
    {
        $editor = User::factory()->create();
        $editor->roles()->attach(Role::firstOrCreate(['slug' => 'user'], ['name' => 'Üye']));

        $this->actingAs($editor)
            ->postJson(route('admin.gallery-items.bulk.store'), $this->payload())
            ->assertForbidden();

        $this->assertSame(0, GalleryItem::count());
    }
}
