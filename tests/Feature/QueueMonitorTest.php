<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\QueueMonitorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Kuyruk izleyici.
 *
 * `failed_jobs` projede tek bir yerde okunuyordu — `HealthCheckService` son 24
 * saatin sayısını alıyordu, o kadar. Listeleme, hatayı görme, yeniden deneme
 * ve silme yoktu. Bu proje için özellikle önemli: tüm mail gönderimi kuyruğa
 * giriyor, yani "doğrulama maili gelmedi" şikâyetinin cevabı
 * `failed_jobs.exception` alanında duruyordu ve o alana panelden bakmanın yolu
 * yoktu.
 */
class QueueMonitorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
        $this->seed(\Database\Seeders\LanguageSeeder::class);
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', $slug)->firstOrFail());

        return $user;
    }

    private function service(): QueueMonitorService
    {
        return app(QueueMonitorService::class);
    }

    /**
     * Kuyruğa gerçek bir iş koyar — `QueueRunner` bunu işleyebilmeli.
     */
    private function pushJob(?int $availableAt = null): void
    {
        DB::table('jobs')->insert([
            'queue'        => 'default',
            'payload'      => json_encode(['displayName' => 'App\\Mail\\TestMail', 'job' => 'test']),
            'attempts'     => 0,
            'reserved_at'  => null,
            'available_at' => $availableAt ?? time(),
            'created_at'   => time(),
        ]);
    }

    private function pushFailedJob(array $attributes = []): string
    {
        $uuid = (string) Str::uuid();

        DB::table('failed_jobs')->insert(array_merge([
            'uuid'       => $uuid,
            'connection' => 'database',
            'queue'      => 'default',
            'payload'    => json_encode([
                'displayName' => 'Illuminate\\Mail\\SendQueuedMailable',
                'attempts'    => 3,
                // Uzunluk doğru: unserialize sınıfı bulamayınca hata değil
                // __PHP_Incomplete_Class döner, çerçevenin komutu bunu
                // zaten eliyor. Gerçek bir yük de böyle görünür.
                'data'        => ['command' => 'O:24:"App\\Mail\\VerifyEmailMail":0:{}'],
            ]),
            'exception'  => "Swift_TransportException: Connection refused\n#0 /app/vendor/foo.php(12)",
            'failed_at'  => now(),
        ], $attributes));

        return $uuid;
    }

    // ── Yetki ────────────────────────────────────────────────────

    public function test_only_an_admin_reaches_the_screen(): void
    {
        $this->actingAs($this->userWithRole('admin'))->get('/admin/kuyruk')->assertOk();
        $this->actingAs($this->userWithRole('editor'))->get('/admin/kuyruk')->assertForbidden();
        $this->actingAs($this->userWithRole('moderator'))->get('/admin/kuyruk')->assertForbidden();
    }

    public function test_the_sidebar_link_is_hidden_from_an_editor(): void
    {
        $adminHtml = $this->actingAs($this->userWithRole('admin'))->get('/admin')->getContent();
        $editorHtml = $this->actingAs($this->userWithRole('editor'))->get('/admin')->getContent();

        $this->assertStringContainsString('/admin/kuyruk', $adminHtml);
        $this->assertStringNotContainsString('/admin/kuyruk', $editorHtml);
    }

    public function test_an_editor_cannot_retry_or_delete(): void
    {
        $uuid = $this->pushFailedJob();
        $editor = $this->userWithRole('editor');

        $this->actingAs($editor)->post("/admin/kuyruk/{$uuid}/yeniden-dene")->assertForbidden();
        $this->actingAs($editor)->delete("/admin/kuyruk/{$uuid}")->assertForbidden();
        $this->actingAs($editor)->delete('/admin/kuyruk/temizle')->assertForbidden();

        $this->assertDatabaseCount('failed_jobs', 1);
    }

    // ── Sayılar ──────────────────────────────────────────────────

    public function test_the_counts_describe_the_queue(): void
    {
        $this->pushJob();
        $this->pushJob();
        $this->pushFailedJob();
        $this->pushFailedJob(['failed_at' => now()->subDays(3)]);

        $stats = $this->service()->stats();

        $this->assertSame(2, $stats['pending']);
        $this->assertSame(1, $stats['failed_today']);
        $this->assertSame(2, $stats['failed_total']);
        $this->assertSame(0, $stats['oldest_minutes']);
    }

    public function test_an_empty_queue_has_no_oldest_job(): void
    {
        $this->assertNull($this->service()->stats()['oldest_minutes']);
        $this->assertFalse($this->service()->isStuck());
    }

    /**
     * Bekleyen iş sayısı tek başına normal; birikip **yaşlanması** cron'un
     * çalışmadığını söyleyen sinyal.
     */
    public function test_an_ageing_queue_is_reported_as_stuck(): void
    {
        $this->pushJob(availableAt: time() - 60 * (QueueMonitorService::STUCK_AFTER_MINUTES + 5));

        $this->assertTrue($this->service()->isStuck());

        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/kuyruk')
            ->assertOk()
            ->assertSee('Kuyruk ilerlemiyor');
    }

    public function test_a_fresh_queue_is_not_reported_as_stuck(): void
    {
        $this->pushJob();

        $this->assertFalse($this->service()->isStuck());
    }

    // ── Liste ────────────────────────────────────────────────────

    /**
     * Kuyruğa giren her mail `SendQueuedMailable` olarak görünüyor; asıl sınıf
     * yükün serileştirilmiş gövdesinde. "Hangi mail patladı" sorusu ancak
     * oradan cevaplanıyor.
     */
    public function test_the_list_names_the_mailable_rather_than_the_framework_wrapper(): void
    {
        $this->pushFailedJob();

        $job = $this->service()->paginate()->first();

        $this->assertSame('App\\Mail\\VerifyEmailMail', $job['job']);
        $this->assertSame(3, $job['attempts']);
        $this->assertStringContainsString('Connection refused', $job['error']);
    }

    public function test_an_unrecognisable_payload_falls_back_to_the_display_name(): void
    {
        $this->pushFailedJob([
            'payload' => json_encode(['displayName' => 'App\\Console\\SomeTask', 'data' => []]),
        ]);

        $this->assertSame('App\\Console\\SomeTask', $this->service()->paginate()->first()['job']);
    }

    /**
     * Liste yığın izinin yalnızca ilk satırını taşıyor; tamamı istendiğinde
     * ayrı bir istekle geliyor.
     */
    public function test_the_list_carries_only_the_first_line_of_the_error(): void
    {
        $this->pushFailedJob();

        $this->assertStringNotContainsString('#0 /app/vendor', $this->service()->paginate()->first()['error']);
    }

    public function test_the_detail_endpoint_returns_the_whole_stack(): void
    {
        $uuid = $this->pushFailedJob();

        $this->actingAs($this->userWithRole('admin'))
            ->getJson("/admin/kuyruk/{$uuid}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('job', 'App\\Mail\\VerifyEmailMail')
            ->assertJsonFragment(['queue' => 'default']);

        $body = $this->actingAs($this->userWithRole('admin'))
            ->getJson("/admin/kuyruk/{$uuid}")
            ->json('exception');

        $this->assertStringContainsString('#0 /app/vendor', (string) $body);
    }

    public function test_the_detail_endpoint_is_honest_about_a_missing_record(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->getJson('/admin/kuyruk/' . Str::uuid())
            ->assertNotFound();
    }

    public function test_the_search_narrows_the_list(): void
    {
        $this->pushFailedJob();
        $this->pushFailedJob(['exception' => 'RuntimeException: disk dolu']);

        $this->assertCount(1, $this->service()->paginate(25, ['search' => 'disk dolu'])->items());
        $this->assertCount(2, $this->service()->paginate(25, [])->items());
    }

    public function test_the_queue_filter_narrows_the_list(): void
    {
        $this->pushFailedJob();
        $this->pushFailedJob(['queue' => 'mails']);

        $this->assertSame(['default', 'mails'], $this->service()->queueOptions());
        $this->assertCount(1, $this->service()->paginate(25, ['queue' => 'mails'])->items());
    }

    // ── İşlemler ─────────────────────────────────────────────────

    public function test_retrying_puts_the_job_back_on_the_queue(): void
    {
        $uuid = $this->pushFailedJob();

        $this->actingAs($this->userWithRole('admin'))
            ->post("/admin/kuyruk/{$uuid}/yeniden-dene")
            ->assertRedirect();

        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $uuid]);
        $this->assertSame(1, DB::table('jobs')->count());
    }

    /**
     * Yükün içindeki nesne açılamıyorsa — iş sınıfı bir deploy'da kaldırılmış
     * olabilir — çerçevenin komutu patlıyor ve ekran 500 veriyordu. İşin geri
     * konması bundan daha önemli.
     */
    public function test_a_job_with_an_unreadable_payload_is_still_requeued(): void
    {
        $uuid = $this->pushFailedJob([
            'payload' => json_encode([
                'displayName' => 'App\\Jobs\\SilinmisIs',
                'attempts'    => 3,
                // Uzunluk kasten yanlış: unserialize burada hata veriyor.
                'data'        => ['command' => 'O:99:"App\\Jobs\\SilinmisIs":0:{}'],
            ]),
        ]);

        $this->actingAs($this->userWithRole('admin'))
            ->post("/admin/kuyruk/{$uuid}/yeniden-dene")
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $uuid]);
        $this->assertSame(1, DB::table('jobs')->count(), 'İş kuyruğa geri konmadı');
    }

    public function test_deleting_removes_only_that_record(): void
    {
        $doomed = $this->pushFailedJob();
        $this->pushFailedJob();

        $this->actingAs($this->userWithRole('admin'))
            ->delete("/admin/kuyruk/{$doomed}")
            ->assertRedirect();

        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $doomed]);
        $this->assertDatabaseCount('failed_jobs', 1);
    }

    public function test_flushing_clears_the_list(): void
    {
        $this->pushFailedJob();
        $this->pushFailedJob();

        $this->actingAs($this->userWithRole('admin'))
            ->delete('/admin/kuyruk/temizle')
            ->assertRedirect();

        $this->assertDatabaseCount('failed_jobs', 0);
    }

    /**
     * Kuyruğa dokunan her işlem denetim izine düşüyor: bir işin neden
     * kaybolduğu sonradan sorulacak ilk şey.
     */
    public function test_every_destructive_action_lands_in_the_audit_trail(): void
    {
        $retried = $this->pushFailedJob();
        $deleted = $this->pushFailedJob();
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->post("/admin/kuyruk/{$retried}/yeniden-dene");
        $this->actingAs($admin)->delete("/admin/kuyruk/{$deleted}");
        $this->pushFailedJob();
        $this->actingAs($admin)->delete('/admin/kuyruk/temizle');

        $labels = AuditLog::pluck('label')->all();

        $this->assertContains('Başarısız kuyruk işi yeniden denendi', $labels);
        $this->assertContains('Başarısız kuyruk işi silindi', $labels);
        $this->assertContains('Başarısız kuyruk listesi temizlendi', $labels);
    }

    /**
     * Ekran gerçek bir başarısızlıkla da dolmalı. Testlerin geri kalanı satırı
     * doğrudan yazıyor; bu, zincirin tamamının çalıştığını doğruluyor —
     * `failed_jobs` kaydı olmasaydı ekran üretimde hep boş kalırdı.
     */
    public function test_a_real_failure_shows_up_on_the_screen(): void
    {
        \Illuminate\Support\Facades\Queue::connection('database')
            ->push(new \Tests\Feature\PatlayanSinamaIsi());

        app(\App\Services\QueueRunner::class)->drain();

        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/kuyruk')
            ->assertOk()
            ->assertSee('PatlayanSinamaIsi')
            ->assertSee('sinama isi patladi');
    }

    public function test_the_screen_can_drain_the_queue_on_demand(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->post('/admin/kuyruk/calistir')
            ->assertRedirect()
            ->assertSessionHas('success');
    }
}
