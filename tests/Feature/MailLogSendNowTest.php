<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MailLogStatus;
use App\Mail\TestMail;
use App\Models\MailLog;
use App\Models\Role;
use App\Models\User;
use App\Services\MailService;
use App\Services\QueueRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Beklemedeki maili elle gönderme.
 *
 * Beklemede olmak "kuyrukta sırası gelmedi" demek. Doğru davranış kuyruğu
 * çalıştırmak: mail kendi işiyle gider ve çift gönderim olmaz. İş kaybolmuşsa
 * kayıt sonsuza kadar beklemede kalmasın diye gövde doğrudan gönderilir.
 */
class MailLogSendNowTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seedAuthorization();

        $admin = User::create([
            'first_name' => 'Mail',
            'last_name'  => 'Yöneticisi',
            'email'      => 'mail-admin@example.com',
            'password'   => 'password',
            'is_active'  => true,
        ]);

        $admin->roles()->attach(Role::where('slug', 'admin')->firstOrFail());

        return $admin;
    }

    private function pendingLog(array $attributes = []): MailLog
    {
        return MailLog::create(array_merge([
            'to'      => 'alici@example.com',
            'subject' => 'Bekleyen mail',
            'body'    => '<p>Merhaba</p>',
            'status'  => MailLogStatus::Pending,
        ], $attributes));
    }

    /**
     * Kuyrukta işi kalmamış bekleyen kayıt: gövde doğrudan gider ve aynı kayıt
     * kapanır — yeni bir "[Yeniden]" satırı açılmaz.
     */
    public function test_a_pending_mail_with_no_job_left_is_sent_straight_away(): void
    {
        $log = $this->pendingLog();

        $this->actingAs($this->admin())
            ->postJson(route('admin.mail-logs.send-now', $log))
            ->assertOk()
            ->assertJson(['success' => true]);

        $log->refresh();

        $this->assertSame(MailLogStatus::Sent, $log->status);
        $this->assertNotNull($log->sent_at);
        $this->assertSame(1, MailLog::query()->count(), 'Elle gönderim yeni bir log satırı açmamalı');
    }

    /**
     * Kuyrukta hâlâ iş varsa mailin sırası gelmemiş olabilir; doğrudan
     * göndermek çift gönderim riski taşır, o yüzden yapılmaz.
     */
    public function test_it_does_not_send_directly_while_the_queue_still_has_work(): void
    {
        $log = $this->pendingLog();

        // Kuyrukta duran, bu maile ait olmayan bir iş.
        DB::table('jobs')->insert([
            'queue'        => 'default',
            'payload'      => json_encode(['displayName' => 'Baska\\Is', 'job' => 'x', 'data' => []]),
            'attempts'     => 0,
            'reserved_at'  => null,
            // Henüz vakti gelmemiş olsun ki drain onu almasın.
            'available_at' => time() + 600,
            'created_at'   => time(),
        ]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.mail-logs.send-now', $log))
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertSame(MailLogStatus::Pending, $log->fresh()->status);
    }

    public function test_a_mail_that_is_not_pending_cannot_be_sent_now(): void
    {
        $log = $this->pendingLog(['status' => MailLogStatus::Sent, 'sent_at' => now()]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.mail-logs.send-now', $log))
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Bu mail zaten işlenmiş, beklemede değil.']);
    }

    /**
     * Gövdesi kaydedilmemiş bir mail elle gönderilemez; kullanıcı sebebini
     * öğrenmeli.
     */
    public function test_a_mail_without_a_stored_body_reports_why_it_cannot_be_sent(): void
    {
        $log = $this->pendingLog(['body' => null]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.mail-logs.send-now', $log))
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Mailin içeriği kayıtlı değil, elle gönderilemez.']);

        $this->assertSame(MailLogStatus::Pending, $log->fresh()->status);
    }

    public function test_a_role_without_the_resend_permission_cannot_send(): void
    {
        $this->seedAuthorization();

        $log = $this->pendingLog();

        $moderator = User::create([
            'first_name' => 'Moderatör',
            'last_name'  => 'Kullanıcı',
            'email'      => 'mail-moderator@example.com',
            'password'   => 'password',
            'is_active'  => true,
        ]);
        $moderator->roles()->attach(Role::where('slug', 'moderator')->firstOrFail());

        $this->actingAs($moderator)
            ->postJson(route('admin.mail-logs.send-now', $log))
            ->assertForbidden();

        $this->assertSame(MailLogStatus::Pending, $log->fresh()->status);
    }

    /**
     * Gönderim patlarsa kayıt beklemede kalmamalı: hata sebebiyle birlikte
     * başarısıza düşmeli.
     */
    public function test_a_failing_send_marks_the_log_failed(): void
    {
        $log = $this->pendingLog();

        Mail::shouldReceive('purge')->andReturnNull();
        Mail::shouldReceive('html')->andThrow(new \RuntimeException('SMTP kapalı'));

        try {
            app(MailService::class)->sendPendingNow($log);
            $this->fail('Gönderim hatası yukarı taşınmalıydı');
        } catch (\Throwable $e) {
            $this->assertSame('SMTP kapalı', $e->getMessage());
        }

        $log->refresh();

        $this->assertSame(MailLogStatus::Failed, $log->status);
        $this->assertSame('SMTP kapalı', $log->error_message);
    }

    /**
     * Kuyruk çalıştırıcı hem zamanlanmış görevin hem de bu düğmenin kullandığı
     * tek yol: kuyruğa düşen mail gerçekten gidiyor mu?
     */
    public function test_the_queue_runner_delivers_a_queued_mail(): void
    {
        config(['queue.default' => 'database']);

        app(MailService::class)->queue('alici@example.com', new TestMail('Kuyruk testi', 'Gövde'));

        $this->assertSame(1, DB::table('jobs')->count(), 'Mail kuyruğa düşmedi');
        $this->assertSame(1, MailLog::query()->where('status', MailLogStatus::Pending->value)->count());

        $result = app(QueueRunner::class)->drain(maxJobs: 5, maxSeconds: 5);

        $this->assertSame(1, $result['processed']);
        $this->assertSame(0, $result['remaining']);
        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame(MailLogStatus::Sent, MailLog::query()->latest('id')->first()->status);
    }
}
