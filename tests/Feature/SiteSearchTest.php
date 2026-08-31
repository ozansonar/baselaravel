<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Enums\SearchType;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Faq;
use App\Models\GalleryCategory;
use App\Models\GalleryItem;
use App\Models\Page;
use App\Services\LanguageService;
use App\Services\SearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Site geneli arama — dört türü tek sorguda tarayan birleşik (UNION) sorgu.
 *
 * Türleri ayrı ayrı sorgulayıp PHP'de birleştirmek daha kolay olurdu ama
 * sayfalama bozulurdu: doğru toplam ve doğru sayfa dilimi için her türden
 * bütün eşleşmeleri belleğe çekmek gerekirdi. Buradaki sınamalar hem sonucun
 * doğruluğunu hem de sayfalamanın gerçekten sorgudan geldiğini bekçiliyor.
 */
class SiteSearchTest extends TestCase
{
    use RefreshDatabase;

    private BlogCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        app(LanguageService::class)->clearCache();

        $this->category = BlogCategory::factory()->create(['is_active' => true, 'slug' => 'haberler']);
    }

    private function service(): SearchService
    {
        return app(SearchService::class);
    }

    private function publish(string $title, string $excerpt = 'özet', string $body = '<p>gövde</p>'): BlogPost
    {
        return BlogPost::factory()->create([
            'blog_category_id' => $this->category->id,
            'title'            => $title,
            'excerpt'          => $excerpt,
            'body'             => $body,
        ]);
    }

    // ── Kapsam ──

    public function test_it_searches_every_configured_type(): void
    {
        $this->publish('Laravel rehberi');
        Page::factory()->create(['title' => 'Laravel hakkında', 'slug' => 'laravel-hakkinda', 'status' => ContentStatus::Published]);
        Faq::factory()->create(['is_active' => true, 'question' => 'Laravel nedir?', 'answer' => 'Bir çerçeve.']);
        GalleryItem::factory()->create(['is_active' => true, 'title' => 'Laravel buluşması']);

        $counts = $this->service()->countsByType('Laravel');

        $this->assertSame(['blog' => 1, 'page' => 1, 'faq' => 1, 'gallery' => 1], $counts);
        $this->assertSame(4, $this->service()->search('Laravel')->total());
    }

    public function test_a_type_can_be_switched_off_in_config(): void
    {
        $this->publish('Laravel rehberi');
        Faq::factory()->create(['is_active' => true, 'question' => 'Laravel nedir?']);

        config(['search.types' => ['blog']]);

        $this->assertSame(1, $this->service()->search('Laravel')->total());
        $this->assertSame(['blog' => 1], $this->service()->countsByType('Laravel'));
    }

    public function test_the_type_filter_narrows_the_result(): void
    {
        $this->publish('Laravel rehberi');
        Faq::factory()->create(['is_active' => true, 'question' => 'Laravel nedir?']);

        $this->assertSame(1, $this->service()->search('Laravel', SearchType::Faq)->total());
    }

    // ── Yalnız yayında olan içerik ──

    public function test_unpublished_and_passive_content_stays_out(): void
    {
        BlogPost::factory()->draft()->create(['blog_category_id' => $this->category->id, 'title' => 'Laravel taslak']);
        Page::factory()->create(['title' => 'Laravel taslak sayfa', 'slug' => 'taslak', 'status' => ContentStatus::Draft]);
        Faq::factory()->create(['is_active' => false, 'question' => 'Laravel gizli soru']);
        GalleryItem::factory()->create(['is_active' => false, 'title' => 'Laravel gizli görsel']);

        $this->assertSame(0, $this->service()->search('Laravel')->total());
    }

    public function test_soft_deleted_content_stays_out(): void
    {
        $post = $this->publish('Laravel rehberi');
        $this->assertSame(1, $this->service()->search('Laravel')->total());

        $post->delete();

        $this->assertSame(0, $this->service()->search('Laravel')->total());
    }

    // ── Alaka sıralaması ──

    /**
     * Puansız sıralamada "hakkımızda" araması, kelimeyi metninin ortasında
     * geçiren bir yazıyı "Hakkımızda" sayfasının üstüne koyabiliyordu.
     */
    public function test_a_title_match_outranks_a_body_match(): void
    {
        $this->publish('Bahçe bakımı', 'özet', '<p>burada laravel geçiyor</p>');
        $this->publish('Laravel rehberi');

        $titles = collect($this->service()->search('Laravel')->items())->pluck('title');

        $this->assertSame(['Laravel rehberi', 'Bahçe bakımı'], $titles->all());
    }

    public function test_a_title_that_starts_with_the_term_ranks_highest(): void
    {
        $this->publish('Kapsamlı Laravel rehberi');
        $this->publish('Laravel rehberi');

        $titles = collect($this->service()->search('Laravel')->items())->pluck('title');

        $this->assertSame('Laravel rehberi', $titles->first());
    }

    // ── Dil ──

    public function test_results_follow_the_visitors_language_with_a_fallback(): void
    {
        $turkish = $this->publish('Laravel rehberi');

        BlogPost::factory()->create([
            'locale'           => 'en',
            'lang_group_id'    => $turkish->lang_group_id,
            'blog_category_id' => $this->category->id,
            'title'            => 'Laravel guide',
            'slug'             => 'laravel-guide',
        ]);

        // Yalnız çevirisi olmayan içerik varsayılan dilden düşer; çevirisi olan
        // iki kez görünmez.
        $this->publish('Laravel yalnız Türkçe');

        app()->setLocale('en');
        $english = collect($this->service()->search('Laravel')->items())->pluck('title');

        $this->assertContains('Laravel guide', $english);
        $this->assertNotContains('Laravel rehberi', $english);
        $this->assertContains('Laravel yalnız Türkçe', $english);
        $this->assertSame(2, $english->count());
    }

    // ── Güvenlik ve dayanıklılık ──

    /**
     * Kural bir üretim hatasından doğdu: kaçırılmamış bir joker, süzgeç
     * yaptığını sanan ziyaretçiye bütün siteyi gösteriyor.
     */
    public function test_wildcards_typed_by_the_visitor_are_letters(): void
    {
        $this->publish('Bahçe bakımı');
        $this->publish('İndirim %50');

        $this->assertSame(1, $this->service()->search('%')->total());
        $this->assertSame(0, $this->service()->search('_')->total());
    }

    public function test_the_escape_character_itself_is_escaped(): void
    {
        $this->publish('Merhaba! dünya');
        $this->publish('Bahçe bakımı');

        $this->assertSame(1, $this->service()->search('!')->total());
    }

    public function test_a_term_shorter_than_the_minimum_is_not_a_search(): void
    {
        $this->assertFalse($this->service()->isSearchable('a'));
        $this->assertFalse($this->service()->isSearchable(' '));
        $this->assertTrue($this->service()->isSearchable('ab'));
    }

    public function test_an_overlong_term_is_trimmed(): void
    {
        $max = (int) config('search.max_length');

        $this->assertSame($max, mb_strlen((string) $this->service()->normalize(str_repeat('a', $max + 50))));
    }

    // ── Sunum ──

    /**
     * Sayfa içeriği ve blog gövdesi zengin metin editöründen geliyor. Ham
     * basılsaydı özet "<p>" ile başlardı ya da yarım bir etiket düzeni bozardı.
     */
    public function test_the_snippet_is_plain_text_and_trimmed(): void
    {
        Page::factory()->create([
            'title'   => 'Laravel hakkında',
            'slug'    => 'laravel-hakkinda',
            'status'  => ContentStatus::Published,
            'excerpt' => '<p><strong>Kalın</strong> bir  özet</p>',
        ]);

        $row = $this->service()->search('Laravel')->items()[0];
        $item = $this->service()->present($row);

        $this->assertSame('Kalın bir özet', $item['snippet']);
    }

    public function test_each_type_resolves_to_its_own_address(): void
    {
        $this->publish('Laravel rehberi');
        Page::factory()->create(['title' => 'Laravel sayfa', 'slug' => 'laravel-sayfa', 'status' => ContentStatus::Published]);
        Faq::factory()->create(['is_active' => true, 'question' => 'Laravel nedir?']);
        $gc = GalleryCategory::factory()->create(['is_active' => true, 'slug' => 'etkinlik']);
        GalleryItem::factory()->create(['is_active' => true, 'gallery_category_id' => $gc->id, 'title' => 'Laravel buluşması']);

        $urls = [];

        foreach ($this->service()->search('Laravel')->items() as $row) {
            $item = $this->service()->present($row);
            $urls[$item['type']->value] = $item['url'];
        }

        $this->assertStringContainsString('/blog/haberler/', $urls['blog']);
        $this->assertStringContainsString('/laravel-sayfa', $urls['page']);
        // SSS'nin kendi sayfası yok: akordeon çapaları sıra numarasından
        // türüyor, yani karta bağlanabilecek kalıcı bir çapa yok.
        $this->assertStringContainsString('sikca-sorulan-sorular', $urls['faq']);
        $this->assertStringContainsString('kategori=etkinlik', $urls['gallery']);
    }

    // ── Sayfalama ──

    public function test_pagination_comes_from_the_query_not_from_php(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $this->publish("Laravel yazısı {$i}");
        }

        $page = $this->service()->search('Laravel', null, 5);

        $this->assertSame(12, $page->total());
        $this->assertSame(3, $page->lastPage());
        $this->assertCount(5, $page->items());
    }

    /**
     * Eşit puanlı ve eşit tarihli satırlarda kararlı bir sıra olmazsa ikinci
     * sayfa, birinci sayfadaki bir kaydı tekrar gösterebiliyor.
     */
    public function test_paging_never_repeats_a_row(): void
    {
        for ($i = 1; $i <= 9; $i++) {
            Faq::factory()->create(['is_active' => true, 'question' => "Laravel sorusu {$i}", 'created_at' => now()]);
        }

        $seen = [];

        for ($page = 1; $page <= 3; $page++) {
            $this->app['request']->merge(['page' => $page]);
            \Illuminate\Pagination\Paginator::currentPageResolver(fn (): int => $page);

            foreach ($this->service()->search('Laravel', null, 3)->items() as $row) {
                $seen[] = $row->type . ':' . $row->id;
            }
        }

        $this->assertSame(array_unique($seen), $seen, 'Aynı kayıt iki sayfada birden görünüyor.');
        $this->assertCount(9, $seen);
    }
}
