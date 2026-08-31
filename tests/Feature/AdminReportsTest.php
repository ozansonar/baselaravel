<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ReportFrequency;
use App\Enums\ReportType;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\PageView;
use App\Models\ReportSchedule;
use App\Models\Role;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Rapor merkezi.
 *
 * İki şeyin sınavı: seçilen tarih aralığının bütün parçalara aynı şekilde
 * uygulanması (ekrandaki sayı ile inen dosyadaki sayı ayrışmamalı) ve
 * zamanlanmış gönderimin gerçekten gitmesi.
 */
class AdminReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
        $this->seed(\Database\Seeders\LanguageSeeder::class);
    }

    private function admin(): User
    {
        $user = User::create([
            'first_name' => 'Rapor',
            'last_name'  => 'Yonetici',
            'email'      => 'rapor@example.test',
            'password'   => 'sifre-123456',
            'is_active'  => true,
        ]);
        $user->markEmailAsVerified();
        $user->roles()->attach(Role::where('slug', 'admin')->firstOrFail()->id);

        return $user->fresh();
    }

    private function editor(): User
    {
        $user = User::create([
            'first_name' => 'Rapor',
            'last_name'  => 'Editor',
            'email'      => 'editor-rapor@example.test',
            'password'   => 'sifre-123456',
            'is_active'  => true,
        ]);
        $user->markEmailAsVerified();
        $user->roles()->attach(Role::where('slug', 'editor')->firstOrFail()->id);

        return $user->fresh();
    }

    private function visit(string $path, string $when): void
    {
        PageView::create([
            'url'        => 'http://localhost' . $path,
            'url_path'   => $path,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
            'session_id' => substr(md5($path . $when), 0, 40),
            'is_bot'     => false,
            'viewed_at'  => $when,
        ]);
    }

    // ── Ekran ──

    public function test_the_screen_opens_with_every_report_type(): void
    {
        $html = (string) $this->actingAs($this->admin())->get('/admin/raporlar')->assertOk()->getContent();

        foreach (ReportType::cases() as $type) {
            $this->assertStringContainsString($type->label(), $html);
        }
    }

    public function test_the_screen_is_closed_to_users_without_the_permission(): void
    {
        $user = User::create([
            'first_name' => 'Sade', 'last_name' => 'Kullanici',
            'email' => 'sade@example.test', 'password' => 'sifre-123456', 'is_active' => true,
        ]);
        $user->markEmailAsVerified();

        $this->actingAs($user)->get('/admin/raporlar')->assertForbidden();
    }

    /**
     * Aralık raporun her parçasına aynı şekilde uygulanmalı: kutudaki sayı,
     * grafikteki eğri ve tablodaki satırlar aynı günleri anlatmalı.
     */
    public function test_the_selected_range_applies_to_the_whole_report(): void
    {
        $this->visit('/tr/eski', now()->subDays(45)->toDateTimeString());
        $this->visit('/tr/yeni', now()->subDay()->toDateTimeString());

        $service = app(ReportService::class);
        [$from, $to] = $service->resolveRange('7');

        $report = $service->build(ReportType::Traffic, $from, $to);

        $paths = array_column($report['rows'], 0);

        $this->assertContains('/tr/yeni', $paths);
        $this->assertNotContains('/tr/eski', $paths);
        $this->assertCount(7, $report['series']['values']);
    }

    /**
     * Boş günler sıfırla dolduruluyor: yalnız veri olan günleri çizmek,
     * grafikte hiç yaşanmamış bir süreklilik gösterirdi.
     */
    public function test_days_without_data_are_still_on_the_curve(): void
    {
        $this->visit('/tr', now()->toDateTimeString());

        $service = app(ReportService::class);
        [$from, $to] = $service->resolveRange('30');

        $series = $service->build(ReportType::Traffic, $from, $to)['series'];

        $this->assertCount(30, $series['values']);
        $this->assertContains(0, $series['values']);
    }

    public function test_an_unknown_range_falls_back_instead_of_breaking(): void
    {
        [$from, $to] = app(ReportService::class)->resolveRange('uydurma');

        $this->assertSame(30, (int) $from->startOfDay()->diffInDays($to->startOfDay()) + 1);
    }

    public function test_the_report_can_be_searched(): void
    {
        $service = app(ReportService::class);

        $rows = [['Ana sayfa', '10'], ['İletişim', '4']];

        $this->assertCount(1, $service->filterRows($rows, 'iletişim'));
        $this->assertCount(2, $service->filterRows($rows, ''));
    }

    // ── Dışa aktarma ──

    public function test_the_report_downloads_as_excel(): void
    {
        $category = BlogCategory::create(['locale' => 'tr', 'name' => 'Genel', 'slug' => 'genel', 'is_active' => true]);
        BlogPost::create([
            'locale' => 'tr', 'blog_category_id' => $category->id, 'title' => 'Rapor yazısı',
            'slug' => 'rapor-yazisi', 'body' => 'Gövde', 'status' => 'published', 'published_at' => now(),
        ]);

        $response = $this->actingAs($this->admin())
            ->get('/admin/disa-aktar/reports/excel?type=content&range=30');

        $response->assertOk();
        $this->assertStringContainsString('spreadsheet', (string) $response->headers->get('content-type'));
    }

    public function test_the_download_is_refused_without_the_permission(): void
    {
        $user = User::create([
            'first_name' => 'Sade', 'last_name' => 'Kullanici',
            'email' => 'sade2@example.test', 'password' => 'sifre-123456', 'is_active' => true,
        ]);
        $user->markEmailAsVerified();

        $this->actingAs($user)->get('/admin/disa-aktar/reports/excel?type=content')->assertForbidden();
    }

    // ── Önizleme ──

    public function test_the_preview_returns_the_first_rows(): void
    {
        $this->visit('/tr', now()->toDateTimeString());

        $this->actingAs($this->admin())
            ->getJson('/admin/raporlar/onizleme/traffic?range=30')
            ->assertOk()
            ->assertJsonStructure(['title', 'range', 'metrics', 'columns', 'rows', 'total']);
    }

    public function test_an_unknown_report_type_is_not_found(): void
    {
        $this->actingAs($this->admin())
            ->getJson('/admin/raporlar/onizleme/uydurma')
            ->assertNotFound();
    }

    // ── Zamanlanmış raporlar ──

    public function test_a_schedule_can_be_created(): void
    {
        $this->actingAs($this->admin())->post('/admin/raporlar/zamanlama', [
            'type'       => ReportType::Traffic->value,
            'frequency'  => ReportFrequency::Weekly->value,
            'range'      => '30',
            'format'     => 'excel',
            'recipients' => 'biri@example.test, ikinci@example.test',
            'is_active'  => '1',
        ])->assertRedirect(route('admin.reports.index'));

        $schedule = ReportSchedule::firstOrFail();

        $this->assertSame(['biri@example.test', 'ikinci@example.test'], $schedule->recipients);
        $this->assertTrue($schedule->is_active);
    }

    public function test_a_schedule_needs_at_least_one_recipient(): void
    {
        $this->actingAs($this->admin())->post('/admin/raporlar/zamanlama', [
            'type'       => ReportType::Traffic->value,
            'frequency'  => ReportFrequency::Daily->value,
            'range'      => '30',
            'format'     => 'excel',
            'recipients' => '',
        ])->assertSessionHasErrors('recipients');

        $this->assertSame(0, ReportSchedule::count());
    }

    /**
     * Rapor okuyabilen herkes düzenli gönderim tanımlayamamalı: dışarıya
     * düzenli veri gönderen bir iş, yöneticinin kararı.
     */
    public function test_an_editor_can_read_reports_but_cannot_schedule_them(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)->get('/admin/raporlar')->assertOk();

        $this->actingAs($editor)->post('/admin/raporlar/zamanlama', [
            'type'       => ReportType::Traffic->value,
            'frequency'  => ReportFrequency::Daily->value,
            'range'      => '30',
            'format'     => 'excel',
            'recipients' => 'biri@example.test',
        ])->assertForbidden();
    }

    public function test_running_a_schedule_queues_the_mail_with_the_report_attached(): void
    {
        Mail::fake();

        $schedule = ReportSchedule::create([
            'type'       => ReportType::Traffic->value,
            'frequency'  => ReportFrequency::Daily->value,
            'range'      => '30',
            'format'     => 'excel',
            'recipients' => ['alici@example.test'],
            'is_active'  => true,
        ]);

        $this->actingAs($this->admin())
            ->post('/admin/raporlar/zamanlama/' . $schedule->id . '/calistir')
            ->assertRedirect(route('admin.reports.index'));

        Mail::assertQueued(\App\Mail\ScheduledReportMail::class);

        $this->assertNotNull($schedule->fresh()?->last_run_at);
        $this->assertNull($schedule->fresh()?->last_error);
    }

    /**
     * Cron dakikada bir uğruyor; bu kontrol olmasaydı günlük rapor bin kez
     * giderdi.
     */
    public function test_a_schedule_does_not_run_twice_in_the_same_day(): void
    {
        ReportSchedule::create([
            'type'        => ReportType::Traffic->value,
            'frequency'   => ReportFrequency::Daily->value,
            'range'       => '30',
            'format'      => 'excel',
            'recipients'  => ['alici@example.test'],
            'is_active'   => true,
            'last_run_at' => now(),
        ]);

        $this->assertCount(0, app(\App\Services\ReportScheduleService::class)->due());
    }

    public function test_an_inactive_schedule_is_skipped(): void
    {
        ReportSchedule::create([
            'type'       => ReportType::Traffic->value,
            'frequency'  => ReportFrequency::Daily->value,
            'range'      => '30',
            'format'     => 'excel',
            'recipients' => ['alici@example.test'],
            'is_active'  => false,
        ]);

        $this->assertCount(0, app(\App\Services\ReportScheduleService::class)->due());
    }

    public function test_the_weekly_schedule_only_comes_due_on_monday(): void
    {
        $this->assertTrue(ReportFrequency::Weekly->dueOn(now()->startOfWeek()));
        $this->assertFalse(ReportFrequency::Weekly->dueOn(now()->startOfWeek()->addDay()));
        $this->assertTrue(ReportFrequency::Monthly->dueOn(now()->startOfMonth()));
        $this->assertFalse(ReportFrequency::Monthly->dueOn(now()->startOfMonth()->addDay()));
    }

    public function test_a_schedule_can_be_deleted(): void
    {
        $schedule = ReportSchedule::create([
            'type'       => ReportType::Traffic->value,
            'frequency'  => ReportFrequency::Daily->value,
            'range'      => '30',
            'format'     => 'excel',
            'recipients' => ['alici@example.test'],
            'is_active'  => true,
        ]);

        $this->actingAs($this->admin())
            ->delete('/admin/raporlar/zamanlama/' . $schedule->id)
            ->assertRedirect(route('admin.reports.index'));

        $this->assertSoftDeleted('report_schedules', ['id' => $schedule->id]);
    }
}
