<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\CustomRoute;
use App\Models\Language;
use App\Models\MailTemplate;
use App\Models\Redirect;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\RoleService;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Denetim izinin kapsamı.
 *
 * Altyapı baştan iyi yazılmıştı — tablo, indeksleri, saklama süresi
 * temizliği, panel ekranı, hassas alan maskesi hepsi yerindeydi. Ama gözlemci
 * **tek bir modele** bağlıydı: `Setting`. Yani "kim giriş yaptı", "kim hangi
 * rolün iznini değiştirdi", "kim kullanıcı sildi" sorularının hiçbirinin
 * cevabı yoktu; kurumsal bir denetimin ilk sorduğu şeyler de bunlar.
 */
class AuditTrailCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
    }

    private function labels(): array
    {
        return AuditLog::orderBy('id')->pluck('label')->all();
    }

    private function lastLog(): ?AuditLog
    {
        return AuditLog::latest('id')->first();
    }

    /**
     * Kapsam listesi tek yerde duruyor; buradaki her model için gerçekten bir
     * kayıt doğduğu doğrulanıyor, listenin varlığı değil.
     */
    public function test_every_critical_model_is_watched(): void
    {
        Setting::create(['key' => 'denetim_sinamasi', 'value' => '1']);
        Role::create(['name' => 'Denetçi', 'slug' => 'denetci']);
        Redirect::create(['old_url' => '/eski', 'new_url' => '/yeni', 'status_code' => 301, 'is_active' => true]);
        CustomRoute::create(['locale' => 'en', 'slug' => 'about', 'target_route' => 'contact', 'type' => 'render', 'is_active' => true]);
        Language::create(['code' => 'nl', 'name' => 'Felemenkçe', 'native_name' => 'Nederlands', 'is_active' => false]);
        User::factory()->create();

        $watched = AuditLog::whereNotNull('auditable_type')
            ->pluck('auditable_type')
            ->unique()
            ->map(static fn (string $class): string => class_basename($class))
            ->sort()
            ->values()
            ->all();

        foreach (['Setting', 'Role', 'Redirect', 'CustomRoute', 'Language', 'User'] as $model) {
            $this->assertContains($model, $watched, "{$model} denetim izinde görünmüyor");
        }
    }

    public function test_mail_templates_are_watched(): void
    {
        MailTemplate::query()->first()?->update(['subject' => 'Değişti']);

        $this->assertNotNull(
            AuditLog::where('auditable_type', MailTemplate::class)->first(),
            'Mail şablonu değişikliği izde yok',
        );
    }

    /**
     * İçerik modelleri bilinçli olarak dışarıda: her kaydetmede satır üretir
     * ve 90 günlük saklama süresiyle asıl aranan kayıt bulunamaz hâle gelir.
     */
    public function test_content_models_are_deliberately_left_out(): void
    {
        \App\Models\Page::factory()->create();

        $this->assertNull(
            AuditLog::where('auditable_type', \App\Models\Page::class)->first(),
            'İçerik modeli denetim izini dolduruyor',
        );
    }

    public function test_a_password_is_never_written_to_the_trail(): void
    {
        $user = User::factory()->create();
        $user->update(['password' => 'yeni-gizli-sifre']);

        $rows = AuditLog::where('auditable_type', User::class)->get();

        $this->assertNotEmpty($rows);

        foreach ($rows as $row) {
            $encoded = json_encode([$row->old_values, $row->new_values], JSON_UNESCAPED_UNICODE);

            $this->assertStringNotContainsString('yeni-gizli-sifre', (string) $encoded);
            $this->assertStringContainsString('[REDACTED]', (string) $encoded);
        }
    }

    public function test_the_label_names_the_user_rather_than_just_the_id(): void
    {
        User::factory()->create(['first_name' => 'Ayşe', 'last_name' => 'Yılmaz']);

        $this->assertStringContainsString('Ayşe Yılmaz', (string) $this->lastLog()?->label);
    }

    // ── Kimlik doğrulama olayları ────────────────────────────────

    public function test_a_successful_login_is_recorded(): void
    {
        $user = User::factory()->create();

        $this->post('/tr/giris', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect();

        $log = AuditLog::where('label', 'Giriş yapıldı')->first();

        $this->assertNotNull($log);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame($user->email, $log->new_values['e-posta'] ?? null);
    }

    public function test_a_failed_login_is_recorded(): void
    {
        User::factory()->create(['email' => 'kurban@example.com']);

        $this->post('/tr/giris', ['email' => 'kurban@example.com', 'password' => 'yanlis-sifre']);

        $log = AuditLog::where('label', 'Başarısız giriş denemesi')->first();

        $this->assertNotNull($log, 'Başarısız deneme izde yok — saldırı örüntüsü görünmez olur');
        $this->assertSame('kurban@example.com', $log->new_values['e-posta'] ?? null);
    }

    /**
     * `$event->credentials` şifreyi de taşıyor; dinleyici yalnızca adresi
     * alıyor ve maske son savunma olarak arkada duruyor.
     */
    public function test_a_failed_login_never_records_the_attempted_password(): void
    {
        User::factory()->create(['email' => 'kurban@example.com']);

        $this->post('/tr/giris', ['email' => 'kurban@example.com', 'password' => 'denenen-sifre']);

        $encoded = (string) json_encode(AuditLog::all()->toArray(), JSON_UNESCAPED_UNICODE);

        $this->assertStringNotContainsString('denenen-sifre', $encoded);
    }

    public function test_a_logout_is_recorded(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/tr/cikis')->assertRedirect();

        $log = AuditLog::where('label', 'Çıkış yapıldı')->first();

        $this->assertNotNull($log);
        $this->assertSame($user->id, $log->user_id);
    }

    public function test_asking_for_a_reset_link_is_recorded(): void
    {
        $user = User::factory()->create();

        $this->post('/tr/sifremi-unuttum', ['email' => $user->email]);

        $this->assertContains('Şifre sıfırlama bağlantısı istendi', $this->labels());
    }

    // ── Yetki değişiklikleri ─────────────────────────────────────

    /**
     * İzin matrisinin pivot tablosunun modeli yok, yani gözlemci onu göremez.
     * Panelin en keskin ekranı da burası.
     */
    public function test_changing_the_permission_matrix_is_recorded(): void
    {
        app(RoleService::class)->syncMatrix(['editor' => ['pages.manage']]);

        $log = AuditLog::where('label', 'Rol izinleri güncellendi')->first();

        $this->assertNotNull($log);
        $this->assertArrayHasKey('editor', (array) $log->new_values);
    }

    public function test_a_matrix_save_that_changes_nothing_is_not_recorded(): void
    {
        $service = app(RoleService::class);

        $service->syncMatrix(['editor' => ['pages.manage']]);
        AuditLog::query()->forceDelete();

        $service->syncMatrix(['editor' => ['pages.manage']]);

        $this->assertSame(
            0,
            AuditLog::where('label', 'Rol izinleri güncellendi')->count(),
            'Değişmeyen kayıt denetim izini dolduruyor',
        );
    }

    public function test_changing_a_users_roles_is_recorded(): void
    {
        $user = User::factory()->create();
        $editor = Role::where('slug', 'editor')->firstOrFail();

        app(RoleService::class)->syncUserRoles($user, [$editor->id]);

        $log = AuditLog::where('label', 'Kullanıcı rolleri değiştirildi')->first();

        $this->assertNotNull($log);
        $this->assertSame(['editor'], $log->new_values['eklenen'] ?? null);
    }

    // ── Sorgu kurucusundan giden toplu yollar ────────────────────

    /**
     * Toplu silme sorgu kurucusundan gidiyor ve model olayı doğurmuyor; elli
     * kullanıcının silindiği tek işlem izde hiç görünmezdi.
     */
    public function test_a_bulk_delete_is_recorded(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        app(UserService::class)->deleteMany([$first->id, $second->id]);

        $log = AuditLog::where('label', 'Kullanıcılar toplu silindi')->first();

        $this->assertNotNull($log);
        $this->assertSame(2, $log->new_values['adet'] ?? null);
    }

    public function test_a_bulk_restore_is_recorded(): void
    {
        $user = User::factory()->create();
        app(UserService::class)->deleteMany([$user->id]);

        app(UserService::class)->restoreMany([$user->id]);

        $this->assertContains('Kullanıcılar toplu geri yüklendi', $this->labels());
    }

    // ── Geri alma ve kalıcı silme ────────────────────────────────

    public function test_restoring_a_record_is_recorded(): void
    {
        $redirect = Redirect::create(['old_url' => '/a', 'new_url' => '/b', 'status_code' => 301, 'is_active' => true]);
        $redirect->delete();
        $redirect->restore();

        $events = AuditLog::where('auditable_type', Redirect::class)->pluck('event')->all();

        $this->assertContains(AuditEvent::Deleted, $events);
        $this->assertSame(
            AuditEvent::Updated,
            AuditLog::where('auditable_type', Redirect::class)->latest('id')->first()?->event,
        );
    }

    public function test_a_permanent_delete_is_recorded(): void
    {
        $redirect = Redirect::create(['old_url' => '/a', 'new_url' => '/b', 'status_code' => 301, 'is_active' => true]);
        $redirect->forceDelete();

        $last = AuditLog::where('auditable_type', Redirect::class)->latest('id')->first();

        $this->assertSame(AuditEvent::Deleted, $last?->event);
        $this->assertTrue(($last?->new_values['kalici'] ?? false) === true);
    }
}
