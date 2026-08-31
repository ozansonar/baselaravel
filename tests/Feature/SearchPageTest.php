<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Faq;
use App\Models\Page;
use App\Services\LanguageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Arama sayfasının kendisi: kutu, tür süzgeci, sayfalama, boş durumlar ve SEO.
 */
class SearchPageTest extends TestCase
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

    private function publish(string $title): BlogPost
    {
        return BlogPost::factory()->create([
            'blog_category_id' => $this->category->id,
            'title'            => $title,
        ]);
    }

    private function url(array $query = []): string
    {
        return route('search', ['locale' => 'tr', ...$query]);
    }

    public function test_the_page_opens_without_a_term_and_invites_a_search(): void
    {
        $this->get($this->url())
            ->assertOk()
            ->assertSee('name="arama"', false)
            ->assertSee(__('site.search.prompt'));
    }

    /**
     * Kuralsız alan yasak; kural sunucudaki sınırla aynı olmalı.
     */
    public function test_the_box_declares_the_same_limit_as_the_server(): void
    {
        $max = (int) config('search.max_length');

        $this->get($this->url())
            ->assertOk()
            ->assertSee('data-validation-engine="validate[maxSize[' . $max . ']]"', false)
            ->assertSee('maxlength="' . $max . '"', false);
    }

    public function test_a_search_lists_results_from_every_type(): void
    {
        $this->publish('Laravel rehberi');
        Page::factory()->create(['title' => 'Laravel hakkında', 'slug' => 'laravel-hakkinda', 'status' => ContentStatus::Published]);
        Faq::factory()->create(['is_active' => true, 'question' => 'Laravel nedir?']);

        $this->get($this->url(['arama' => 'Laravel']))
            ->assertOk()
            ->assertSee('Laravel rehberi')
            ->assertSee('Laravel hakkında')
            ->assertSee('Laravel nedir?');
    }

    public function test_the_type_filter_narrows_and_marks_itself(): void
    {
        $this->publish('Laravel rehberi');
        Faq::factory()->create(['is_active' => true, 'question' => 'Laravel nedir?']);

        $this->get($this->url(['arama' => 'Laravel', 'tur' => 'faq']))
            ->assertOk()
            ->assertSee('Laravel nedir?')
            ->assertDontSee('Laravel rehberi');
    }

    /**
     * Uydurma bir tür boş liste değil "tümü" görünümü vermeli; süzgeç çubuğu
     * hiçbir zaman ekranda olmayan bir seçimi işaretlememeli.
     */
    public function test_an_unknown_type_is_ignored(): void
    {
        $this->publish('Laravel rehberi');

        $this->get($this->url(['arama' => 'Laravel', 'tur' => 'uydurma']))
            ->assertOk()
            ->assertSee('Laravel rehberi');
    }

    public function test_a_too_short_term_says_why(): void
    {
        $this->publish('Laravel rehberi');

        $this->get($this->url(['arama' => 'a']))
            ->assertOk()
            ->assertSee(__('site.search.too_short'))
            ->assertDontSee('Laravel rehberi');
    }

    public function test_no_match_shows_its_own_message(): void
    {
        $this->publish('Laravel rehberi');

        $this->get($this->url(['arama' => 'boylebirseyyok']))
            ->assertOk()
            ->assertSee(__('site.search.empty'))
            ->assertDontSee(__('site.search.prompt'));
    }

    public function test_the_term_is_escaped_on_the_page(): void
    {
        $this->get($this->url(['arama' => '<script>alert(1)</script>']))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_pagination_keeps_the_term_and_the_type(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            $this->publish("Laravel yazısı {$i}");
        }

        $response = $this->get($this->url(['arama' => 'Laravel', 'tur' => 'blog']))->assertOk();

        $response->assertSee('arama=Laravel', false)
            ->assertSee('tur=blog', false);
    }

    /**
     * Sonsuz sayıda terim, sonsuz sayıda adres demek; dizine girerse aynı
     * içerik yüzlerce adreste görünür. Terimsiz sayfa ise gerçek bir sayfa.
     */
    public function test_a_search_result_is_not_indexable_but_the_bare_page_is(): void
    {
        $this->publish('Laravel rehberi');

        $this->get($this->url(['arama' => 'Laravel']))
            ->assertOk()
            ->assertSee('name="robots" content="noindex, follow"', false)
            ->assertDontSee('rel="canonical"', false);

        $this->get($this->url())
            ->assertOk()
            ->assertSee('name="robots" content="index, follow"', false)
            ->assertSee('rel="canonical"', false);
    }

    /**
     * Sayfaya her yerden ulaşılabilmeli; ulaşılamayan bir sayfa yapılmamış
     * sayılır.
     */
    public function test_every_page_links_to_the_search(): void
    {
        $this->get(route('home', ['locale' => 'tr']))
            ->assertOk()
            ->assertSee(route('search', ['locale' => 'tr']), false);
    }

    public function test_the_page_is_translated(): void
    {
        $this->get(route('search', ['locale' => 'en']))
            ->assertOk()
            ->assertSee(__('site.search.prompt', [], 'en'))
            ->assertDontSee(__('site.search.prompt', [], 'tr'));
    }
}
