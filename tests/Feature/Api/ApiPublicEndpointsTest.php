<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\ContentStatus;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\ContactMessage;
use App\Models\GalleryCategory;
use App\Models\GalleryItem;
use App\Models\Menu;
use App\Models\Page;
use App\Models\MenuItem;
use App\Models\Setting;
use App\Services\LanguageService;
use App\Services\MenuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Jeton istemeyen uçlar — mobil uygulamanın ve harici ön yüzlerin ortak
 * tükettiği içerik.
 */
class ApiPublicEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        app(LanguageService::class)->clearCache();
        app(MenuService::class)->clearAllCaches();
    }

    // ── Ayarlar ──

    public function test_settings_are_grouped_and_public_only(): void
    {
        Setting::setValue('site_name', 'Örnek Site', 'general');
        Setting::setValue('contact_email', 'info@ornek.com', 'contact');
        Setting::setValue('social_instagram', 'https://instagram.com/ornek', 'social');

        $response = $this->getJson('/api/v1/settings')->assertOk();

        $response->assertJsonPath('success', true)
            ->assertJsonPath('data.general.site_name', 'Örnek Site')
            ->assertJsonPath('data.contact.contact_email', 'info@ornek.com')
            ->assertJsonPath('data.social.social_instagram', 'https://instagram.com/ornek');
    }

    /**
     * settings tablosu SMTP parolasını, reCAPTCHA gizli anahtarını ve Telegram
     * jetonunu da tutuyor. Bu uç tabloyu olduğu gibi bassaydı üçü de mobil
     * uygulamanın diskine inerdi — oradan da telefonu eline geçiren herkese.
     */
    public function test_settings_never_expose_secrets(): void
    {
        Setting::setValue('mail_password', 'cok-gizli-smtp', 'mail', 'password');
        Setting::setValue('recaptcha_secret_key', 'gizli-anahtar', 'general');
        Setting::setValue('telegram_bot_token', 'bot-jetonu', 'telegram');
        Setting::setValue('custom_head_code', '<script>alert(1)</script>', 'seo');
        Setting::setValue('admin_notification_email', 'yonetici@ornek.com', 'contact');

        $body = $this->getJson('/api/v1/settings')->assertOk()->getContent();

        foreach (['cok-gizli-smtp', 'gizli-anahtar', 'bot-jetonu', 'alert(1)', 'yonetici@ornek.com'] as $secret) {
            $this->assertStringNotContainsString($secret, (string) $body);
        }

        // Anahtar adları da çıkmamalı: varlıkları bile bilgi.
        foreach (['mail_password', 'recaptcha_secret_key', 'telegram_bot_token', 'custom_head_code'] as $key) {
            $this->assertStringNotContainsString($key, (string) $body);
        }
    }

    public function test_an_unknown_settings_group_returns_404(): void
    {
        $this->getJson('/api/v1/settings?group=mail')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    // ── Sayfalar ──

    public function test_pages_list_only_the_published_ones(): void
    {
        Page::factory()->create(['title' => 'Hakkımızda', 'slug' => 'hakkimizda', 'status' => ContentStatus::Published]);
        Page::factory()->create(['title' => 'Taslak', 'slug' => 'taslak-sayfa', 'status' => ContentStatus::Draft]);

        $slugs = collect(
            $this->getJson('/api/v1/pages')->assertOk()->json('data'),
        )->pluck('slug');

        $this->assertContains('hakkimizda', $slugs);
        $this->assertNotContains('taslak-sayfa', $slugs);
    }

    /**
     * Liste bir menü çizmek için; bütün yasal metinleri indirmek için değil.
     */
    public function test_the_page_list_does_not_carry_the_body(): void
    {
        Page::factory()->create(['slug' => 'gizlilik', 'status' => ContentStatus::Published]);

        $first = $this->getJson('/api/v1/pages')->assertOk()->json('data.0');

        $this->assertArrayNotHasKey('content', $first);
        $this->assertArrayHasKey('slug', $first);
    }

    public function test_a_page_detail_carries_the_html_content(): void
    {
        Page::factory()->create([
            'title'   => 'Gizlilik Politikası',
            'slug'    => 'gizlilik',
            'content' => '<h2>Kişisel veriler</h2><p>Metin.</p>',
            'status'  => ContentStatus::Published,
        ]);

        $this->getJson('/api/v1/pages/gizlilik')
            ->assertOk()
            ->assertJsonPath('data.slug', 'gizlilik')
            ->assertJsonPath('data.content_format', 'html')
            ->assertJsonPath('data.content', '<h2>Kişisel veriler</h2><p>Metin.</p>')
            ->assertJsonStructure(['data' => ['meta' => ['title', 'description']]]);
    }

    public function test_an_unpublished_page_is_not_reachable(): void
    {
        Page::factory()->create(['slug' => 'taslak-sayfa', 'status' => ContentStatus::Draft]);

        $this->getJson('/api/v1/pages/taslak-sayfa')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    // ── Menüler ──

    public function test_menus_return_the_tree_with_resolved_urls(): void
    {
        // Üst ve alt menü göçlerle zaten kuruluyor; sınama kendi konumunu
        // kullanıyor ki onlara bağımlı olmasın.
        $menu = Menu::factory()->create(['location' => 'custom', 'is_active' => true]);

        $parent = MenuItem::factory()->create([
            'menu_id' => $menu->id, 'label' => 'Kurumsal', 'url' => '/kurumsal', 'sort_order' => 1,
        ]);

        MenuItem::factory()->create([
            'menu_id' => $menu->id, 'parent_id' => $parent->id,
            'label'   => 'Hakkımızda', 'url' => '/hakkimizda', 'sort_order' => 1,
        ]);

        app(MenuService::class)->clearAllCaches();

        $response = $this->getJson('/api/v1/menus/custom')->assertOk();

        $response->assertJsonPath('data.location', 'custom')
            ->assertJsonPath('data.items.0.label', 'Kurumsal')
            ->assertJsonPath('data.items.0.children.0.label', 'Hakkımızda');

        // Adres çözülmüş geliyor: istemci link_type'a bakıp kendi kurmuyor.
        $this->assertNotEmpty($response->json('data.items.0.url'));

        // Toplu uçta her konum bir kez: uygulama açılışında tek istek yetsin.
        $locations = collect($this->getJson('/api/v1/menus')->assertOk()->json('data'))
            ->pluck('location');

        $this->assertEqualsCanonicalizing(['custom', 'footer', 'header'], $locations->all());
    }

    public function test_an_unknown_menu_location_returns_404(): void
    {
        $this->getJson('/api/v1/menus/yok-boyle-bir-konum')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    // ── Diller ve çeviriler ──

    public function test_languages_endpoint_lists_active_languages(): void
    {
        $this->getJson('/api/v1/languages')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.default', app(LanguageService::class)->defaultCode())
            ->assertJsonStructure(['data' => [['code', 'name', 'native_name', 'is_default']]]);
    }

    public function test_translations_return_flat_keys_for_the_requested_locale(): void
    {
        $tr = $this->withHeader('Accept-Language', 'tr')
            ->getJson('/api/v1/translations')
            ->assertOk();

        $this->assertSame('tr', $tr->json('data.locale'));
        $this->assertNotEmpty($tr->json('data.groups.site'));

        $en = $this->withHeader('Accept-Language', 'en')
            ->getJson('/api/v1/translations')
            ->assertOk();

        $this->assertSame('en', $en->json('data.locale'));
        $this->assertNotSame($tr->json('data.groups.site'), $en->json('data.groups.site'));
    }

    public function test_a_translation_group_outside_the_allow_list_returns_404(): void
    {
        // validation.php yer tutucular ve çoğul kuralları taşıyor; dışarı
        // açılmıyor.
        $this->getJson('/api/v1/translations?group=validation')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    // ── Blog ──

    public function test_blog_posts_are_paginated_and_only_published(): void
    {
        $category = BlogCategory::factory()->create(['is_active' => true]);

        BlogPost::factory()->count(3)->create(['blog_category_id' => $category->id]);
        BlogPost::factory()->draft()->create(['blog_category_id' => $category->id]);

        $response = $this->getJson('/api/v1/blog/posts?per_page=2')->assertOk();

        $response->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.has_more', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['id', 'title', 'slug', 'excerpt', 'category', 'url']]]);

        // Liste gövdeyi taşımıyor: yirmi yazılık bir sayfa aksi hâlde yirmi tam
        // metin demek olurdu.
        $this->assertArrayNotHasKey('body', $response->json('data.0'));
    }

    public function test_per_page_is_capped(): void
    {
        $category = BlogCategory::factory()->create(['is_active' => true]);
        BlogPost::factory()->count(3)->create(['blog_category_id' => $category->id]);

        $this->getJson('/api/v1/blog/posts?per_page=99999')
            ->assertOk()
            ->assertJsonPath('meta.per_page', (int) config('api.pagination.max_per_page'));
    }

    public function test_blog_posts_can_be_filtered_by_category(): void
    {
        $wanted = BlogCategory::factory()->create(['is_active' => true, 'slug' => 'haberler']);
        $other  = BlogCategory::factory()->create(['is_active' => true, 'slug' => 'duyurular']);

        BlogPost::factory()->count(2)->create(['blog_category_id' => $wanted->id]);
        BlogPost::factory()->create(['blog_category_id' => $other->id]);

        $this->getJson('/api/v1/blog/posts?category=haberler')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $this->getJson('/api/v1/blog/posts?category=olmayan-kategori')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_a_blog_post_detail_carries_the_body_and_bumps_the_view_counter(): void
    {
        $category = BlogCategory::factory()->create(['is_active' => true]);
        $post = BlogPost::factory()->create([
            'blog_category_id' => $category->id,
            'slug'             => 'ornek-yazi',
            'views'            => 5,
        ]);

        $this->getJson('/api/v1/blog/posts/ornek-yazi')
            ->assertOk()
            ->assertJsonPath('data.slug', 'ornek-yazi')
            ->assertJsonStructure(['data' => ['body', 'meta' => ['title', 'description']]]);

        $this->assertSame(6, $post->fresh()?->views);
    }

    public function test_a_draft_post_is_not_reachable(): void
    {
        $category = BlogCategory::factory()->create(['is_active' => true]);
        BlogPost::factory()->draft()->create([
            'blog_category_id' => $category->id,
            'slug'             => 'taslak-yazi',
        ]);

        $this->getJson('/api/v1/blog/posts/taslak-yazi')->assertNotFound();
    }

    public function test_blog_categories_are_listed(): void
    {
        BlogCategory::factory()->count(2)->create(['is_active' => true]);
        BlogCategory::factory()->create(['is_active' => false]);

        $this->getJson('/api/v1/blog/categories')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['id', 'name', 'slug', 'posts_count']]]);
    }

    /**
     * Liste sorgusu kategori ve yazar ilişkilerini baştan yüklüyor; yüklemeseydi
     * her yazı için iki sorgu daha atılırdı (N+1).
     */
    public function test_the_post_list_does_not_run_a_query_per_row(): void
    {
        $category = BlogCategory::factory()->create(['is_active' => true]);
        BlogPost::factory()->count(10)->create(['blog_category_id' => $category->id]);

        $queries = 0;
        \Illuminate\Support\Facades\DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $this->getJson('/api/v1/blog/posts?per_page=10')->assertOk();

        // Sayım + yazılar + kategoriler + yazarlar + diller/ayarlar için birkaç
        // sorgu. On yazı için otuz sorguya çıkıyorsa eager loading kırılmıştır.
        $this->assertLessThan(15, $queries, "Blog listesi {$queries} sorgu attı — eager loading kırılmış olabilir.");
    }

    // ── Galeri ──

    public function test_gallery_items_can_be_filtered_by_category_and_type(): void
    {
        // Göçler dört hazır kategori kuruyor (genel, etkinlikler, ekip, mekan);
        // sınama kendi slug'ını kullanıyor ki onlarla çakışmasın.
        $category = GalleryCategory::factory()->create(['is_active' => true, 'slug' => 'api-sinamasi']);

        GalleryItem::factory()->count(2)->create([
            'gallery_category_id' => $category->id,
            'type'                => \App\Enums\GalleryType::Photo,
            'is_active'           => true,
        ]);
        GalleryItem::factory()->create([
            'gallery_category_id' => $category->id,
            'type'                => \App\Enums\GalleryType::Video,
            'is_active'           => true,
        ]);

        $this->getJson('/api/v1/gallery')->assertOk()->assertJsonPath('meta.total', 3);
        $this->getJson('/api/v1/gallery?type=photo')->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson('/api/v1/gallery?category=api-sinamasi')->assertOk()->assertJsonPath('meta.total', 3);

        $this->getJson('/api/v1/gallery?type=uydurma')
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->getJson('/api/v1/gallery?category=olmayan')->assertNotFound();
    }

    public function test_gallery_categories_list_only_the_active_ones(): void
    {
        GalleryCategory::factory()->create(['is_active' => true, 'slug' => 'gorunur-kategori']);
        GalleryCategory::factory()->create(['is_active' => false, 'slug' => 'gizli-kategori']);

        $slugs = collect(
            $this->getJson('/api/v1/gallery/categories')
                ->assertOk()
                ->assertJsonStructure(['data' => [['id', 'name', 'slug']]])
                ->json('data'),
        )->pluck('slug');

        $this->assertContains('gorunur-kategori', $slugs);
        $this->assertNotContains('gizli-kategori', $slugs);
    }

    // ── İletişim formu ──

    public function test_contact_form_stores_the_message(): void
    {
        $this->postJson('/api/v1/contact', [
            'name'    => 'Ozan Sonar',
            'email'   => 'gonderen@gmail.com',
            'subject' => 'Teklif talebi',
            'message' => 'Merhaba, fiyat listenizi rica ederim.',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.subject', 'Teklif talebi');

        $this->assertDatabaseHas('contact_messages', ['email' => 'gonderen@gmail.com']);

        // Makbuz yönetim alanlarını taşımamalı.
        $this->assertDatabaseCount('contact_messages', 1);
    }

    public function test_contact_form_receipt_hides_internal_fields(): void
    {
        $payload = $this->postJson('/api/v1/contact', [
            'name'    => 'Ozan Sonar',
            'email'   => 'gonderen@gmail.com',
            'subject' => 'Teklif talebi',
            'message' => 'Merhaba, fiyat listenizi rica ederim.',
        ])->assertCreated()->json('data');

        foreach (['ip_address', 'is_read', 'read_at', 'reply_text', 'replied_at'] as $internal) {
            $this->assertArrayNotHasKey($internal, $payload);
        }

        // IP yine de kaydediliyor — sadece dışarı verilmiyor.
        $this->assertNotNull(ContactMessage::first()?->ip_address);
    }

    public function test_contact_form_rejects_invalid_input(): void
    {
        $this->postJson('/api/v1/contact', [
            'name'    => 'A',
            'email'   => 'gecersiz',
            'subject' => '',
            'message' => 'kısa',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['name', 'email', 'subject', 'message']]);
    }

    public function test_contact_form_is_throttled(): void
    {
        $limit = (int) config('api.rate_limits.contact');

        for ($attempt = 0; $attempt < $limit; $attempt++) {
            $this->postJson('/api/v1/contact', [
                'name'    => 'Ozan Sonar',
                'email'   => 'gonderen@gmail.com',
                'subject' => 'Konu ' . $attempt,
                'message' => 'Merhaba, fiyat listenizi rica ederim.',
            ])->assertCreated();
        }

        $this->postJson('/api/v1/contact', [
            'name'    => 'Ozan Sonar',
            'email'   => 'gonderen@gmail.com',
            'subject' => 'Bir tane daha',
            'message' => 'Merhaba, fiyat listenizi rica ederim.',
        ])->assertStatus(429);
    }

    // ── Bakım modu ──

    public function test_public_endpoints_close_during_maintenance_but_auth_stays_open(): void
    {
        Setting::setValue('maintenance_mode', '1', 'appearance');

        $this->getJson('/api/v1/settings')
            ->assertStatus(503)
            ->assertJsonPath('success', false);

        // Giriş açık kalıyor: ön yüzde de /giris bakım modunda erişilebilir.
        $this->postJson('/api/v1/auth/login', ['email' => 'yok@ornek.com', 'password' => 'Gizli*12345'])
            ->assertStatus(401);
    }
}
