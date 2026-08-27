<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PermissionKey;
use App\Models\PageView;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Ziyaret kaydı ekranı: yüz binlerce satırın içinden aranan kaydı bulmak.
 *
 * Buradaki asıl iş süzgeç ve sayfalama. Süzgeç sessizce yanlış çalışırsa ekran
 * yine dolu görünür — kullanıcı eksik veriye bakarak karar verir.
 */
class VisitLogTest extends TestCase
{
    use RefreshDatabase;

    private function analyst(): User
    {
        $role = Role::create(['name' => 'Analist', 'slug' => 'analist-' . uniqid()]);

        $permission = Permission::firstOrCreate(
            ['key' => PermissionKey::AnalyticsView->value],
            ['name' => PermissionKey::AnalyticsView->label(), 'group' => PermissionKey::AnalyticsView->group()],
        );

        $role->permissions()->syncWithoutDetaching([$permission->id]);

        $user = User::factory()->create();
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function visit(array $overrides = []): PageView
    {
        return PageView::create(array_merge([
            'url'         => 'http://localhost/tr',
            'url_path'    => '/tr',
            'ip_address'  => '203.0.113.10',
            'user_agent'  => 'Mozilla/5.0',
            'device_type' => 'desktop',
            'browser'     => 'Chrome',
            'os'          => 'Windows',
            'session_id'  => 'oturum-varsayilan',
            'is_bot'      => false,
            'viewed_at'   => now(),
        ], $overrides));
    }

    // ── Süzgeçler ──

    public function test_the_list_can_be_filtered_by_traffic_and_device(): void
    {
        $this->visit(['url_path' => '/insan-masaustu']);
        $this->visit(['url_path' => '/insan-mobil', 'device_type' => 'mobile']);
        $this->visit(['url_path' => '/bot', 'device_type' => 'bot', 'is_bot' => true, 'bot_name' => 'Googlebot']);

        $sadeceInsan = $this->actingAs($this->analyst())
            ->get(route('admin.analytics.visits', ['is_bot' => '0']))
            ->assertOk();

        $this->assertSame(2, $sadeceInsan->viewData('visits')->total());

        $sadeceMobil = $this->actingAs($this->analyst())
            ->get(route('admin.analytics.visits', ['device_type' => 'mobile']))
            ->assertOk();

        $this->assertSame(
            ['/insan-mobil'],
            $sadeceMobil->viewData('visits')->pluck('url_path')->all(),
        );
    }

    public function test_the_list_can_be_filtered_by_visitor_type(): void
    {
        $user = User::factory()->create();
        $this->visit(['url_path' => '/uye', 'user_id' => $user->id]);
        $this->visit(['url_path' => '/misafir']);

        $uye = $this->actingAs($this->analyst())
            ->get(route('admin.analytics.visits', ['visitor' => 'member']))
            ->viewData('visits');

        $misafir = $this->actingAs($this->analyst())
            ->get(route('admin.analytics.visits', ['visitor' => 'guest']))
            ->viewData('visits');

        $this->assertSame(['/uye'], $uye->pluck('url_path')->all());
        $this->assertSame(['/misafir'], $misafir->pluck('url_path')->all());
    }

    /**
     * "Doğrudan" bir alan adı değil, kaynağı olmayan ziyaretin adı.
     */
    public function test_direct_traffic_can_be_separated_from_referrals(): void
    {
        $this->visit(['url_path' => '/dogrudan']);
        $this->visit(['url_path' => '/googledan', 'referrer_domain' => 'google.com']);

        $dogrudan = $this->actingAs($this->analyst())
            ->get(route('admin.analytics.visits', ['referrer' => 'direct']))
            ->viewData('visits');

        $google = $this->actingAs($this->analyst())
            ->get(route('admin.analytics.visits', ['referrer' => 'google.com']))
            ->viewData('visits');

        $this->assertSame(['/dogrudan'], $dogrudan->pluck('url_path')->all());
        $this->assertSame(['/googledan'], $google->pluck('url_path')->all());
    }

    /**
     * "Şu adres ne gezdi" ve "bu oturum nereye gitti" bu ekranda sorulur;
     * arama yalnızca sayfa yolunu kapsasa iki soru da cevapsız kalırdı.
     */
    public function test_the_search_also_matches_the_address_and_the_session(): void
    {
        $this->visit(['url_path' => '/aranan-sayfa']);
        $this->visit(['url_path' => '/baska', 'ip_address' => '198.51.100.7']);
        $this->visit(['url_path' => '/ucuncu', 'session_id' => 'abcdef1234567890']);

        $arama = fn (string $terim): array => $this->actingAs($this->analyst())
            ->get(route('admin.analytics.visits', ['url' => $terim]))
            ->viewData('visits')
            ->pluck('url_path')
            ->all();

        $this->assertSame(['/aranan-sayfa'], $arama('aranan'));
        $this->assertSame(['/baska'], $arama('198.51.100.7'));
        $this->assertSame(['/ucuncu'], $arama('abcdef123'));
    }

    /**
     * Joker karakter düz metin sayılmalı; yoksa "%" yazan biri tüm listeyi
     * getirir ve süzgeç yaptığını sanır.
     */
    public function test_a_wildcard_in_the_search_is_taken_literally(): void
    {
        $this->visit(['url_path' => '/normal']);
        $this->visit(['url_path' => '/yuzde%isaretli']);

        $sonuc = $this->actingAs($this->analyst())
            ->get(route('admin.analytics.visits', ['url' => '%']))
            ->viewData('visits');

        $this->assertSame(['/yuzde%isaretli'], $sonuc->pluck('url_path')->all());
    }

    /**
     * "Sadece insan" süzgecinin değeri "0" — boş dizeyle karıştırılırsa ekran
     * süzgeç kapalıymış gibi davranır ve sıfırlama yolu görünmez.
     */
    public function test_the_human_only_filter_counts_as_an_open_filter(): void
    {
        $this->visit();

        $this->assertTrue(
            $this->actingAs($this->analyst())
                ->get(route('admin.analytics.visits', ['is_bot' => '0']))
                ->viewData('filtered'),
        );

        $this->assertFalse(
            $this->actingAs($this->analyst())
                ->get(route('admin.analytics.visits'))
                ->viewData('filtered'),
        );
    }

    // ── Sayfalama ve sıralama ──

    public function test_the_page_size_can_be_chosen_within_limits(): void
    {
        foreach (range(1, 30) as $i) {
            $this->visit(['url_path' => '/sayfa-' . $i, 'viewed_at' => now()->subMinutes($i)]);
        }

        $secilen = $this->actingAs($this->analyst())
            ->get(route('admin.analytics.visits', ['per_page' => 25]))
            ->viewData('visits');

        $this->assertSame(25, $secilen->perPage());
        $this->assertSame(30, $secilen->total());

        // İzin verilmeyen bir değer varsayılana düşmeli.
        $this->assertSame(
            50,
            $this->actingAs($this->analyst())
                ->get(route('admin.analytics.visits', ['per_page' => 5000]))
                ->viewData('visits')
                ->perPage(),
        );
    }

    public function test_the_list_can_be_sorted_oldest_first(): void
    {
        $this->visit(['url_path' => '/eski', 'viewed_at' => now()->subDay()]);
        $this->visit(['url_path' => '/yeni', 'viewed_at' => now()]);

        $yeniOnce = $this->actingAs($this->analyst())
            ->get(route('admin.analytics.visits'))
            ->viewData('visits');

        $eskiOnce = $this->actingAs($this->analyst())
            ->get(route('admin.analytics.visits', ['sort' => 'oldest']))
            ->viewData('visits');

        $this->assertSame('/yeni', $yeniOnce->first()->url_path);
        $this->assertSame('/eski', $eskiOnce->first()->url_path);
    }

    /**
     * Uydurulmuş bir sıralama sütun adı olarak sorguya girmemeli.
     */
    public function test_an_unknown_sort_is_ignored(): void
    {
        $this->visit();

        $this->actingAs($this->analyst())
            ->get(route('admin.analytics.visits', ['sort' => 'viewed_at); drop table page_views;--']))
            ->assertOk();

        $this->assertSame(1, PageView::count());
    }

    /**
     * Süzgeçler sayfa bağlantılarında kalmalı; ikinci sayfada süzgeç düşerse
     * kullanıcı farkında olmadan başka bir listeye bakar.
     */
    public function test_the_filters_survive_in_the_pagination_links(): void
    {
        foreach (range(1, 30) as $i) {
            $this->visit(['url_path' => '/mobil-' . $i, 'device_type' => 'mobile', 'viewed_at' => now()->subMinutes($i)]);
        }

        $sayfalayici = $this->actingAs($this->analyst())
            ->get(route('admin.analytics.visits', ['device_type' => 'mobile', 'per_page' => 25]))
            ->viewData('visits');

        $this->assertTrue($sayfalayici->hasPages());
        $this->assertStringContainsString('device_type=mobile', $sayfalayici->nextPageUrl() ?? '');
        $this->assertStringContainsString('per_page=25', $sayfalayici->nextPageUrl() ?? '');
    }

    // ── Ekran ──

    /**
     * Üye adı sütunda görünüyor; ilişki önden yüklenmezse her satır kendi
     * sorgusunu açar ve elli satırlık sayfa elli sorgu demek olur.
     */
    public function test_listing_visits_does_not_query_per_row(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 10) as $i) {
            $this->visit(['url_path' => '/uye-' . $i, 'user_id' => $user->id, 'viewed_at' => now()->subMinutes($i)]);
        }

        $analyst = $this->analyst();

        DB::enableQueryLog();
        $this->actingAs($analyst)->get(route('admin.analytics.visits'))->assertOk();
        $sorgular = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(25, $sorgular, 'Satır başına sorgu açılıyor: ilişki önden yüklenmemiş');
    }

    public function test_the_screen_says_that_staff_visits_are_not_recorded(): void
    {
        $this->actingAs($this->analyst())
            ->get(route('admin.analytics.visits'))
            ->assertOk()
            ->assertSee('gezinmeler kaydedilmez', false);
    }

    // ── Analitik paneli ──

    /**
     * Grafik kütüphanesi projede duruyor; CDN'den çekmek dış bir servise
     * bağımlılık demek — erişilemediğinde grafikler sessizce boş kalır.
     */
    public function test_the_dashboard_loads_the_chart_library_from_the_project(): void
    {
        $html = $this->actingAs($this->analyst())
            ->get(route('admin.analytics.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('assets/vendor/chartjs/chart.umd.min.js', $html);
        $this->assertStringNotContainsString('cdn.jsdelivr.net', $html);
    }

    /**
     * Panel tabloları tarayıcıda süzülüp sayfalanıyor: veri bir sayfadan uzun
     * gelmeli, yoksa sayfalamanın süzeceği bir şey olmaz.
     */
    public function test_the_dashboard_tables_carry_more_than_one_page_of_data(): void
    {
        foreach (range(1, 60) as $i) {
            $this->visit([
                'url_path'  => '/sayfa-' . ($i % 20),
                'viewed_at' => now()->subMinutes($i),
            ]);
        }

        $response = $this->actingAs($this->analyst())
            ->get(route('admin.analytics.index'))
            ->assertOk();

        $this->assertGreaterThan(10, $response->viewData('recentVisits')->count());
        $this->assertGreaterThan(10, count($response->viewData('topPages')));

        $html = $response->getContent();
        $this->assertStringContainsString('recentPager', $html);
        $this->assertStringContainsString('topPagesPager', $html);
        $this->assertStringContainsString('recentDevice', $html);
    }

    /**
     * Panelin son ziyaret listesi de üye adını gösteriyor; ilişki önden
     * yüklenmezse yüz satırlık liste yüz sorgu açar.
     */
    public function test_the_dashboard_does_not_query_per_recent_visit(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 15) as $i) {
            $this->visit(['url_path' => '/uye-' . $i, 'user_id' => $user->id, 'viewed_at' => now()->subMinutes($i)]);
        }

        $analyst = $this->analyst();

        DB::enableQueryLog();
        $this->actingAs($analyst)->get(route('admin.analytics.index'))->assertOk();
        $sorgular = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(40, $sorgular, 'Son ziyaret listesi satır başına sorgu açıyor');
    }

    public function test_a_user_without_the_permission_cannot_see_the_log(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.analytics.visits'))
            ->assertForbidden();
    }
}
