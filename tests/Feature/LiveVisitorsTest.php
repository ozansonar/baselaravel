<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PermissionKey;
use App\Models\PageView;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Who is on the site right now" is read from the same page_views rows the
 * tracker already writes — there is no separate presence table to drift.
 *
 * The thing worth guarding is the grouping: one row per visitor, not per hit,
 * with the page they are currently on.
 */
class LiveVisitorsTest extends TestCase
{
    use RefreshDatabase;

    private const CHROME = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36';

    private function hit(string $session, string $path, array $overrides = []): PageView
    {
        return PageView::create(array_merge([
            'url'         => 'http://localhost' . $path,
            'url_path'    => $path,
            'ip_address'  => '203.0.113.10',
            'user_agent'  => self::CHROME,
            'device_type' => 'desktop',
            'browser'     => 'Chrome',
            'os'          => 'OS X',
            'session_id'  => $session,
            'is_bot'      => false,
            'viewed_at'   => now(),
        ], $overrides));
    }

    public function test_each_visitor_appears_once_with_the_page_they_are_on(): void
    {
        $this->hit('sess-a', '/');
        $this->hit('sess-a', '/blog');
        $this->hit('sess-a', '/iletisim');
        $this->hit('sess-b', '/galeri');

        $visitors = app(AnalyticsService::class)->getActiveVisitors();

        $this->assertCount(2, $visitors, 'Ziyaretçi başına bir satır değil, tıklama başına satır dönüyor');

        $first = $visitors->firstWhere('session_id', 'sess-a');
        $this->assertSame('/iletisim', $first->url_path, 'Ziyaretçinin son sayfası değil başka bir sayfa gösteriliyor');
        $this->assertSame(3, $first->page_count);
    }

    public function test_a_visitor_outside_the_window_drops_off(): void
    {
        $this->hit('sess-eski', '/', ['viewed_at' => now()->subMinutes(30)]);
        $this->hit('sess-yeni', '/blog');

        $service = app(AnalyticsService::class);

        $this->assertSame(1, $service->getOnlineCount(5));
        $this->assertSame(2, $service->getOnlineCount(60), 'Aralık genişletilince eski ziyaretçi de sayılmalı');
    }

    /**
     * By the second click the referrer is this site itself, so the useful
     * answer is where the session started.
     */
    public function test_the_source_is_the_sessions_entry_referrer(): void
    {
        $this->hit('sess-a', '/galeri', ['referrer' => 'https://t.co/abc', 'referrer_domain' => 't.co']);
        $this->hit('sess-a', '/iletisim', ['referrer' => 'http://localhost/galeri', 'referrer_domain' => 'localhost']);

        $visitor = app(AnalyticsService::class)->getActiveVisitors()->firstWhere('session_id', 'sess-a');

        $this->assertSame('t.co', $visitor->referrer_domain);
        $this->assertSame('/galeri', $visitor->entry_path);
    }

    public function test_bots_are_excluded_unless_asked_for(): void
    {
        $this->hit('sess-insan', '/');
        $this->hit('sess-bot', '/', [
            'is_bot'      => true,
            'bot_name'    => 'Googlebot',
            'device_type' => 'bot',
        ]);

        $service = app(AnalyticsService::class);

        $this->assertSame(1, $service->getOnlineCount());
        $this->assertSame(2, $service->getOnlineCount(5, false));
        $this->assertCount(1, $service->getActiveVisitors());
        $this->assertCount(2, $service->getActiveVisitors(5, false));
    }

    public function test_a_signed_in_visitor_is_named(): void
    {
        $user = User::factory()->create(['first_name' => 'Ahmet', 'last_name' => 'Yılmaz']);

        $this->hit('sess-uye', '/hesabim', ['user_id' => $user->id]);

        $visitor = app(AnalyticsService::class)->getActiveVisitors()->first();

        $this->assertSame('Ahmet', $visitor->user['first_name']);
    }

    public function test_the_busiest_pages_are_counted_per_visitor(): void
    {
        // Two visitors on /blog, one on /galeri. The visitor with three hits on
        // /blog must not count as three people.
        $this->hit('sess-a', '/blog');
        $this->hit('sess-a', '/blog');
        $this->hit('sess-a', '/blog');
        $this->hit('sess-b', '/blog');
        $this->hit('sess-c', '/galeri');

        $pages = app(AnalyticsService::class)->getActivePages();

        $this->assertSame(['label' => '/blog', 'count' => 2], $pages[0]);
        $this->assertSame(['label' => '/galeri', 'count' => 1], $pages[1]);
    }

    public function test_the_feed_returns_only_what_is_new(): void
    {
        $first = $this->hit('sess-a', '/');
        $second = $this->hit('sess-a', '/blog');

        $service = app(AnalyticsService::class);

        $this->assertCount(2, $service->getLiveFeed(30));

        $fresh = $service->getLiveFeed(30, $first->id);
        $this->assertCount(1, $fresh, 'Akış, ekranda zaten olan kaydı tekrar döndürüyor');
        $this->assertSame($second->id, $fresh->first()->id);
    }

    public function test_no_visitors_returns_an_empty_collection(): void
    {
        $this->assertTrue(app(AnalyticsService::class)->getActiveVisitors()->isEmpty());
        $this->assertSame([], app(AnalyticsService::class)->getActivePages());
    }

    // ── Panel ──

    private function analyst(): User
    {
        $role = Role::create(['name' => 'Analist', 'slug' => 'analyst']);
        $permission = Permission::firstOrCreate(
            ['key' => PermissionKey::AnalyticsView->value],
            ['name' => PermissionKey::AnalyticsView->label(), 'group' => PermissionKey::AnalyticsView->group()],
        );
        $role->permissions()->syncWithoutDetaching([$permission->id]);

        $user = User::factory()->create();
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    public function test_the_live_screen_loads(): void
    {
        $this->actingAs($this->analyst())
            ->get(route('admin.analytics.live'))
            ->assertOk()
            ->assertSee('Canlı Ziyaretçiler');
    }

    /**
     * Ekranın kendisi de bir şey anlatmalı: veri neden gecikiyor, kim sayılmıyor.
     */
    public function test_the_live_screen_explains_how_the_numbers_are_collected(): void
    {
        $html = $this->actingAs($this->analyst())
            ->get(route('admin.analytics.live'))
            ->assertOk()
            ->getContent();

        // Yenileme aralığı hem metinde hem betiğin ayarında aynı olmalı.
        $this->assertStringContainsString('10 saniyede bir', $html);
        $this->assertStringContainsString('intervalMs: 10000', $html);

        // Panel hesaplarının sayılmaması bir kusur değil, kural — ekranda yazıyor.
        $this->assertStringContainsString('gezinmeler sayılmaz', $html);

        // Bağlantı koptuğunda uyaracak yer hazır.
        $this->assertStringContainsString('connectionAlert', $html);
    }

    /**
     * Panel hesaplarıyla gezinmek istatistiği bozmamalı: sayılar sitenin
     * ziyaretçisini anlatmalı, sitede çalışanı değil.
     */
    public function test_a_staff_visit_is_not_recorded(): void
    {
        $staffRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Yönetici']);
        $staff = User::factory()->create();
        $staff->roles()->syncWithoutDetaching([$staffRole->id]);

        $this->actingAs($staff)
            ->postJson(route('analytics.track'), [
                'url'  => 'http://localhost/tr/blog',
                'path' => '/tr/blog',
            ])
            ->assertStatus(202)
            ->assertJsonPath('skipped', 'staff');

        $this->assertSame(0, PageView::count(), 'Yönetici gezinmesi kayda girmemeli');
    }

    /**
     * Sıradan ziyaretçi kaydediliyor — dışlama yalnızca panel rollerine.
     */
    public function test_an_ordinary_visit_is_recorded(): void
    {
        $this->postJson(route('analytics.track'), [
            'url'  => 'http://localhost/tr/blog',
            'path' => '/tr/blog',
        ])->assertStatus(202);

        $this->assertSame(1, PageView::count());
    }

    public function test_the_polling_endpoint_returns_the_current_picture(): void
    {
        $this->hit('sess-a', '/blog');

        $this->actingAs($this->analyst())
            ->getJson(route('admin.analytics.live.data'))
            ->assertOk()
            ->assertJsonPath('online', 1)
            ->assertJsonPath('visitors.0.url_path', '/blog')
            ->assertJsonStructure(['online', 'window', 'server_time', 'visitors', 'pages', 'feed']);
    }

    /**
     * The window is a query parameter on an endpoint that gets polled, so it
     * must not accept an arbitrary range.
     */
    public function test_an_out_of_range_window_falls_back_to_the_default(): void
    {
        $this->actingAs($this->analyst())
            ->getJson(route('admin.analytics.live.data', ['window' => 100000]))
            ->assertOk()
            ->assertJsonPath('window', AnalyticsService::ACTIVE_WINDOW_MINUTES);
    }

    public function test_a_user_without_the_permission_cannot_watch(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.analytics.live'))->assertForbidden();
        $this->actingAs($user)->getJson(route('admin.analytics.live.data'))->assertForbidden();
    }

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $this->get(route('admin.analytics.live'))->assertRedirect();
    }
}
