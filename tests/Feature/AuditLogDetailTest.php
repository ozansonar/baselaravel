<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tek bir aktivite kaydının detay ekranı.
 *
 * Ekranın asıl işi "ne değişti" sorusunu cevaplamak; ham JSON yedekte durur.
 * Gösterilecek küme olaya göre değişiyor, o yüzden dört olay türü de ayrı
 * sınanıyor.
 */
class AuditLogDetailTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seedAuthorization();

        $admin = User::create([
            'first_name' => 'Denetim',
            'last_name'  => 'Yöneticisi',
            'email'      => 'audit-detail@example.com',
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
            'auditable_id'   => 9,
            'label'          => 'Setting #9 güncellendi',
            'old_values'     => ['value' => null, 'key' => 'mail_logo'],
            'new_values'     => ['value' => 'logo.jpg'],
            'ip_address'     => '10.0.0.5',
            'url'            => 'https://example.com/admin/settings',
            'created_at'     => now(),
        ], $attributes));
    }

    /**
     * Değişiklik tablosu enum yerine metinle karşılaştırıldığı için hiç
     * görünmüyordu; bu test onu geri getirmenin bekçisi.
     */
    public function test_an_update_shows_what_changed(): void
    {
        $admin = $this->admin();
        $log = $this->log();

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.show', $log->id))
            ->assertOk()
            ->assertSee('Değişiklikler')
            ->assertSee('value')
            ->assertSee('logo.jpg');
    }

    /**
     * Boş bir değer ham JSON'da "null" görünüyordu; tabloda okunur olmalı.
     */
    public function test_empty_values_are_written_for_people(): void
    {
        $this->assertSame('—', AuditLog::formatValue(null));
        $this->assertSame('Evet', AuditLog::formatValue(true));
        $this->assertSame('Hayır', AuditLog::formatValue(false));
        $this->assertSame('(boş)', AuditLog::formatValue(''));
        $this->assertSame('{"a":1}', AuditLog::formatValue(['a' => 1]));
    }

    public function test_a_created_record_shows_the_values_it_was_born_with(): void
    {
        $admin = $this->admin();

        $log = $this->log([
            'event'      => AuditEvent::Created,
            'old_values' => null,
            'new_values' => ['key' => 'mail_logo', 'group' => 'mail'],
            'label'      => 'Setting #9 oluşturuldu',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.show', $log->id))
            ->assertOk()
            ->assertSee('Oluşturulan kayıt')
            ->assertSee('mail_logo');
    }

    public function test_a_deleted_record_shows_its_last_state(): void
    {
        $admin = $this->admin();

        $log = $this->log([
            'event'      => AuditEvent::Deleted,
            'old_values' => ['key' => 'silinen_ayar'],
            'new_values' => null,
            'label'      => 'Setting #9 silindi',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.show', $log->id))
            ->assertOk()
            ->assertSee('Silinen kayıt')
            ->assertSee('silinen_ayar');
    }

    /**
     * Model dışı olaylarda bağlam new_values'ta durur.
     */
    public function test_a_custom_event_shows_the_context_it_recorded(): void
    {
        $admin = $this->admin();

        $log = $this->log([
            'event'          => AuditEvent::Custom,
            'auditable_type' => null,
            'auditable_id'   => null,
            'old_values'     => null,
            'new_values'     => ['file' => 'backup-2026-08-27.zip'],
            'label'          => 'Sistem yedeklemesi alındı',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.show', $log->id))
            ->assertOk()
            ->assertSee('İşlem ayrıntısı')
            ->assertSee('backup-2026-08-27.zip')
            ->assertSee('Modelsiz işlem');
    }

    /**
     * Denetim kaydı tek satır değil, bir oturumun akışı okunarak takip edilir.
     */
    public function test_the_neighbouring_records_can_be_walked(): void
    {
        $admin = $this->admin();

        $older = $this->log(['created_at' => now()->subHour(), 'label' => 'Eski kayıt']);
        $middle = $this->log(['created_at' => now()->subMinutes(30), 'label' => 'Orta kayıt']);
        $newer = $this->log(['created_at' => now(), 'label' => 'Yeni kayıt']);

        $html = $this->actingAs($admin)
            ->get(route('admin.audit-logs.show', $middle->id))
            ->assertOk()
            ->getContent() ?: '';

        $this->assertStringContainsString(route('admin.audit-logs.show', $older->id), $html);
        $this->assertStringContainsString(route('admin.audit-logs.show', $newer->id), $html);
    }

    public function test_the_newest_record_has_no_next_link(): void
    {
        $admin = $this->admin();

        $this->log(['created_at' => now()->subHour()]);
        $newest = $this->log(['created_at' => now()]);

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.show', $newest->id))
            ->assertOk()
            ->assertSee('Önceki')
            ->assertDontSee('Sonraki');
    }

    /**
     * Detaydan listeye dönerken süzgeç hazır gelmeli: "bu IP'den başka neler
     * yapıldı" sorusu tek tıkla cevaplansın.
     */
    public function test_the_record_offers_ready_made_filters(): void
    {
        $admin = $this->admin();

        $log = $this->log(['user_id' => $admin->id, 'ip_address' => '203.0.113.9']);

        $html = $this->actingAs($admin)
            ->get(route('admin.audit-logs.show', $log->id))
            ->assertOk()
            ->getContent() ?: '';

        $this->assertStringContainsString('user_id=' . $admin->id, $html);
        $this->assertStringContainsString('ip=203.0.113.9', $html);
        $this->assertStringContainsString('event=updated', $html);
    }

    public function test_a_role_without_the_permission_cannot_open_a_record(): void
    {
        $this->seedAuthorization();

        $log = $this->log();

        $moderator = User::create([
            'first_name' => 'Moderatör',
            'last_name'  => 'Kullanıcı',
            'email'      => 'audit-detail-moderator@example.com',
            'password'   => 'password',
            'is_active'  => true,
        ]);
        $moderator->roles()->attach(Role::where('slug', 'moderator')->firstOrFail());

        $this->actingAs($moderator)
            ->get(route('admin.audit-logs.show', $log->id))
            ->assertForbidden();
    }
}
