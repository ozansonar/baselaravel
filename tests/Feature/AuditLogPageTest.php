<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\Page;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Aktivite log ekranı — süzgeçler ve özetler.
 *
 * Denetim kaydına bakan kişi genelde tek bir soruyla gelir: "şu kaydı kim
 * değiştirdi?" Süzgeçlerin işi bu soruyu birkaç tıkla cevaplamak, o yüzden
 * hepsi ayrı ayrı sınanıyor.
 */
class AuditLogPageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seedAuthorization();

        $admin = User::create([
            'first_name' => 'Denetim',
            'last_name'  => 'Yöneticisi',
            'email'      => 'audit-admin@example.com',
            'password'   => 'password',
            'is_active'  => true,
        ]);

        $admin->roles()->attach(Role::where('slug', 'admin')->firstOrFail());

        return $admin;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function log(array $attributes = []): AuditLog
    {
        return AuditLog::create(array_merge([
            'user_id'        => null,
            'event'          => AuditEvent::Updated,
            'auditable_type' => Setting::class,
            'auditable_id'   => 1,
            'label'          => 'Ayar güncellendi',
            'old_values'     => ['site_title' => 'Eski'],
            'new_values'     => ['site_title' => 'Yeni'],
            'ip_address'     => '10.0.0.1',
            'url'            => 'https://example.com/admin/ayarlar',
            'created_at'     => now(),
        ], $attributes));
    }

    private function service(): AuditLogService
    {
        return app(AuditLogService::class);
    }

    public function test_the_page_summarises_what_is_recorded(): void
    {
        $admin = $this->admin();

        $this->log();
        $this->log(['created_at' => now()->subDays(3)]);
        $this->log(['event' => AuditEvent::Deleted, 'created_at' => now()->subDays(30)]);

        $stats = $this->actingAs($admin)
            ->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->viewData('stats');

        $this->assertSame(3, $stats['total']);
        $this->assertSame(1, $stats['today']);
        $this->assertSame(2, $stats['week']);
        $this->assertSame(1, $stats['deletions']);
    }

    public function test_the_event_tab_narrows_the_list(): void
    {
        $admin = $this->admin();

        $this->log(['event' => AuditEvent::Deleted, 'label' => 'Sayfa silindi']);
        $this->log(['event' => AuditEvent::Created, 'label' => 'Sayfa oluşturuldu']);

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index', ['event' => 'deleted']))
            ->assertOk()
            ->assertSee('Sayfa silindi')
            ->assertDontSee('Sayfa oluşturuldu');
    }

    /**
     * Zamanlanmış görevlerin yazdığı satırların kullanıcısı yoktur; "Sistem"
     * seçeneği tam olarak bunları getirmeli.
     */
    public function test_the_system_option_finds_the_entries_without_a_user(): void
    {
        $admin = $this->admin();

        $this->log(['user_id' => null, 'label' => 'Sistem yedeklemesi alındı']);
        $this->log(['user_id' => $admin->id, 'label' => 'Elle yapılan değişiklik']);

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index', ['user_id' => '0']))
            ->assertOk()
            ->assertSee('Sistem yedeklemesi alındı')
            ->assertDontSee('Elle yapılan değişiklik');

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index', ['user_id' => (string) $admin->id]))
            ->assertOk()
            ->assertSee('Elle yapılan değişiklik')
            ->assertDontSee('Sistem yedeklemesi alındı');
    }

    public function test_the_record_type_filter_uses_the_stored_class(): void
    {
        $admin = $this->admin();

        $this->log(['auditable_type' => Setting::class, 'label' => 'Ayar kaydı']);
        $this->log(['auditable_type' => Page::class, 'label' => 'Sayfa kaydı']);

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index', ['model' => Page::class]))
            ->assertOk()
            ->assertSee('Sayfa kaydı')
            ->assertDontSee('Ayar kaydı');
    }

    /**
     * Bitiş tarihi o günün tamamını kapsamalı: 14:00'te yapılan bir işlem, o
     * güne kadar süzülen listede görünmeli.
     */
    public function test_the_date_range_covers_the_whole_end_day(): void
    {
        $admin = $this->admin();

        $day = now()->subDays(2);

        $this->log(['created_at' => $day->copy()->setTime(14, 0), 'label' => 'O günkü işlem']);
        $this->log(['created_at' => $day->copy()->addDays(3), 'label' => 'Sonraki işlem']);

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index', [
                'from' => $day->toDateString(),
                'to'   => $day->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('O günkü işlem')
            ->assertDontSee('Sonraki işlem');
    }

    public function test_the_search_covers_the_label_and_the_ip(): void
    {
        $admin = $this->admin();

        $this->log(['label' => 'Sifre degistirildi', 'ip_address' => '10.0.0.5']);
        $this->log(['label' => 'Ayar guncellendi', 'ip_address' => '192.168.1.7']);

        // Küçük/büyük harf ayrımı olmadan bulmalı.
        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index', ['q' => 'sifre']))
            ->assertOk()
            ->assertSee('Sifre degistirildi')
            ->assertDontSee('Ayar guncellendi');

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index', ['q' => '192.168.1.7']))
            ->assertOk()
            ->assertSee('Ayar guncellendi')
            ->assertDontSee('Sifre degistirildi');
    }

    /**
     * Arama kutusuna yazılan % harfi joker değil, harf sayılmalı.
     */
    public function test_a_wildcard_in_the_search_box_is_taken_literally(): void
    {
        $admin = $this->admin();

        $this->log(['label' => 'Kapasite %90 doldu']);
        $this->log(['label' => 'Sıradan kayıt']);

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index', ['q' => '%90']))
            ->assertOk()
            ->assertSee('Kapasite %90 doldu')
            ->assertDontSee('Sıradan kayıt');
    }

    public function test_the_page_size_is_limited_to_the_offered_values(): void
    {
        $admin = $this->admin();

        $this->log();

        $response = $this->actingAs($admin)
            ->get(route('admin.audit-logs.index', ['per_page' => 5000]))
            ->assertOk();

        $this->assertSame(50, $response->viewData('perPage'));
        $this->assertSame(50, $response->viewData('logs')->perPage());
    }

    public function test_the_summary_ranks_the_busiest_users(): void
    {
        $admin = $this->admin();

        $this->log(['user_id' => $admin->id]);
        $this->log(['user_id' => $admin->id]);
        $this->log(['user_id' => null]);

        $actors = $this->service()->topActors();

        $this->assertSame('Denetim Yöneticisi', $actors[0]['name']);
        $this->assertSame(2, $actors[0]['count']);
        $this->assertSame(100, $actors[0]['percent']);
        $this->assertSame('Sistem', $actors[1]['name']);
    }

    public function test_the_type_filter_lists_only_types_that_exist(): void
    {
        $this->admin();

        $this->log(['auditable_type' => Setting::class]);
        $this->log(['auditable_type' => Setting::class]);

        $options = $this->service()->modelOptions();

        $this->assertSame(['Setting' => 2], array_map(
            static fn (array $option): int => $option['count'],
            array_combine(
                array_column($options, 'label'),
                $options,
            ),
        ));
    }

    public function test_the_ip_filter_narrows_the_list(): void
    {
        $admin = $this->admin();

        $this->log(['ip_address' => '10.0.0.5', 'label' => 'Ofisten yapıldı']);
        $this->log(['ip_address' => '203.0.113.9', 'label' => 'Disaridan yapildi']);

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index', ['ip' => '203.0.113.9']))
            ->assertOk()
            ->assertSee('Disaridan yapildi')
            ->assertDontSee('Ofisten yapıldı');
    }

    /**
     * IP süzgeci elle yazılmıyor: seçenekler kayıtlarda geçen adreslerden,
     * çok kullanılan başta olacak şekilde kuruluyor.
     */
    public function test_the_ip_options_come_from_the_records(): void
    {
        $this->admin();

        $this->log(['ip_address' => '10.0.0.5']);
        $this->log(['ip_address' => '10.0.0.5']);
        $this->log(['ip_address' => '203.0.113.9']);
        $this->log(['ip_address' => null]);

        $this->assertSame(
            ['10.0.0.5' => 2, '203.0.113.9' => 1],
            $this->service()->ipOptions(),
        );
    }

    /**
     * Sayfalama panelin kendi bileşeniyle çiziliyor; Laravel'in varsayılan
     * görünümü panele yabancı düşüyor ve çeviri anahtarlarını ham gösteriyor.
     */
    public function test_the_pager_is_the_panels_own(): void
    {
        $admin = $this->admin();

        foreach (range(1, 30) as $i) {
            $this->log(['label' => "Kayıt {$i}"]);
        }

        $html = $this->actingAs($admin)
            ->get(route('admin.audit-logs.index', ['per_page' => 25]))
            ->assertOk()
            ->getContent() ?: '';

        $this->assertStringContainsString('cl-page-btn', $html);
        $this->assertStringNotContainsString('pagination.previous', $html);
        $this->assertStringContainsString('arası gösteriliyor', $html);
    }

    /**
     * Denetim kaydı herkese açık değil: izni olmayan rol listeyi göremez.
     */
    public function test_a_role_without_the_permission_cannot_see_the_log(): void
    {
        $this->seedAuthorization();

        $moderator = User::create([
            'first_name' => 'Moderatör',
            'last_name'  => 'Kullanıcı',
            'email'      => 'audit-moderator@example.com',
            'password'   => 'password',
            'is_active'  => true,
        ]);
        $moderator->roles()->attach(Role::where('slug', 'moderator')->firstOrFail());

        $this->actingAs($moderator)
            ->get(route('admin.audit-logs.index'))
            ->assertForbidden();
    }

    /**
     * Ekranda yazan saklama süresi ile temizlik görevinin sildiği süre aynı
     * kaynaktan gelmeli.
     */
    public function test_the_retention_the_screen_shows_is_the_one_the_cleanup_uses(): void
    {
        $schedule = file_get_contents(base_path('routes/console.php')) ?: '';

        $this->assertStringContainsString('AuditLogService::RETENTION_DAYS', $schedule);
        $this->assertSame(90, AuditLogService::RETENTION_DAYS);
    }
}
