<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\ContactMessage;
use App\Models\Page;
use App\Models\Role;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Gösterge panosundaki sayılar.
 *
 * Sayılar beş dakika önbellekte duruyordu ve hiçbir yol onu temizlemiyordu:
 * yönetici iletişim mesajını okuyor, panoda "okunmamış" sayısı beş dakika
 * daha eski değeri yazıyordu. Kartların az önce yapılan işle çelişmemesi
 * gerekiyor.
 */
final class DashboardStatsTest extends TestCase
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

    /** Önbelleği doldurup okunan değeri döndürür. */
    private function stats(): array
    {
        return app(DashboardService::class)->getStats();
    }

    public function test_reading_a_message_drops_the_unread_counter(): void
    {
        ContactMessage::factory()->count(2)->create(['is_read' => false]);

        $this->assertSame(2, $this->stats()['unread_messages']);

        ContactMessage::first()->update(['is_read' => true]);

        $this->assertSame(1, $this->stats()['unread_messages'], 'Mesaj okununca pano sayısı düşmedi');
    }

    public function test_a_new_message_raises_the_unread_counter(): void
    {
        $this->assertSame(0, $this->stats()['unread_messages']);

        ContactMessage::factory()->create(['is_read' => false]);

        $this->assertSame(1, $this->stats()['unread_messages']);
    }

    public function test_a_new_post_raises_the_post_counter(): void
    {
        $this->assertSame(0, $this->stats()['total_posts']);

        BlogPost::factory()->create();

        $this->assertSame(1, $this->stats()['total_posts']);
    }

    public function test_deleting_a_page_drops_the_page_counter(): void
    {
        $page = Page::factory()->create();

        $this->assertSame(1, $this->stats()['total_pages']);

        $page->delete();

        $this->assertSame(0, $this->stats()['total_pages']);
    }

    public function test_creating_a_user_raises_the_user_counter(): void
    {
        $before = $this->stats()['total_users'];

        User::factory()->create();

        $this->assertSame($before + 1, $this->stats()['total_users']);
    }

    /**
     * Toplu silme sorgu kurucusundan gidiyor ve model olayı doğurmuyor;
     * gözlemci oraya yetişemediği için o yol önbelleği kendisi düşürmeli.
     */
    public function test_bulk_deleting_users_drops_the_user_counter(): void
    {
        $silinecek = User::factory()->count(2)->create();

        $before = $this->stats()['total_users'];

        app(UserService::class)->deleteMany($silinecek->pluck('id')->all());

        $this->assertSame($before - 2, $this->stats()['total_users'], 'Toplu silmeden sonra pano sayısı eski kaldı');
    }

    public function test_bulk_restoring_users_raises_the_user_counter_again(): void
    {
        $silinecek = User::factory()->count(2)->create();
        $ids = $silinecek->pluck('id')->all();

        app(UserService::class)->deleteMany($ids);
        $before = $this->stats()['total_users'];

        app(UserService::class)->restoreMany($ids);

        $this->assertSame($before + 2, $this->stats()['total_users']);
    }

    /** Panonun kendisi de aynı sayıyı göstermeli. */
    public function test_the_dashboard_screen_shows_the_fresh_numbers(): void
    {
        ContactMessage::factory()->create(['is_read' => false]);

        $this->get(route('admin.dashboard'))->assertOk();

        ContactMessage::first()->update(['is_read' => true]);

        $html = (string) $this->get(route('admin.dashboard'))->assertOk()->getContent();

        $this->assertSame(0, $this->stats()['unread_messages']);
        $this->assertIsString($html);
    }

    /**
     * Sayfa ve içerik çeviri grubu taşıyor; toplu silme model örnekleri
     * üzerinden yürüdüğü için gözlemci olayı yakalıyor.
     */
    public function test_bulk_deleting_pages_drops_the_page_counter(): void
    {
        $ids = collect(['bir', 'iki'])->map(fn (string $slug): int => Page::create([
            'locale' => 'tr', 'lang_group_id' => (string) Str::uuid(),
            'title' => $slug, 'slug' => $slug, 'content' => 'x',
        ])->id)->all();

        $this->assertSame(2, $this->stats()['total_pages']);

        app(\App\Services\PageService::class)->deleteMany($ids);

        $this->assertSame(0, $this->stats()['total_pages']);
    }

    public function test_bulk_deleting_posts_drops_the_post_counter(): void
    {
        $category = BlogCategory::create([
            'locale' => 'tr', 'lang_group_id' => (string) Str::uuid(),
            'name' => 'Genel', 'slug' => 'genel',
        ]);

        $ids = collect(['bir', 'iki'])->map(fn (string $slug): int => BlogPost::create([
            'locale' => 'tr', 'lang_group_id' => (string) Str::uuid(),
            'blog_category_id' => $category->id,
            'title' => $slug, 'slug' => $slug, 'body' => 'x',
            'status' => \App\Enums\ContentStatus::Draft,
        ])->id)->all();

        $this->assertSame(2, $this->stats()['total_posts']);

        app(\App\Services\BlogService::class)->deleteMany($ids);

        $this->assertSame(0, $this->stats()['total_posts']);
    }
}
