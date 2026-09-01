<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Enums\PermissionKey;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\ContentRevision;
use App\Models\Page;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\ContentRevisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * İçerik sürümleme.
 *
 * Denetim izi neyin değiştiğini gösteriyor ama geri döndüremiyor; yanlışlıkla
 * silinen bir paragrafın tek karşılığı onu hatırlayan birinin yeniden
 * yazmasıydı. Sürümleme o boşluğu kapatıyor.
 *
 * Üç karar bu sınıfın sınadığı şeyi belirliyor: kapsam **sayfa ve blog
 * yazısı**, tavan **yirmi sürüm**, ve geçmiş **dile bağlı** — dil grubuna
 * değil.
 */
class ContentRevisionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        $this->seedAuthorization();
    }

    private function service(): ContentRevisionService
    {
        return app(ContentRevisionService::class);
    }

    private function page(array $attributes = []): Page
    {
        return Page::create(array_merge([
            'locale'        => 'tr',
            'lang_group_id' => (string) Str::uuid(),
            'title'         => 'İlk Başlık',
            'slug'          => 'ilk-baslik',
            'content'       => '<p>İlk içerik</p>',
            'status'        => ContentStatus::Published,
        ], $attributes));
    }

    private function blogPost(array $attributes = []): BlogPost
    {
        $category = BlogCategory::create([
            'locale'        => 'tr',
            'lang_group_id' => (string) Str::uuid(),
            'name'          => 'Duyurular',
            'slug'          => 'duyurular-' . uniqid(),
        ]);

        return BlogPost::create(array_merge([
            'locale'           => 'tr',
            'lang_group_id'    => (string) Str::uuid(),
            'blog_category_id' => $category->id,
            'title'            => 'İlk Yazı',
            'slug'             => 'ilk-yazi',
            'body'             => '<p>İlk gövde</p>',
            'status'           => ContentStatus::Published,
        ], $attributes));
    }

    /* ==================== Yakalama ==================== */

    public function test_creating_content_records_the_first_revision(): void
    {
        $page = $this->page();

        $this->assertSame(1, $page->revisions()->count());
        $this->assertSame('İlk Başlık', $page->revisions()->first()->value('title'));
    }

    public function test_each_edit_adds_a_revision(): void
    {
        $page = $this->page();

        $page->update(['title' => 'İkinci Başlık']);
        $page->update(['content' => '<p>Yeni içerik</p>']);

        $this->assertSame(3, $page->fresh()->revisions()->count());
    }

    /**
     * Listenin başındaki sürüm her zaman içeriğin şu anki hâli olmalı; ekran
     * "şu anki hâl" rozetini buna dayandırıyor.
     */
    public function test_the_newest_revision_always_matches_the_current_content(): void
    {
        $page = $this->page();
        $page->update(['title' => 'Güncel Başlık']);

        $this->assertSame(
            $page->fresh()->title,
            $page->fresh()->revisions()->first()->value('title'),
        );
    }

    /**
     * Sayaçlar tetikleyici değil. `views` her ziyarette artıyor ve
     * `increment()` model olayı doğuruyor: sayaç sürüm yazsaydı popüler bir
     * yazının geçmişi bir günde dolar, gerçek düzenlemeler listeden düşerdi.
     */
    public function test_the_view_counter_does_not_create_a_revision(): void
    {
        $post = $this->blogPost();
        $before = $post->revisions()->count();

        $post->increment('views');
        $post->increment('views');

        $this->assertSame($before, $post->fresh()->revisions()->count());
    }

    /**
     * Sürümlenmeyen bir alanı kaydetmek geçmişi kirletmemeli.
     *
     * Bu sınav bir hatayı yakaladı: Eloquent, hiçbir alanı kirletmeyen bir
     * `save()` çağrısında güncelleme sorgusunu atlıyor ve `getChanges()` bir
     * önceki kaydın değerlerini taşımaya devam ediyor — `wasChanged()` o anda
     * "evet" diyor ve sahte bir sürüm doğuyordu.
     */
    public function test_saving_an_untracked_field_after_a_real_edit_adds_nothing(): void
    {
        $page = $this->page();
        $page->update(['title' => 'Değişti']);

        $count = $page->fresh()->revisions()->count();

        $page->update(['sort_order' => 5]);

        $this->assertSame($count, $page->fresh()->revisions()->count());
    }

    /**
     * Karşılaştırma anahtar sırasına takılmamalı.
     *
     * payload bir `json` sütunu ve MySQL 8 nesne anahtarlarını depolarken
     * yeniden sıralıyor (önce uzunluğa, sonra bayt sırasına). Karşılaştırma
     * kodlanmış dizgeye baktığı sürece MySQL'de hiçbir zaman eşleşmiyor:
     * hiçbir şey değişmese bile her kaydetme yeni bir sürüm yazıyor ve yirmi
     * sürümlük tavan gerçek geçmişi dışarı itiyordu. SQLite ve MariaDB
     * anahtarları sıralamadığı için hata yalnız üretimde görünüyordu — bu
     * sınav sürücüden bağımsız: sırayı elle bozuyor.
     */
    public function test_a_reordered_payload_is_still_the_same_content(): void
    {
        $page = $this->page();

        $latest = ContentRevision::query()->forTarget($page)->firstOrFail();

        // MySQL'in yaptığı: aynı içerik, farklı anahtar sırası. Sayı da dizge
        // olarak dönebiliyor, o da karışsın.
        $shuffled = $latest->payload;
        ksort($shuffled);
        $shuffled = array_map(
            static fn ($value) => is_int($value) ? (string) $value : $value,
            $shuffled,
        );

        $latest->update(['payload' => $shuffled]);

        $count = $page->fresh()->revisions()->count();

        $page->update(['title' => $page->title]);

        $this->assertSame($count, $page->fresh()->revisions()->count());
    }

    public function test_saving_the_same_content_twice_leaves_one_revision(): void
    {
        $page = $this->page();
        $count = $page->revisions()->count();

        $page->update(['title' => $page->title]);
        $page->update(['title' => $page->title]);

        $this->assertSame($count, $page->fresh()->revisions()->count());
    }

    /* ==================== Tavan ==================== */

    public function test_only_the_newest_twenty_revisions_are_kept(): void
    {
        $page = $this->page();

        for ($i = 1; $i <= 25; $i++) {
            $page->update(['title' => "Başlık {$i}"]);
        }

        $revisions = $page->fresh()->revisions()->get();

        $this->assertCount(20, $revisions);
        $this->assertSame('Başlık 25', $revisions->first()->value('title'));
        $this->assertSame('Başlık 6', $revisions->last()->value('title'));
    }

    /**
     * Budama kalıcı: tavanın var olma sebebi disk, yumuşak silinen satır
     * diskte durmaya devam ederdi.
     */
    public function test_pruned_revisions_leave_no_soft_deleted_rows_behind(): void
    {
        $page = $this->page();

        for ($i = 1; $i <= 25; $i++) {
            $page->update(['title' => "Başlık {$i}"]);
        }

        $this->assertSame(20, ContentRevision::withTrashed()->count());
    }

    /* ==================== Dil ayrımı ==================== */

    /**
     * Geçmiş dile bağlı: iki dili iki ayrı kişi düzenlediğinde biri ötekinin
     * işini silmemeli.
     */
    public function test_each_language_keeps_its_own_history(): void
    {
        $group = (string) Str::uuid();

        $tr = $this->page(['locale' => 'tr', 'lang_group_id' => $group, 'slug' => 'tr-sayfa']);
        $en = $this->page(['locale' => 'en', 'lang_group_id' => $group, 'slug' => 'en-page', 'title' => 'First Title']);

        $tr->update(['title' => 'TR güncellendi']);
        $tr->update(['title' => 'TR bir daha']);

        $this->assertSame(3, $tr->fresh()->revisions()->count());
        $this->assertSame(1, $en->fresh()->revisions()->count());
        $this->assertSame('First Title', $en->fresh()->revisions()->first()->value('title'));
    }

    public function test_restoring_one_language_does_not_touch_the_other(): void
    {
        $group = (string) Str::uuid();

        $tr = $this->page(['locale' => 'tr', 'lang_group_id' => $group, 'slug' => 'tr-sayfa']);
        $en = $this->page(['locale' => 'en', 'lang_group_id' => $group, 'slug' => 'en-page', 'title' => 'English Title']);

        $ilk = $tr->revisions()->first();
        $tr->update(['title' => 'TR değişti']);

        $this->service()->restore($ilk, $tr->fresh());

        $this->assertSame('İlk Başlık', $tr->fresh()->title);
        $this->assertSame('English Title', $en->fresh()->title);
    }

    /* ==================== Geri yükleme ==================== */

    public function test_restoring_brings_the_old_content_back(): void
    {
        $page = $this->page();
        $ilk = $page->revisions()->first();

        $page->update(['title' => 'Yeni Başlık', 'content' => '<p>Yeni içerik</p>']);

        $this->service()->restore($ilk, $page->fresh());

        $page->refresh();

        $this->assertSame('İlk Başlık', $page->title);
        $this->assertSame('<p>İlk içerik</p>', $page->content);
    }

    /**
     * Geri yükleme yeni satır açmıyor: adres, kimlik ve bağlantılar korunuyor.
     */
    public function test_restoring_updates_the_same_record(): void
    {
        $page = $this->page();
        $ilk = $page->revisions()->first();
        $page->update(['title' => 'Değişti']);

        $this->service()->restore($ilk, $page->fresh());

        $this->assertSame(1, Page::count());
        $this->assertTrue(Page::whereKey($page->getKey())->exists());
    }

    /**
     * Geri yükleme de bir kayıt ve kendi sürümünü doğuruyor: "yanlış sürüme
     * döndüm" diyen kişi bir öncekine dönebilmeli.
     */
    public function test_restoring_is_itself_recorded_so_it_can_be_undone(): void
    {
        $page = $this->page();
        $ilk = $page->revisions()->first();

        $page->update(['title' => 'Ara Başlık']);
        $before = $page->fresh()->revisions()->count();

        $this->service()->restore($ilk, $page->fresh());

        $this->assertSame($before + 1, $page->fresh()->revisions()->count());
        $this->assertSame('İlk Başlık', $page->fresh()->revisions()->first()->value('title'));
    }

    public function test_a_revision_of_another_record_is_refused(): void
    {
        $a = $this->page(['slug' => 'a-sayfa']);
        $b = $this->page(['slug' => 'b-sayfa', 'title' => 'B Başlık']);

        $this->expectException(\RuntimeException::class);

        $this->service()->restore($a->revisions()->first(), $b);
    }

    /* ==================== Kapsam ==================== */

    /**
     * Kapsam bilinçli olarak dar. Galeri, SSS, slider ve popup dışarıda; bu
     * sınav kapsamın sessizce genişlemediğini de, daralmadığını da tutuyor.
     */
    public function test_only_pages_and_blog_posts_are_versioned(): void
    {
        $this->assertSame(
            [Page::class, BlogPost::class],
            array_keys((array) config('revisions.models')),
        );
    }

    public function test_untracked_models_write_no_revisions(): void
    {
        BlogCategory::create([
            'locale'        => 'tr',
            'lang_group_id' => (string) Str::uuid(),
            'name'          => 'Kategori',
            'slug'          => 'kategori',
        ]);

        $this->assertSame(0, ContentRevision::count());
    }

    /**
     * İçerik kalıcı olarak silindiğinde geçmişi de gidiyor; yumuşak silmede
     * duruyor, çünkü geri alınan içerik geçmişiyle birlikte dönmeli.
     */
    public function test_soft_deleting_keeps_the_history_but_force_deleting_clears_it(): void
    {
        $page = $this->page();
        $page->update(['title' => 'Değişti']);

        $page->delete();
        $this->assertSame(2, ContentRevision::count());

        $page->forceDelete();
        $this->assertSame(0, ContentRevision::withTrashed()->count());
    }

    /* ==================== Ekran ==================== */

    /**
     * @param array<int, PermissionKey> $permissions
     */
    private function userWith(array $permissions): User
    {
        $role = Role::create(['name' => 'Test', 'slug' => 'test-' . uniqid()]);

        $ids = [];
        foreach ($permissions as $key) {
            $ids[] = Permission::firstOrCreate(
                ['key' => $key->value],
                ['name' => $key->label(), 'group' => $key->group()],
            )->id;
        }

        $role->permissions()->syncWithoutDetaching($ids);

        $user = User::factory()->create();
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    public function test_the_screen_lists_the_revisions(): void
    {
        $page = $this->page();
        $page->update(['title' => 'İkinci Başlık']);

        $this->actingAs($this->userWith([PermissionKey::PagesView, PermissionKey::PagesManage]))
            ->get(route('admin.revisions.index', ['type' => 'sayfa', 'id' => $page->id]))
            ->assertOk()
            ->assertSee('İkinci Başlık')
            ->assertSee('İlk Başlık')
            ->assertSee('Şu anki hâl');
    }

    public function test_the_screen_restores_the_chosen_revision(): void
    {
        $page = $this->page();
        $ilk = $page->revisions()->first();
        $page->update(['title' => 'Değişti']);

        $this->actingAs($this->userWith([PermissionKey::PagesView, PermissionKey::PagesManage]))
            ->post(route('admin.revisions.restore', [
                'type' => 'sayfa', 'id' => $page->id, 'revision' => $ilk->id,
            ]))
            ->assertRedirect();

        $this->assertSame('İlk Başlık', $page->fresh()->title);
    }

    public function test_editing_permission_is_required(): void
    {
        $page = $this->page();

        $this->actingAs($this->userWith([PermissionKey::PagesView]))
            ->get(route('admin.revisions.index', ['type' => 'sayfa', 'id' => $page->id]))
            ->assertForbidden();

        $this->actingAs($this->userWith([PermissionKey::PagesView]))
            ->post(route('admin.revisions.restore', [
                'type' => 'sayfa', 'id' => $page->id, 'revision' => $page->revisions()->first()->id,
            ]))
            ->assertForbidden();
    }

    /**
     * Adres satırındaki tür serbest bırakılmıyor: sabit haritada olmayan bir
     * değer sınıf adına dönmemeli.
     */
    public function test_an_unknown_content_type_is_not_resolved(): void
    {
        $this->actingAs($this->userWith([PermissionKey::PagesView, PermissionKey::PagesManage]))
            ->get('/admin/surumler/kullanici/1')
            ->assertNotFound();
    }

    public function test_the_blog_screen_works_too(): void
    {
        $post = $this->blogPost();
        $post->update(['title' => 'Yeni Yazı Başlığı']);

        $this->actingAs($this->userWith([PermissionKey::BlogPostsView, PermissionKey::BlogPostsManage]))
            ->get(route('admin.revisions.index', ['type' => 'blog', 'id' => $post->id]))
            ->assertOk()
            ->assertSee('Yeni Yazı Başlığı');
    }

    /**
     * Düzenleme ekranından ulaşılabilir olmalı; ulaşılamayan bir özellik
     * yazılmamış sayılır.
     */
    public function test_the_edit_screens_link_to_the_history(): void
    {
        $page = $this->page();
        $post = $this->blogPost();

        $admin = $this->userWith([
            PermissionKey::PagesView, PermissionKey::PagesManage,
            PermissionKey::BlogPostsView, PermissionKey::BlogPostsManage,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.pages.edit', $page))
            ->assertOk()
            ->assertSee(route('admin.revisions.index', ['type' => 'sayfa', 'id' => $page->id]));

        $this->actingAs($admin)
            ->get(route('admin.blog-posts.edit', $post))
            ->assertOk()
            ->assertSee(route('admin.revisions.index', ['type' => 'blog', 'id' => $post->id]));
    }

    public function test_the_author_of_a_revision_is_recorded(): void
    {
        $user = $this->userWith([PermissionKey::PagesView, PermissionKey::PagesManage]);

        $this->actingAs($user);

        $page = $this->page();

        $this->assertSame($user->getKey(), $page->revisions()->first()->user_id);
    }
}
