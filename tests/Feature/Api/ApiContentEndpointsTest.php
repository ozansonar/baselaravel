<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\CommentStatus;
use App\Enums\ContentStatus;
use App\Models\BlogCategory;
use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\Faq;
use App\Models\GalleryItem;
use App\Models\Slider;
use App\Models\Subscriber;
use App\Services\GalleryService;
use App\Services\LanguageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Öncelik 2 uçları: açılış ekranı, slider, SSS, yorumlar, bülten.
 *
 * Hepsi ön yüzün kullandığı servislerin üzerine kuruldu; buradaki sınamalar
 * ikisinin aynı şeyi gösterdiğini ve yönetim tarafına ait alanların dışarı
 * çıkmadığını doğruluyor.
 */
class ApiContentEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        app(LanguageService::class)->clearCache();
    }

    private function publishedPost(string $slug = 'ornek-yazi'): BlogPost
    {
        return BlogPost::factory()->create([
            'blog_category_id' => BlogCategory::factory()->create(['is_active' => true]),
            'slug'             => $slug,
            'status'           => ContentStatus::Published,
        ]);
    }

    // ── Açılış ekranı ──

    public function test_the_home_endpoint_answers_the_whole_screen_at_once(): void
    {
        Slider::factory()->count(2)->create(['is_active' => true]);
        $category = BlogCategory::factory()->create(['is_active' => true]);
        BlogPost::factory()->count(6)->create(['blog_category_id' => $category->id]);
        GalleryItem::factory()->count(3)->create([
            'is_active' => true,
            'image'     => 'gallery/ornek.webp',
            'type'      => \App\Enums\GalleryType::Photo,
        ]);

        $data = $this->getJson('/api/v1/home')->assertOk()->json('data');

        $this->assertCount(2, $data['sliders']);
        // Ön yüzdeki ana sayfayla aynı sayı; config'ten okunuyor.
        $this->assertCount((int) config('api.home.posts'), $data['posts']);
        $this->assertCount(3, $data['gallery']);
    }

    /**
     * Görseli olmayan öğe şeritte boşluk bırakır; ön yüzde de eleniyor.
     */
    public function test_the_home_strip_skips_images_that_are_missing(): void
    {
        GalleryItem::factory()->create(['is_active' => true, 'image' => 'gallery/var.webp']);
        GalleryItem::factory()->create(['is_active' => true, 'image' => null]);

        app(GalleryService::class);

        $this->getJson('/api/v1/home')->assertOk()->assertJsonCount(1, 'data.gallery');
    }

    // ── Slider ──

    public function test_sliders_list_only_the_active_ones_with_resolved_button_urls(): void
    {
        Slider::factory()->create([
            'is_active'   => true,
            'title'       => 'Kampanya',
            'button_text' => 'İncele',
            'button_url'  => '/iletisim',
        ]);
        Slider::factory()->create(['is_active' => false, 'title' => 'Kapalı']);

        $data = $this->getJson('/api/v1/sliders')->assertOk()->json('data');

        $this->assertCount(1, $data);
        $this->assertSame('Kampanya', $data[0]['title']);

        // Panelde göreli bir yol yazılı ama dışarı mutlak adres çıkıyor:
        // göreli adres mobil istemcide hiçbir şeye çözülmez.
        $this->assertStringStartsWith('http', (string) $data[0]['button_url']);
    }

    /**
     * Buton adresi isteğin diline göre çözülüyor.
     *
     * Panelde `/iletisim` yazılı olsa bile İngilizce isteyen istemci İngilizce
     * adresi almalı — ham yol verilseydi uygulama Türkçe sayfaya düşerdi.
     */
    public function test_the_button_url_follows_the_requested_language(): void
    {
        Slider::factory()->create(['is_active' => true, 'button_url' => '/iletisim']);

        $turkish = $this->withHeader('Accept-Language', 'tr')
            ->getJson('/api/v1/sliders')->assertOk()->json('data.0.button_url');

        $english = $this->withHeader('Accept-Language', 'en')
            ->getJson('/api/v1/sliders')->assertOk()->json('data.0.button_url');

        $this->assertStringContainsString('/tr/', (string) $turkish);
        $this->assertStringContainsString('/en/', (string) $english);
        $this->assertNotSame($turkish, $english);
    }

    public function test_a_slider_without_a_button_returns_null_not_a_hash(): void
    {
        Slider::factory()->create(['is_active' => true, 'button_url' => null, 'button_text' => null]);

        $this->getJson('/api/v1/sliders')
            ->assertOk()
            ->assertJsonPath('data.0.button_url', null);
    }

    // ── SSS ──

    public function test_faqs_list_only_the_active_ones(): void
    {
        Faq::factory()->create(['is_active' => true, 'question' => 'Nasıl üye olurum?']);
        Faq::factory()->create(['is_active' => false, 'question' => 'Gizli soru']);

        $questions = collect($this->getJson('/api/v1/faqs')->assertOk()->json('data'))->pluck('question');

        $this->assertContains('Nasıl üye olurum?', $questions);
        $this->assertNotContains('Gizli soru', $questions);
    }

    // ── Yorumlar ──

    public function test_only_approved_comments_are_listed_as_a_tree(): void
    {
        $post = $this->publishedPost();

        $approved = BlogComment::factory()->create([
            'blog_post_id' => $post->id,
            'name'         => 'Onaylı',
            'status'       => CommentStatus::Approved,
        ]);

        BlogComment::factory()->create([
            'blog_post_id' => $post->id,
            'parent_id'    => $approved->id,
            'name'         => 'Onaylı yanıt',
            'status'       => CommentStatus::Approved,
        ]);

        BlogComment::factory()->create([
            'blog_post_id' => $post->id,
            'name'         => 'Bekleyen',
            'status'       => CommentStatus::Pending,
        ]);

        $response = $this->getJson('/api/v1/blog/posts/ornek-yazi/comments')->assertOk();

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Onaylı')
            ->assertJsonPath('data.0.replies.0.name', 'Onaylı yanıt');

        // Onay bekleyen yorum hiçbir yerde görünmemeli.
        $this->assertStringNotContainsString('Bekleyen', (string) $response->getContent());
    }

    /**
     * Form yorumcuya "e-posta adresiniz yayınlanmayacaktır" diyor; bu söz
     * API'de de tutulmak zorunda.
     */
    public function test_a_comment_never_exposes_the_address_or_the_ip(): void
    {
        $post = $this->publishedPost();

        BlogComment::factory()->create([
            'blog_post_id' => $post->id,
            'email'        => 'gizli@ornek.com',
            'ip_address'   => '203.0.113.9',
            'status'       => CommentStatus::Approved,
        ]);

        $body = (string) $this->getJson('/api/v1/blog/posts/ornek-yazi/comments')->assertOk()->getContent();

        $this->assertStringNotContainsString('gizli@ornek.com', $body);
        $this->assertStringNotContainsString('203.0.113.9', $body);
    }

    public function test_the_post_detail_carries_the_comment_count_but_not_the_comments(): void
    {
        $post = $this->publishedPost();

        BlogComment::factory()->count(2)->create([
            'blog_post_id' => $post->id,
            'status'       => CommentStatus::Approved,
        ]);

        $data = $this->getJson('/api/v1/blog/posts/ornek-yazi')->assertOk()->json('data');

        $this->assertSame(2, $data['comment_count']);
        $this->assertArrayNotHasKey('comments', $data);
    }

    public function test_comments_of_an_unknown_post_are_a_404(): void
    {
        $this->getJson('/api/v1/blog/posts/olmayan-yazi/comments')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_a_submitted_comment_waits_for_approval(): void
    {
        $post = $this->publishedPost();

        $this->postJson('/api/v1/blog/comments', [
            'blog_post_id' => $post->id,
            'name'         => 'Ozan Sonar',
            'email'        => 'ozan@ornek.com',
            'body'         => 'Yazı için teşekkürler.',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', __('site.blog.comment_sent'));

        $this->assertDatabaseHas('blog_comments', [
            'blog_post_id' => $post->id,
            'email'        => 'ozan@ornek.com',
            'status'       => CommentStatus::Pending->value,
        ]);

        // Ve listede görünmüyor.
        $this->getJson('/api/v1/blog/posts/ornek-yazi/comments')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * Doğrulamadaki `exists` kuralı satırın varlığına bakıyor, yayında olup
     * olmadığına değil: taslak bir yazının kimliği tahmin edilerek yorum
     * bırakılabilirdi.
     */
    public function test_an_unpublished_post_cannot_be_commented_on(): void
    {
        $draft = BlogPost::factory()->draft()->create([
            'blog_category_id' => BlogCategory::factory()->create(['is_active' => true]),
        ]);

        $this->postJson('/api/v1/blog/comments', [
            'blog_post_id' => $draft->id,
            'name'         => 'Ozan Sonar',
            'email'        => 'ozan@ornek.com',
            'body'         => 'Görünmeyen yazıya yorum.',
        ])->assertNotFound();

        $this->assertDatabaseCount('blog_comments', 0);
    }

    public function test_comment_input_is_validated(): void
    {
        $post = $this->publishedPost();

        $this->postJson('/api/v1/blog/comments', [
            'blog_post_id' => $post->id,
            'name'         => 'A',
            'email'        => 'gecersiz',
            'body'         => 'kı',
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['name', 'email', 'body']]);
    }

    public function test_comments_are_throttled(): void
    {
        $post = $this->publishedPost();

        $limit = (int) config('api.rate_limits.comment');

        for ($attempt = 0; $attempt < $limit; $attempt++) {
            $this->postJson('/api/v1/blog/comments', [
                'blog_post_id' => $post->id,
                'name'         => 'Ozan Sonar',
                'email'        => 'ozan@ornek.com',
                'body'         => "Yorum numara {$attempt}.",
            ])->assertCreated();
        }

        $this->postJson('/api/v1/blog/comments', [
            'blog_post_id' => $post->id,
            'name'         => 'Ozan Sonar',
            'email'        => 'ozan@ornek.com',
            'body'         => 'Bir tane daha.',
        ])->assertStatus(429);
    }

    // ── Bülten ──

    public function test_subscribing_adds_the_address(): void
    {
        $this->postJson('/api/v1/newsletter/subscribe', ['email' => 'abone@gmail.com'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('subscribers', ['email' => 'abone@gmail.com']);
    }

    /**
     * Aynı adresle ikinci kez abone olmak yeni satır açmamalı: kayıt geçmişiyle
     * birlikte tutuluyor ve yeniden abone olmak onu canlandırıyor.
     */
    public function test_subscribing_twice_does_not_duplicate_the_row(): void
    {
        $payload = ['email' => 'abone@gmail.com'];

        $this->postJson('/api/v1/newsletter/subscribe', $payload)->assertOk();
        $this->postJson('/api/v1/newsletter/subscribe', $payload)->assertOk();

        $this->assertSame(1, Subscriber::where('email', 'abone@gmail.com')->count());
    }

    public function test_subscribing_validates_the_address(): void
    {
        $this->postJson('/api/v1/newsletter/subscribe', ['email' => 'bu-bir-adres-degil'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['email']]);
    }
}
