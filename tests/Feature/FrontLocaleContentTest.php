<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Faq;
use App\Models\GalleryCategory;
use App\Models\GalleryItem;
use App\Models\Page;
use App\Models\Slider;
use App\Services\FaqService;
use App\Services\LanguageService;
use App\Services\SliderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public site serves content in the visitor's language, falling back to the
 * default for anything not translated yet.
 *
 * The cache is the trap here: without the language in the key the first
 * visitor's language would be handed to everyone until it expired.
 */
class FrontLocaleContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        app(LanguageService::class)->clearCache();
    }

    private function faqPair(): Faq
    {
        $turkish = Faq::create([
            'question'  => 'Nasıl üye olurum?',
            'answer'    => 'Kayıt sayfasından.',
            'is_active' => true,
        ]);

        Faq::create([
            'locale'        => 'en',
            'lang_group_id' => $turkish->lang_group_id,
            'question'      => 'How do I sign up?',
            'answer'        => 'From the register page.',
            'is_active'     => true,
        ]);

        return $turkish;
    }

    public function test_content_is_served_in_the_visitors_language(): void
    {
        $this->faqPair();

        $this->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])
            ->get('/en/sikca-sorulan-sorular')
            ->assertOk()
            ->assertSee('How do I sign up?', false)
            ->assertDontSee('Nasıl üye olurum?', false);
    }

    public function test_untranslated_content_still_appears_in_the_default_language(): void
    {
        $this->faqPair();

        Faq::create([
            'question'  => 'Yalnızca Türkçe soru',
            'answer'    => 'Cevap.',
            'is_active' => true,
        ]);

        $this->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])
            ->get('/en/sikca-sorulan-sorular')
            ->assertOk()
            ->assertSee('How do I sign up?', false)
            ->assertSee('Yalnızca Türkçe soru', false);
    }

    /**
     * The bug this guards against: one language's cached list being handed to
     * every other language.
     */
    public function test_the_cache_does_not_leak_one_language_into_another(): void
    {
        $this->faqPair();

        $faqs = app(FaqService::class);

        app()->setLocale('tr');
        $turkish = $faqs->allActive()->pluck('question')->all();

        app()->setLocale('en');
        $english = $faqs->allActive()->pluck('question')->all();

        $this->assertSame(['Nasıl üye olurum?'], $turkish);
        $this->assertSame(['How do I sign up?'], $english, 'Türkçe cache İngilizceye sızdı');
    }

    /**
     * Cache invalidation lives in the services, so this goes through the same
     * path the admin screens use rather than writing the model directly.
     */
    public function test_saving_through_the_service_clears_the_cache_for_every_language(): void
    {
        $faqs = app(FaqService::class);

        app()->setLocale('en');
        $this->assertCount(0, $faqs->allActive());

        app()->setLocale('tr');
        $this->assertCount(0, $faqs->allActive());

        $faqs->create([
            'question'  => 'Yeni soru',
            'answer'    => 'Yeni cevap',
            'is_active' => true,
        ]);

        app()->setLocale('tr');
        $this->assertCount(1, $faqs->allActive(), 'Türkçe cache temizlenmedi');

        // The Turkish row is the fallback for English until it is translated.
        app()->setLocale('en');
        $this->assertCount(1, $faqs->allActive(), 'İngilizce cache temizlenmedi');
    }

    public function test_a_page_resolves_by_its_slug_in_the_current_language(): void
    {
        $turkish = Page::create([
            'title'   => 'Hakkımızda',
            'content' => '<p>Türkçe metin</p>',
            'status'  => 'published',
        ]);

        Page::create([
            'locale'        => 'en',
            'lang_group_id' => $turkish->lang_group_id,
            'title'         => 'About Us',
            'slug'          => 'about-us',
            'content'       => '<p>English text</p>',
            'status'        => 'published',
        ]);

        $this->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])
            ->get('/en/about-us')
            ->assertOk()
            ->assertSee('English text', false);

        $this->withHeaders(['Accept-Language' => 'tr'])
            ->get('/tr/hakkimizda')
            ->assertOk()
            ->assertSee('Türkçe metin', false);
    }

    public function test_sliders_follow_the_visitors_language(): void
    {
        $turkish = Slider::create([
            'title'      => 'Türkçe Slider',
            'image'      => 'sliders/tr.webp',
            'is_active'  => true,
            'sort_order' => 0,
        ]);

        Slider::create([
            'locale'        => 'en',
            'lang_group_id' => $turkish->lang_group_id,
            'title'         => 'English Slider',
            'image'         => 'sliders/en.webp',
            'is_active'     => true,
            'sort_order'    => 0,
        ]);

        $sliders = app(SliderService::class);

        app()->setLocale('en');
        $this->assertSame('English Slider', $sliders->allActive()->first()?->title);

        app()->setLocale('tr');
        $this->assertSame('Türkçe Slider', $sliders->allActive()->first()?->title);
    }

    /**
     * Blade reads @section('x', null) as the block form and opens an output
     * buffer nothing ever closes. A page with neither a meta description nor an
     * excerpt used to leak one on every view.
     */
    public function test_rendering_a_page_leaves_no_output_buffer_open(): void
    {
        Page::create([
            'title'   => 'Açıklamasız Sayfa',
            'content' => '<p>Metin</p>',
            'status'  => 'published',
        ]);

        $before = ob_get_level();

        $this->get('/tr/aciklamasiz-sayfa')->assertOk();

        $this->assertSame($before, ob_get_level(), 'Sayfa render edilirken çıktı tamponu açık kaldı');
    }

    /**
     * A slider's artwork usually carries text, so each language keeps its own.
     */
    public function test_each_language_serves_its_own_slider_image(): void
    {
        $turkish = Slider::create([
            'title'      => 'Kampanya',
            'image'      => 'sliders/kampanya-tr.webp',
            'is_active'  => true,
            'sort_order' => 0,
        ]);

        Slider::create([
            'locale'        => 'en',
            'lang_group_id' => $turkish->lang_group_id,
            'title'         => 'Campaign',
            'image'         => 'sliders/campaign-en.webp',
            'is_active'     => true,
            'sort_order'    => 0,
        ]);

        $sliders = app(SliderService::class);

        app()->setLocale('en');
        $this->assertSame('sliders/campaign-en.webp', $sliders->allActive()->first()?->image);

        app()->setLocale('tr');
        $this->assertSame('sliders/kampanya-tr.webp', $sliders->allActive()->first()?->image);
    }

    // ── Blog ──

    /**
     * @return array{post: BlogPost, category: BlogCategory}
     */
    private function blogPair(): array
    {
        $turkishCategory = BlogCategory::factory()->create(['locale' => 'tr', 'name' => 'Duyurular', 'slug' => 'duyurular']);
        $englishCategory = BlogCategory::factory()->create([
            'locale'        => 'en',
            'lang_group_id' => $turkishCategory->lang_group_id,
            'name'          => 'Announcements',
            'slug'          => 'announcements',
        ]);

        $turkishPost = BlogPost::factory()->create([
            'locale'           => 'tr',
            'blog_category_id' => $turkishCategory->id,
            'title'            => 'Türkçe Yazı',
            'slug'             => 'turkce-yazi',
        ]);

        BlogPost::factory()->create([
            'locale'           => 'en',
            'lang_group_id'    => $turkishPost->lang_group_id,
            'blog_category_id' => $englishCategory->id,
            'title'            => 'English Post',
            'slug'             => 'english-post',
        ]);

        return ['post' => $turkishPost, 'category' => $turkishCategory];
    }

    public function test_the_blog_list_follows_the_visitors_language(): void
    {
        $this->blogPair();

        $this->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])
            ->get('/en/blog')
            ->assertOk()
            ->assertSee('English Post', false)
            ->assertDontSee('Türkçe Yazı', false);

        $this->withHeaders(['Accept-Language' => 'tr'])
            ->get('/tr/blog')
            ->assertOk()
            ->assertSee('Türkçe Yazı', false);
    }

    public function test_an_untranslated_post_still_shows_in_the_default_language(): void
    {
        $this->blogPair();

        $category = BlogCategory::where('locale', 'tr')->firstOrFail();
        BlogPost::factory()->create([
            'locale'           => 'tr',
            'blog_category_id' => $category->id,
            'title'            => 'Yalnızca Türkçe Yazı',
            'slug'             => 'yalnizca-turkce',
        ]);

        $this->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])
            ->get('/en/blog')
            ->assertOk()
            ->assertSee('English Post', false)
            ->assertSee('Yalnızca Türkçe Yazı', false);
    }

    public function test_a_post_resolves_by_its_slug_in_the_current_language(): void
    {
        $this->blogPair();

        $this->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])
            ->get('/en/blog/announcements/english-post')
            ->assertOk()
            ->assertSee('English Post', false);

        $this->withHeaders(['Accept-Language' => 'tr'])
            ->get('/tr/blog/duyurular/turkce-yazi')
            ->assertOk()
            ->assertSee('Türkçe Yazı', false);
    }

    public function test_blog_categories_follow_the_visitors_language(): void
    {
        $this->blogPair();

        $categories = app(\App\Services\BlogCategoryService::class);

        app()->setLocale('en');
        $this->assertSame(['Announcements'], $categories->allActive()->pluck('name')->all());

        app()->setLocale('tr');
        $this->assertSame(['Duyurular'], $categories->allActive()->pluck('name')->all(), 'Kategori cache i dile göre ayrılmıyor');
    }

    // ── Galeri ──

    public function test_gallery_items_follow_the_visitors_language(): void
    {
        $category = GalleryCategory::factory()->create(['locale' => 'tr']);

        $turkish = GalleryItem::factory()->create([
            'locale'              => 'tr',
            'gallery_category_id' => $category->id,
            'title'               => 'Türkçe Kare',
            'image'               => 'gallery/tr.webp',
        ]);

        GalleryItem::factory()->create([
            'locale'              => 'en',
            'lang_group_id'       => $turkish->lang_group_id,
            'gallery_category_id' => $category->id,
            'title'               => 'English Shot',
            'image'               => 'gallery/en.webp',
        ]);

        $gallery = app(\App\Services\GalleryService::class);

        app()->setLocale('en');
        $english = $gallery->activePhotos();
        $this->assertSame(['English Shot'], $english->pluck('title')->all());
        $this->assertSame('gallery/en.webp', $english->first()?->image);

        app()->setLocale('tr');
        $turkishItems = $gallery->activePhotos();
        $this->assertSame(['Türkçe Kare'], $turkishItems->pluck('title')->all(), 'Galeri cache i dile göre ayrılmıyor');
        $this->assertSame('gallery/tr.webp', $turkishItems->first()?->image);
    }

    // ── İçerikten içeriğe dil geçişi ──

    public function test_a_post_links_straight_to_its_translation(): void
    {
        $this->blogPair();

        // Başlıktaki dil değiştirici sitenin dilini değiştiriyor; okunan yazının
        // çevirisine götürdüğü tıklamadan anlaşılmıyordu. Bağlantı artık metnin
        // yanında ve karşı dilde yazılı.
        $this->get(route('blog.show', ['locale' => 'tr', 'categorySlug' => 'duyurular', 'slug' => 'turkce-yazi']))
            ->assertOk()
            ->assertSee('content-langs__link')
            ->assertSee('Read in English')
            ->assertSee(route('blog.show', ['locale' => 'en', 'categorySlug' => 'announcements', 'slug' => 'english-post']));
    }

    public function test_a_post_with_no_translation_offers_no_link_and_the_switcher_says_so(): void
    {
        $category = BlogCategory::factory()->create(['locale' => 'tr', 'name' => 'Duyurular', 'slug' => 'duyurular']);
        BlogPost::factory()->create([
            'locale'           => 'tr',
            'blog_category_id' => $category->id,
            'title'            => 'Yalnız Türkçe',
            'slug'             => 'yalniz-turkce',
        ]);

        $response = $this->get(route('blog.show', ['locale' => 'tr', 'categorySlug' => 'duyurular', 'slug' => 'yalniz-turkce']));

        $response->assertOk()
            ->assertDontSee('content-langs__link')
            // Çevirisi olmayan dil sessizce ana sayfaya atmıyor, bunu söylüyor.
            ->assertSee('lang-switcher__missing');
    }

    /**
     * Çevirisi olmayan içerik ziyaretçinin dilinde de kendi diliyle basılıyor.
     * Kanonik kendini gösterseydi aynı metin iki adreste kanonik ilan edilir ve
     * arama motoru için kopya içerik doğardı.
     */
    public function test_content_served_through_the_fallback_points_its_canonical_at_its_own_language(): void
    {
        $category = BlogCategory::factory()->create(['locale' => 'tr', 'name' => 'Duyurular', 'slug' => 'duyurular']);
        BlogPost::factory()->create([
            'locale'           => 'tr',
            'blog_category_id' => $category->id,
            'title'            => 'Yalnız Türkçe',
            'slug'             => 'yalniz-turkce',
        ]);

        $turkishUrl = route('blog.show', ['locale' => 'tr', 'categorySlug' => 'duyurular', 'slug' => 'yalniz-turkce']);
        $englishUrl = route('blog.show', ['locale' => 'en', 'categorySlug' => 'duyurular', 'slug' => 'yalniz-turkce']);

        $html = $this->get($englishUrl)->assertOk()->getContent();

        $this->assertStringContainsString('<link rel="canonical" href="' . $turkishUrl . '">', $html);
        $this->assertStringNotContainsString('<link rel="canonical" href="' . $englishUrl . '">', $html);
        // Metnin dili sayfanınkinden ayrıldığında og:locale metni anlatmalı.
        $this->assertStringContainsString('<meta property="og:locale" content="tr">', $html);
    }

    /**
     * A sitemap should advertise every language, so it is deliberately not
     * scoped to the visitor's locale.
     */
    public function test_the_sitemap_lists_every_language(): void
    {
        $this->blogPair();

        $urls = collect(app(\App\Services\SitemapService::class)->generateUrls())->pluck('loc');

        $this->assertTrue($urls->contains(fn (string $u): bool => str_contains($u, 'turkce-yazi')));
        $this->assertTrue($urls->contains(fn (string $u): bool => str_contains($u, 'english-post')));
    }
}
