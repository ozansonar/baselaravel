<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Services\LanguageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Yazı araması.
 *
 * Arama LikeSearch üzerinden yapılıyor: ziyaretçinin yazdığı `%` ve `_` joker
 * değil harf sayılıyor ve kaçış biçimi hem MySQL'de hem SQLite'ta çalışıyor.
 * Bu kural bir üretim hatasından doğdu (bkz. LikeSearchIsPortableTest); yeni
 * yazılan her arama ona uymak zorunda.
 */
class ApiBlogSearchTest extends TestCase
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

    private function publish(string $title, ?string $excerpt = null, ?BlogCategory $category = null): BlogPost
    {
        return BlogPost::factory()->create([
            'blog_category_id' => ($category ?? $this->category)->id,
            'title'            => $title,
            'excerpt'          => $excerpt ?? 'Genel bir özet.',
        ]);
    }

    private function titles(string $url): array
    {
        return collect($this->getJson($url)->assertOk()->json('data'))->pluck('title')->all();
    }

    public function test_search_matches_the_title(): void
    {
        $this->publish('Laravel 13 ile gelenler');
        $this->publish('Bahçe bakımı');

        $this->assertSame(['Laravel 13 ile gelenler'], $this->titles('/api/v1/blog/posts?search=Laravel'));
    }

    public function test_search_matches_the_excerpt_too(): void
    {
        $this->publish('Duyuru', 'Yeni sürümde Sanctum desteği geldi.');
        $this->publish('Bahçe bakımı', 'Sulama takvimi.');

        $this->assertSame(['Duyuru'], $this->titles('/api/v1/blog/posts?search=Sanctum'));
    }

    /**
     * Gövde bilerek aranmıyor: zengin metin editöründen geldiği için HTML ve
     * "div" ya da "strong" arandığında her yazı eşleşirdi.
     */
    public function test_search_does_not_match_html_in_the_body(): void
    {
        $post = $this->publish('Bahçe bakımı', 'Sulama takvimi.');
        $post->update(['body' => '<div><strong>bahce</strong></div>']);

        $this->assertSame([], $this->titles('/api/v1/blog/posts?search=strong'));
    }

    public function test_search_is_case_insensitive_and_partial(): void
    {
        $this->publish('Laravel 13 ile gelenler');

        $this->assertCount(1, $this->titles('/api/v1/blog/posts?search=laravel'));
        $this->assertCount(1, $this->titles('/api/v1/blog/posts?search=RAVEL'));
    }

    /**
     * Ziyaretçinin yazdığı joker karakter harf sayılmalı: "%" yazan biri
     * süzgeç yaptığını sanarak bütün listeye bakmamalı.
     */
    public function test_a_wildcard_is_treated_as_a_letter(): void
    {
        $this->publish('Bahçe bakımı');
        $this->publish('İndirim %50');

        // '%' bütün listeyi getirmemeli — yalnız gerçekten '%' içeren yazıyı.
        $this->assertSame(['İndirim %50'], $this->titles('/api/v1/blog/posts?search=%25'));

        // '_' de öyle: tek karakter jokeri değil.
        $this->assertSame([], $this->titles('/api/v1/blog/posts?search=_'));
    }

    public function test_search_and_category_work_together(): void
    {
        $other = BlogCategory::factory()->create(['is_active' => true, 'slug' => 'duyurular']);

        $this->publish('Laravel haberi');
        $this->publish('Laravel duyurusu', null, $other);

        $this->assertSame(['Laravel haberi'], $this->titles('/api/v1/blog/posts?category=haberler&search=Laravel'));
    }

    /**
     * Eşleşme bulunmaması hata değil, geçerli bir cevap — olmayan bir kategori
     * slug'ının aksine.
     */
    public function test_no_match_is_an_empty_list_not_an_error(): void
    {
        $this->publish('Bahçe bakımı');

        $response = $this->getJson('/api/v1/blog/posts?search=boyle-bir-sey-yok')->assertOk();

        $response->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 0)
            ->assertJsonCount(0, 'data');
    }

    public function test_an_empty_search_is_ignored(): void
    {
        $this->publish('Bahçe bakımı');
        $this->publish('Laravel haberi');

        $this->assertCount(2, $this->titles('/api/v1/blog/posts?search='));
        $this->assertCount(2, $this->titles('/api/v1/blog/posts?search=%20%20'));
    }

    /**
     * Sınırsız uzunlukta bir terim, her istekte bütün tabloyu tarayan bir
     * sorguya dönüşebilir.
     */
    public function test_an_overlong_search_is_refused(): void
    {
        $this->getJson('/api/v1/blog/posts?search=' . str_repeat('a', 101))
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['search']]);
    }

    public function test_search_results_stay_paginated(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->publish("Laravel yazısı {$i}");
        }

        $response = $this->getJson('/api/v1/blog/posts?search=Laravel&per_page=2')->assertOk();

        $response->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.has_more', true);

        // Sayfalama bağlantıları arama terimini korumalı, yoksa ikinci sayfa
        // bütün listeyi gösterir.
        $this->assertStringContainsString('search=Laravel', (string) $response->json('links.next'));
    }

    public function test_search_only_finds_published_posts(): void
    {
        $this->publish('Laravel yayında');
        BlogPost::factory()->draft()->create([
            'blog_category_id' => $this->category->id,
            'title'            => 'Laravel taslak',
        ]);

        $this->assertSame(['Laravel yayında'], $this->titles('/api/v1/blog/posts?search=Laravel'));
    }
}
