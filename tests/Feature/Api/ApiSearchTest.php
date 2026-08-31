<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\ContentStatus;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Faq;
use App\Models\Page;
use App\Services\LanguageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * API araması — ön yüzdeki sayfayla aynı servisi çağırıyor, yani aynı terim
 * iki tarafta aynı sonucu ve aynı sırayı veriyor.
 */
class ApiSearchTest extends TestCase
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

    public function test_it_returns_results_from_every_type_with_counts(): void
    {
        $this->publish('Laravel rehberi');
        Page::factory()->create(['title' => 'Laravel hakkında', 'slug' => 'laravel-hakkinda', 'status' => ContentStatus::Published]);
        Faq::factory()->create(['is_active' => true, 'question' => 'Laravel nedir?']);

        $response = $this->getJson('/api/v1/search?q=Laravel')->assertOk();

        $response->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('counts.blog', 1)
            ->assertJsonPath('counts.page', 1)
            ->assertJsonPath('counts.faq', 1)
            ->assertJsonStructure(['data' => [['type' => ['value', 'label'], 'id', 'title', 'snippet', 'url']]]);
    }

    public function test_the_type_filter_narrows_the_result(): void
    {
        $this->publish('Laravel rehberi');
        Faq::factory()->create(['is_active' => true, 'question' => 'Laravel nedir?']);

        $this->getJson('/api/v1/search?q=Laravel&type=faq')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.type.value', 'faq');
    }

    public function test_a_missing_or_too_short_term_is_refused(): void
    {
        $this->getJson('/api/v1/search')
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['q']]);

        $this->getJson('/api/v1/search?q=a')
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['q']]);
    }

    public function test_an_unknown_type_is_refused(): void
    {
        $this->getJson('/api/v1/search?q=Laravel&type=uydurma')
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['type']]);
    }

    public function test_no_match_is_an_empty_list_not_an_error(): void
    {
        $this->publish('Bahçe bakımı');

        $this->getJson('/api/v1/search?q=boylebirseyyok')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 0)
            ->assertJsonCount(0, 'data');
    }

    public function test_results_are_paginated(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $this->publish("Laravel yazısı {$i}");
        }

        $this->getJson('/api/v1/search?q=Laravel&per_page=5')
            ->assertOk()
            ->assertJsonPath('meta.total', 12)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.has_more', true)
            ->assertJsonCount(5, 'data');
    }

    public function test_results_follow_the_requested_language(): void
    {
        $turkish = $this->publish('Laravel rehberi');

        BlogPost::factory()->create([
            'locale'           => 'en',
            'lang_group_id'    => $turkish->lang_group_id,
            'blog_category_id' => $this->category->id,
            'title'            => 'Laravel guide',
            'slug'             => 'laravel-guide',
        ]);

        $this->withHeader('Accept-Language', 'en')
            ->getJson('/api/v1/search?q=Laravel')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Laravel guide');
    }

    /**
     * Her terim ayrı bir sonuç; ETag'ler hiç tekrarlanmadan birikirdi.
     */
    public function test_search_is_not_cached(): void
    {
        $this->publish('Laravel rehberi');

        $response = $this->getJson('/api/v1/search?q=Laravel')->assertOk();

        $this->assertEmpty($response->headers->get('ETag'));
    }

    public function test_the_snippet_is_plain_text(): void
    {
        Page::factory()->create([
            'title'   => 'Laravel hakkında',
            'slug'    => 'laravel-hakkinda',
            'status'  => ContentStatus::Published,
            'excerpt' => '<p><strong>Kalın</strong> özet</p>',
        ]);

        $this->getJson('/api/v1/search?q=Laravel')
            ->assertOk()
            ->assertJsonPath('data.0.snippet', 'Kalın özet');
    }
}
