<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\BlogCategory;
use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Role;
use App\Models\Slider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Panel listelerindeki toplu işlemler.
 *
 * Toplu seçim arayüzü yedi listede vardı ama arka ucu yoktu: içerik
 * listesinde hiçbir istek gitmiyor, ötekilerde ise kayıt başına ayrı bir
 * istek atılıyordu — elli kayıt elli istek, yarısı düşerse ortada karışık bir
 * sonuç kalıyordu. Hepsi tek isteğe, tek işleme ve tek doğrulama yoluna
 * bağlandı; bu sınıf onu yerinde tutuyor.
 *
 * Görünüm tarafı da burada: işaretleri (data-bulk-*) olmayan bir liste
 * sessizce çalışmaz hâle gelir, çünkü sürücü onları arıyor.
 */
final class AdminBulkActionsTest extends TestCase
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

    // ── Görünüm tarafı: işaretler eksiksiz mi ──

    /**
     * @return array<string, array{string}>
     */
    public static function listRoutes(): array
    {
        $routes = [
            'admin.pages.index',
            'admin.sliders.index',
            'admin.faqs.index',
            'admin.blog-categories.index',
            'admin.blog-posts.index',
            'admin.blog-comments.index',
            'admin.gallery-items.index',
            'admin.users.index',
        ];

        return array_combine($routes, array_map(static fn (string $r): array => [$r], $routes));
    }

    /**
     * Sürücü (assets/admin/js/bulk-actions.js) bu işaretleri arıyor: biri
     * eksikse çubuk hiç belirmiyor ya da seçim toplanamıyor, üstelik sayfa
     * hatasız görünüyor.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('listRoutes')]
    public function test_every_list_carries_the_markers_the_driver_needs(string $routeName): void
    {
        // Satır işareti ancak listede kayıt varken basılıyor; boş listede
        // denetim kendini kandırırdı.
        $this->seedOneRowFor($routeName);

        $html = (string) $this->get(route($routeName))->assertOk()->getContent();

        foreach (['data-bulk-bar', 'data-bulk-count', 'data-bulk-item', 'data-bulk-all', 'data-bulk-clear'] as $isaret) {
            $this->assertStringContainsString($isaret, $html, "{$routeName} → {$isaret} eksik");
        }

        // Her işlem düğmesinin gönderdiği form sayfada gerçekten olmalı.
        preg_match_all('/data-bulk-action="([^"]+)"/', $html, $eslesmeler);

        $this->assertNotEmpty($eslesmeler[1], "{$routeName} → toplu işlem düğmesi yok");

        foreach (array_unique($eslesmeler[1]) as $formId) {
            $this->assertStringContainsString('id="' . $formId . '"', $html, "{$routeName} → {$formId} formu yok");
        }
    }

    /** Denetlenen listede en az bir satır olsun. */
    private function seedOneRowFor(string $routeName): void
    {
        match ($routeName) {
            'admin.pages.index'           => Page::factory()->create(),
            'admin.sliders.index'         => Slider::factory()->create(),
            'admin.faqs.index'            => Faq::factory()->create(),
            'admin.blog-categories.index' => BlogCategory::factory()->create(),
            'admin.blog-posts.index'      => BlogPost::factory()->create(),
            'admin.blog-comments.index'   => BlogComment::factory()->create(),
            'admin.gallery-items.index'   => \App\Models\GalleryItem::factory()->create(),
            // Kullanıcı listesinde oturumu açık olan zaten var.
            default                       => null,
        };
    }

    /** Motor panelin her sayfasında yüklü; işareti olmayan sayfada sessiz kalıyor. */
    public function test_the_driver_is_loaded_by_the_panel_layout(): void
    {
        $html = (string) $this->get(route('admin.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('assets/admin/js/bulk-actions.js', $html);
    }

    // ── Sunucu tarafı: her modül gerçekten siliyor mu ──

    public function test_pages_are_deleted_and_restored_in_bulk(): void
    {
        $ids = [
            Page::create(['locale' => 'tr', 'lang_group_id' => (string) Str::uuid(), 'title' => 'Bir', 'slug' => 'bir', 'content' => 'x'])->id,
            Page::create(['locale' => 'tr', 'lang_group_id' => (string) Str::uuid(), 'title' => 'İki', 'slug' => 'iki', 'content' => 'x'])->id,
        ];

        $this->delete(route('admin.pages.bulk-destroy'), ['ids' => $ids])
            ->assertRedirect()->assertSessionHas('success', '2 kayıt silindi.');

        $this->assertSoftDeleted('pages', ['id' => $ids[0]]);

        $this->patch(route('admin.pages.bulk-restore'), ['ids' => $ids])
            ->assertSessionHas('success', '2 kayıt geri yüklendi.');

        $this->assertNotSoftDeleted('pages', ['id' => $ids[0]]);
    }

    public function test_sliders_are_deleted_in_bulk(): void
    {
        $ids = [
            Slider::create(['locale' => 'tr', 'lang_group_id' => (string) Str::uuid(), 'title' => 'Bir', 'image' => 'sliders/a.webp'])->id,
            Slider::create(['locale' => 'tr', 'lang_group_id' => (string) Str::uuid(), 'title' => 'İki', 'image' => 'sliders/b.webp'])->id,
        ];

        $this->delete(route('admin.sliders.bulk-destroy'), ['ids' => $ids])
            ->assertSessionHas('success', '2 kayıt silindi.');

        $this->assertSoftDeleted('sliders', ['id' => $ids[1]]);
    }

    public function test_faqs_are_deleted_in_bulk(): void
    {
        $ids = [
            Faq::create(['locale' => 'tr', 'lang_group_id' => (string) Str::uuid(), 'question' => 'Bir?', 'answer' => 'Evet'])->id,
            Faq::create(['locale' => 'tr', 'lang_group_id' => (string) Str::uuid(), 'question' => 'İki?', 'answer' => 'Hayır'])->id,
        ];

        $this->delete(route('admin.faqs.bulk-destroy'), ['ids' => $ids])
            ->assertSessionHas('success', '2 kayıt silindi.');

        $this->assertSoftDeleted('faqs', ['id' => $ids[0]]);
    }

    public function test_blog_categories_are_deleted_in_bulk(): void
    {
        $ids = [
            BlogCategory::create(['locale' => 'tr', 'lang_group_id' => (string) Str::uuid(), 'name' => 'Bir', 'slug' => 'bir'])->id,
            BlogCategory::create(['locale' => 'tr', 'lang_group_id' => (string) Str::uuid(), 'name' => 'İki', 'slug' => 'iki'])->id,
        ];

        $this->delete(route('admin.blog-categories.bulk-destroy'), ['ids' => $ids])
            ->assertSessionHas('success', '2 kayıt silindi.');

        $this->assertSoftDeleted('blog_categories', ['id' => $ids[1]]);
    }

    public function test_posts_are_published_drafted_and_deleted_in_bulk(): void
    {
        $category = BlogCategory::create(['locale' => 'tr', 'lang_group_id' => (string) Str::uuid(), 'name' => 'Genel', 'slug' => 'genel']);

        $ids = collect(['Bir', 'İki'])->map(fn (string $t): int => BlogPost::create([
            'locale'           => 'tr',
            'lang_group_id'    => (string) Str::uuid(),
            'blog_category_id' => $category->id,
            'title'            => $t,
            'slug'             => Str::slug($t),
            'body'             => 'x',
            'status'           => ContentStatus::Draft,
        ])->id)->all();

        $this->patch(route('admin.blog-posts.bulk-status', ['status' => 'publish']), ['ids' => $ids])
            ->assertSessionHas('success', '2 içerik yayına alındı.');

        // Yayına alınan tarihsiz içeriğe tarih yazılıyor; yoksa "yayında"
        // görünüp listede hiç çıkmıyordu.
        $post = BlogPost::findOrFail($ids[0]);
        $this->assertSame(ContentStatus::Published, $post->status);
        $this->assertNotNull($post->published_at);

        $this->patch(route('admin.blog-posts.bulk-status', ['status' => 'draft']), ['ids' => $ids])
            ->assertSessionHas('success', '2 içerik taslağa alındı.');

        $this->assertSame(ContentStatus::Draft, BlogPost::findOrFail($ids[0])->status);

        $this->delete(route('admin.blog-posts.bulk-destroy'), ['ids' => $ids])
            ->assertSessionHas('success', '2 içerik silindi.');

        $this->assertSoftDeleted('blog_posts', ['id' => $ids[0]]);
    }

    public function test_comments_are_approved_and_deleted_in_bulk(): void
    {
        $category = BlogCategory::create(['locale' => 'tr', 'lang_group_id' => (string) Str::uuid(), 'name' => 'Genel', 'slug' => 'genel']);
        $post = BlogPost::create([
            'locale' => 'tr', 'lang_group_id' => (string) Str::uuid(), 'blog_category_id' => $category->id,
            'title' => 'Yazı', 'slug' => 'yazi', 'body' => 'x', 'status' => ContentStatus::Published, 'published_at' => now(),
        ]);

        $ids = collect(['Ali', 'Ayşe'])->map(fn (string $ad): int => BlogComment::create([
            'blog_post_id' => $post->id,
            'name'         => $ad,
            'email'        => Str::slug($ad) . '@ornek.com',
            'body'         => 'Yorum',
            'status'       => \App\Enums\CommentStatus::Pending,
        ])->id)->all();

        $this->patch(route('admin.blog-comments.bulk-approve'), ['ids' => $ids])
            ->assertSessionHas('success', '2 yorum onaylandı.');

        $this->assertSame(\App\Enums\CommentStatus::Approved, BlogComment::findOrFail($ids[0])->status);

        // İkinci kez onaylamak hiçbir şeyi değiştirmiyor; sayı da öyle diyor.
        $this->patch(route('admin.blog-comments.bulk-approve'), ['ids' => $ids])
            ->assertSessionHas('info', 'Seçilen yorumlar zaten onaylıydı.');

        $this->delete(route('admin.blog-comments.bulk-destroy'), ['ids' => $ids])
            ->assertSessionHas('success', '2 yorum silindi.');

        $this->assertSoftDeleted('blog_comments', ['id' => $ids[0]]);
    }

    public function test_users_are_deleted_in_bulk_but_never_the_one_signed_in(): void
    {
        $baskasi = User::factory()->create();

        // Oturumu açık olan kullanıcı seçili olsa bile silinmiyor: yönetici
        // kendi erişimini kapatabilirdi.
        $this->delete(route('admin.users.bulk-destroy'), ['ids' => [$baskasi->id, $this->admin->id]])
            ->assertSessionHas('success', '1 kullanıcı silindi.');

        $this->assertSoftDeleted('users', ['id' => $baskasi->id]);
        $this->assertNotSoftDeleted('users', ['id' => $this->admin->id]);
    }

    // ── Ortak kurallar ──

    /**
     * @return array<string, array{string, string}>
     */
    public static function deleteRoutes(): array
    {
        return [
            'sayfa'     => ['admin.pages.bulk-destroy', 'admin.pages.index'],
            'slider'    => ['admin.sliders.bulk-destroy', 'admin.sliders.index'],
            'soru'      => ['admin.faqs.bulk-destroy', 'admin.faqs.index'],
            'kategori'  => ['admin.blog-categories.bulk-destroy', 'admin.blog-categories.index'],
            'içerik'    => ['admin.blog-posts.bulk-destroy', 'admin.blog-posts.index'],
            'yorum'     => ['admin.blog-comments.bulk-destroy', 'admin.blog-comments.index'],
            'galeri'    => ['admin.gallery-items.bulk-destroy', 'admin.gallery-items.index'],
            'kullanıcı' => ['admin.users.bulk-destroy', 'admin.users.index'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('deleteRoutes')]
    public function test_an_empty_selection_is_refused_everywhere(string $bulkRoute, string $listRoute): void
    {
        $this->from(route($listRoute))
            ->delete(route($bulkRoute), ['ids' => []])
            ->assertSessionHasErrors('ids');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('deleteRoutes')]
    public function test_an_unknown_id_is_refused_everywhere(string $bulkRoute, string $listRoute): void
    {
        $this->from(route($listRoute))
            ->delete(route($bulkRoute), ['ids' => [999999]])
            ->assertSessionHasErrors('ids.0');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('deleteRoutes')]
    public function test_more_than_a_hundred_ids_are_refused_everywhere(string $bulkRoute, string $listRoute): void
    {
        // Ekranda seçilemeyecek kadar çok kimlik gelmişse istek formdan değil,
        // elle kurulmuştur.
        $this->from(route($listRoute))
            ->delete(route($bulkRoute), ['ids' => range(1, 101)])
            ->assertSessionHasErrors('ids');
    }
}
