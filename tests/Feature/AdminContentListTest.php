<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Faq;
use App\Models\GalleryCategory;
use App\Models\GalleryItem;
use App\Models\Page;
use App\Models\Role;
use App\Models\User;
use App\Services\ContentListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Genel içerik listesi.
 *
 * Dört tür tek listede birleşiyor ve sütunları farklı: blog ile sayfada durum
 * bir enum, galeri ile SSS'de bir bayrak. Sınavın ağırlığı bu birleşmenin
 * doğru olmasında — süzgeç dört farklı şeyi soruyorsa liste yalan söyler.
 */
class AdminContentListTest extends TestCase
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
            'first_name' => 'Icerik', 'last_name' => 'Yonetici',
            'email' => 'icerik@example.test', 'password' => 'sifre-123456', 'is_active' => true,
        ]);
        $user->markEmailAsVerified();
        $user->roles()->attach(Role::where('slug', 'admin')->firstOrFail()->id);

        return $user->fresh();
    }

    private function seedContent(): void
    {
        $category = BlogCategory::create(['locale' => 'tr', 'name' => 'Genel', 'slug' => 'genel', 'is_active' => true]);

        BlogPost::create([
            'locale' => 'tr', 'blog_category_id' => $category->id, 'title' => 'Yayındaki yazı',
            'slug' => 'yayindaki-yazi', 'body' => 'Gövde', 'status' => 'published', 'published_at' => now(),
        ]);
        BlogPost::create([
            'locale' => 'en', 'blog_category_id' => $category->id, 'title' => 'Draft post',
            'slug' => 'draft-post', 'body' => 'Body', 'status' => 'draft',
        ]);

        Page::create([
            'locale' => 'tr', 'title' => 'Hakkımızda', 'slug' => 'hakkimizda',
            'content' => 'İçerik', 'status' => 'published', 'published_at' => now(),
        ]);

        $galleryCategory = GalleryCategory::create(['locale' => 'tr', 'name' => 'Ofis', 'slug' => 'ofis', 'is_active' => true]);
        GalleryItem::create([
            'locale' => 'tr', 'gallery_category_id' => $galleryCategory->id, 'title' => 'Ofis görseli',
            'image' => 'ornek.webp', 'type' => \App\Enums\GalleryType::Photo->value, 'is_active' => true,
        ]);
        GalleryItem::create([
            'locale' => 'tr', 'gallery_category_id' => $galleryCategory->id, 'title' => 'Pasif görsel',
            'image' => 'ornek2.webp', 'type' => \App\Enums\GalleryType::Photo->value, 'is_active' => false,
        ]);

        Faq::create(['locale' => 'tr', 'question' => 'Nasıl üye olurum?', 'answer' => 'Cevap', 'is_active' => true]);
    }

    public function test_the_list_carries_every_content_type(): void
    {
        $this->seedContent();

        $counts = app(ContentListService::class)->counts();

        $this->assertSame(2, $counts['blog_post']);
        $this->assertSame(1, $counts['page']);
        $this->assertSame(2, $counts['gallery_item']);
        $this->assertSame(1, $counts['faq']);
        $this->assertSame(6, $counts['all']);
    }

    public function test_the_type_filter_narrows_the_list(): void
    {
        $this->seedContent();

        $items = app(ContentListService::class)->paginate(25, ['type' => 'page']);

        $this->assertSame(1, $items->total());
        $this->assertSame('page', $items->first()->type);
    }

    /**
     * Blog "published" enum'u ile galerinin is_active bayrağı aynı süzgeçten
     * geçmeli; yoksa "yayında" demek türden türe farklı bir şey olurdu.
     */
    public function test_the_status_filter_means_the_same_thing_for_every_type(): void
    {
        $this->seedContent();

        $published = app(ContentListService::class)->paginate(25, ['status' => 'published']);
        $drafts = app(ContentListService::class)->paginate(25, ['status' => 'draft']);

        // Yayında: yazı, sayfa, galeri öğesi, SSS.
        $this->assertSame(4, $published->total());

        // Yayında olmayan: taslak yazı ve pasif galeri öğesi.
        $this->assertSame(2, $drafts->total());
    }

    public function test_the_language_filter_works_across_types(): void
    {
        $this->seedContent();

        $english = app(ContentListService::class)->paginate(25, ['locale' => 'en']);

        $this->assertSame(1, $english->total());
        $this->assertSame('Draft post', $english->first()->title);
    }

    public function test_the_search_matches_titles_and_questions(): void
    {
        $this->seedContent();

        $service = app(ContentListService::class);

        $this->assertSame(1, $service->paginate(25, ['search' => 'Hakkımızda'])->total());
        // SSS'de başlık alanı "question"; birleşimde title'a çevriliyor.
        $this->assertSame(1, $service->paginate(25, ['search' => 'üye olurum'])->total());
    }

    public function test_the_date_filter_narrows_the_list(): void
    {
        $this->seedContent();

        $service = app(ContentListService::class);

        $this->assertSame(6, $service->paginate(25, ['from' => now()->subDay()->toDateString()])->total());
        $this->assertSame(0, $service->paginate(25, ['to' => now()->subDay()->toDateString()])->total());
    }

    public function test_deleted_records_stay_out_of_the_list(): void
    {
        $this->seedContent();

        Page::first()?->delete();

        $this->assertSame(0, app(ContentListService::class)->counts()['page']);
    }

    /**
     * Sekmedeki rakam, o sekmeye tıklandığında kaç kayıt geleceğini
     * söylemeli — kendi türünü süzgeçlemiş bir sayı yanlış olurdu.
     */
    public function test_the_tab_counts_ignore_the_type_filter(): void
    {
        $this->seedContent();

        $counts = app(ContentListService::class)->counts(['type' => 'page']);

        $this->assertSame(2, $counts['blog_post']);
        $this->assertSame(6, $counts['all']);
    }

    // ── Ekran ──

    public function test_the_screen_opens(): void
    {
        $this->seedContent();

        $this->actingAs($this->admin())->get('/admin/icerikler')
            ->assertOk()
            ->assertSee('Genel İçerik Listesi')
            ->assertSee('Yayındaki yazı')
            ->assertSee('Hakkımızda');
    }

    public function test_the_screen_is_closed_without_content_permission(): void
    {
        $user = User::create([
            'first_name' => 'Sade', 'last_name' => 'Kullanici',
            'email' => 'sade-icerik@example.test', 'password' => 'sifre-123456', 'is_active' => true,
        ]);
        $user->markEmailAsVerified();

        $this->actingAs($user)->get('/admin/icerikler')->assertForbidden();
    }

    public function test_the_rows_link_to_their_own_screens(): void
    {
        $this->seedContent();

        $post = BlogPost::where('slug', 'yayindaki-yazi')->firstOrFail();

        $this->actingAs($this->admin())->get('/admin/icerikler')
            ->assertOk()
            ->assertSee(route('admin.blog-posts.edit', $post->id), false);
    }

    public function test_an_unknown_per_page_falls_back(): void
    {
        $this->seedContent();

        $this->actingAs($this->admin())->get('/admin/icerikler?per_page=9999')
            ->assertOk();
    }
}
