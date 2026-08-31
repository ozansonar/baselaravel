<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Services\LanguageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Blog sayfasındaki arama kutusu.
 *
 * Sunucu tarafı API ile ortak ({@see \App\Services\BlogService}); buradaki
 * sınamalar ekranın kendisine bakıyor: kutu doğru yere basılıyor mu, terim
 * kategoriyle birlikte yaşıyor mu, sayfalama terimi koruyor mu ve arama sonucu
 * arama motorlarına açılıyor mu.
 */
class BlogSearchPageTest extends TestCase
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

    private function publish(string $title, ?BlogCategory $category = null): BlogPost
    {
        return BlogPost::factory()->create([
            'blog_category_id' => ($category ?? $this->category)->id,
            'title'            => $title,
            'excerpt'          => 'Genel bir özet.',
        ]);
    }

    private function blogUrl(array $query = []): string
    {
        return route('blog.index', ['locale' => 'tr', ...$query]);
    }

    public function test_the_page_carries_a_search_box(): void
    {
        $response = $this->get($this->blogUrl())->assertOk();

        $response->assertSee('name="arama"', false)
            ->assertSee('type="search"', false)
            ->assertSee(__('site.blog.search_placeholder'), false);
    }

    /**
     * Kuralsız alan yasak: kutu doğrulama motorunun kuralını taşımalı ve o
     * kural sunucudaki sınırdan gevşek olmamalı.
     */
    public function test_the_box_declares_the_same_limit_as_the_server(): void
    {
        $response = $this->get($this->blogUrl())->assertOk();

        $response->assertSee('data-validation-engine="validate[maxSize[100]]"', false)
            ->assertSee('maxlength="100"', false);
    }

    public function test_searching_filters_the_list(): void
    {
        $this->publish('Laravel 13 ile gelenler');
        $this->publish('Bahçe bakımı');

        $response = $this->get($this->blogUrl(['arama' => 'Laravel']))->assertOk();

        $response->assertSee('Laravel 13 ile gelenler')
            ->assertDontSee('Bahçe bakımı');
    }

    public function test_the_term_stays_in_the_box(): void
    {
        $this->publish('Laravel 13 ile gelenler');

        $this->get($this->blogUrl(['arama' => 'Laravel']))
            ->assertOk()
            ->assertSee('value="Laravel"', false);
    }

    /**
     * Terim ziyaretçiden geliyor; ekrana basılırken kaçırılmalı.
     */
    public function test_the_term_is_escaped_on_the_page(): void
    {
        $response = $this->get($this->blogUrl(['arama' => '<script>alert(1)</script>']))->assertOk();

        $response->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_a_search_inside_a_category_stays_in_that_category(): void
    {
        $other = BlogCategory::factory()->create(['is_active' => true, 'slug' => 'duyurular']);

        $this->publish('Laravel haberi');
        $this->publish('Laravel duyurusu', $other);

        $url = route('blog.category', ['locale' => 'tr', 'categorySlug' => 'haberler', 'arama' => 'Laravel']);

        $this->get($url)
            ->assertOk()
            ->assertSee('Laravel haberi')
            ->assertDontSee('Laravel duyurusu');
    }

    public function test_an_empty_search_shows_its_own_message(): void
    {
        $this->publish('Bahçe bakımı');

        $this->get($this->blogUrl(['arama' => 'boyle-bir-sey-yok']))
            ->assertOk()
            ->assertSee(__('site.blog.search_empty'))
            // "İçerik yok" demek yanlış olurdu: içerik var, eşleşme yok.
            ->assertDontSee(__('site.blog.empty_lead'));
    }

    /**
     * Arama yapan ziyaretçi listeye dönmenin yolunu görmeli; adres çubuğunu
     * elle temizlemek zorunda kalmamalı.
     */
    public function test_a_clear_link_appears_only_while_searching(): void
    {
        $this->publish('Laravel 13 ile gelenler');

        $this->get($this->blogUrl(['arama' => 'Laravel']))
            ->assertOk()
            ->assertSee(__('site.blog.search_clear'));

        $this->get($this->blogUrl())
            ->assertOk()
            ->assertDontSee(__('site.blog.search_clear'));
    }

    public function test_pagination_keeps_the_term(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $this->publish("Laravel yazısı {$i}");
        }

        $this->get($this->blogUrl(['arama' => 'Laravel']))
            ->assertOk()
            ->assertSee('arama=Laravel', false);
    }

    /**
     * Arama sonucu bir sayfa değil bir görünüm: sonsuz sayıda terim, sonsuz
     * sayıda adres demek. Dizine girerse aynı içerik yüzlerce adreste görünür.
     */
    public function test_a_search_result_is_not_indexable(): void
    {
        $this->publish('Laravel 13 ile gelenler');

        $this->get($this->blogUrl(['arama' => 'Laravel']))
            ->assertOk()
            ->assertSee('name="robots" content="noindex, follow"', false)
            // Kanonik basılmamalı: noindex ile birlikte kullanıldığında arama
            // motorları ikisinden hangisine uyacağını bilemiyor.
            ->assertDontSee('rel="canonical"', false);
    }

    public function test_the_plain_blog_page_stays_indexable(): void
    {
        $this->publish('Laravel 13 ile gelenler');

        $this->get($this->blogUrl())
            ->assertOk()
            ->assertSee('name="robots" content="index, follow"', false)
            ->assertSee('rel="canonical"', false);
    }

    /**
     * Sunucu son sözü söylüyor: istemcideki sınır atlansa bile terim kırpılıyor.
     */
    public function test_an_overlong_term_is_trimmed_not_refused(): void
    {
        $this->publish('Bahçe bakımı');

        $this->get($this->blogUrl(['arama' => str_repeat('a', 500)]))->assertOk();
    }
}
