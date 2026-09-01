<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\NotificationPreference;
use App\Enums\PermissionKey;
use App\Enums\PushAudience;
use App\Enums\PushNotificationStatus;
use App\Models\Permission;
use App\Models\PushNotification;
use App\Models\PushToken;
use App\Models\Role;
use App\Models\User;
use App\Services\NotificationPreferenceService;
use App\Services\PushNotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConfiguresFcm;
use Tests\TestCase;

/**
 * Panelden gönderilen push duyuruları.
 *
 * İki taraf sınanıyor: ekranın kimin neyi yapabildiğine dair sınırları ve
 * gönderimin kime ulaştığı. İkincisi asıl mesele — duyuru, kapatmış birine
 * ulaşırsa tercih ekranı yalan söylemiş olur.
 */
class PushNotificationPanelTest extends TestCase
{
    use RefreshDatabase, ConfiguresFcm;

    protected function setUp(): void
    {
        parent::setUp();

        // Taşıyıcı açık kabul ediliyor: kapalıyken gönderim hiç denenmiyor ve
        // sınanacak bir yol kalmıyor.
        $this->configureFcm();
        $this->fakeFcm();
    }

    /**
     * @param array<int, PermissionKey> $permissions
     */
    private function userWith(array $permissions): User
    {
        $role = Role::create(['name' => 'Test Rolü', 'slug' => 'test-' . uniqid()]);

        $ids = [];
        foreach ($permissions as $key) {
            $ids[] = Permission::firstOrCreate(
                ['key' => $key->value],
                ['name' => $key->label(), 'group' => $key->group()],
            )->id;
        }

        $role->permissions()->syncWithoutDetaching($ids);

        $user = User::factory()->create();
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    private function sender(): User
    {
        return $this->userWith([
            PermissionKey::PushNotificationsView,
            PermissionKey::PushNotificationsSend,
            PermissionKey::PushNotificationsDelete,
        ]);
    }

    /* ==================== Yetki ==================== */

    public function test_yetkisiz_kullanici_listeyi_goremez(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.push-notifications.index'))
            ->assertForbidden();
    }

    public function test_goruntuleme_yetkisi_gonderme_yetkisi_vermez(): void
    {
        $user = $this->userWith([PermissionKey::PushNotificationsView]);

        $this->actingAs($user)
            ->get(route('admin.push-notifications.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('admin.push-notifications.create'))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('admin.push-notifications.store'), [
                'title'    => 'Duyuru',
                'body'     => 'Metin',
                'audience' => PushAudience::All->value,
            ])
            ->assertForbidden();
    }

    public function test_silme_ayri_bir_yetki(): void
    {
        $user = $this->userWith([PermissionKey::PushNotificationsView, PermissionKey::PushNotificationsSend]);
        $notification = PushNotification::factory()->create();

        $this->actingAs($user)
            ->delete(route('admin.push-notifications.destroy', $notification))
            ->assertForbidden();
    }

    /* ==================== Ekranlar ==================== */

    public function test_liste_ekrani_duyurulari_gosteriyor(): void
    {
        PushNotification::factory()->create(['title' => 'Bayram duyurusu']);

        $this->actingAs($this->sender())
            ->get(route('admin.push-notifications.index'))
            ->assertOk()
            ->assertSee('Bayram duyurusu');
    }

    public function test_form_ekrani_aciliyor(): void
    {
        $this->actingAs($this->sender())
            ->get(route('admin.push-notifications.create'))
            ->assertOk()
            ->assertSee('Yeni Push Duyurusu');
    }

    public function test_detay_ekrani_sayaclari_gosteriyor(): void
    {
        $notification = PushNotification::factory()->sent()->create(['title' => 'Sürüm çıktı']);

        $this->actingAs($this->sender())
            ->get(route('admin.push-notifications.show', $notification))
            ->assertOk()
            ->assertSee('Sürüm çıktı')
            ->assertSee('Gönderildi');
    }

    /* ==================== Kayıt ==================== */

    public function test_duyuru_siraya_aliniyor(): void
    {
        $sender = $this->sender();

        $this->actingAs($sender)
            ->post(route('admin.push-notifications.store'), [
                'title'    => 'Yeni sürüm yayında',
                'body'     => 'Uygulamanın yeni sürümü mağazada.',
                'link'     => '/blog/yeni-surum',
                'audience' => PushAudience::All->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('push_notifications', [
            'title'   => 'Yeni sürüm yayında',
            'link'    => '/blog/yeni-surum',
            'status'  => PushNotificationStatus::Queued->value,
            'user_id' => $sender->getKey(),
        ]);
    }

    public function test_baslik_ve_metin_zorunlu(): void
    {
        $this->actingAs($this->sender())
            ->post(route('admin.push-notifications.store'), [
                'audience' => PushAudience::All->value,
            ])
            ->assertSessionHasErrors(['title', 'body']);
    }

    public function test_uzunluk_tavani_sunucuda_da_gecerli(): void
    {
        $this->actingAs($this->sender())
            ->post(route('admin.push-notifications.store'), [
                'title'    => str_repeat('a', 121),
                'body'     => str_repeat('b', 501),
                'audience' => PushAudience::All->value,
            ])
            ->assertSessionHasErrors(['title', 'body']);
    }

    /**
     * Bağlantı açık yönlendirmeye dönüşemez: bildirimin götürdüğü yer,
     * yönlendirme hedefiyle aynı kuraldan geçiyor.
     */
    public function test_site_disi_baglanti_reddediliyor(): void
    {
        $this->actingAs($this->sender())
            ->post(route('admin.push-notifications.store'), [
                'title'    => 'Duyuru',
                'body'     => 'Metin',
                'link'     => 'https://kotu-site.test/tuzak',
                'audience' => PushAudience::All->value,
            ])
            ->assertSessionHasErrors('link');
    }

    public function test_rol_hedefi_secim_istiyor(): void
    {
        $this->actingAs($this->sender())
            ->post(route('admin.push-notifications.store'), [
                'title'    => 'Duyuru',
                'body'     => 'Metin',
                'audience' => PushAudience::Role->value,
            ])
            ->assertSessionHasErrors('audience_id');
    }

    public function test_olmayan_rol_reddediliyor(): void
    {
        $this->actingAs($this->sender())
            ->post(route('admin.push-notifications.store'), [
                'title'       => 'Duyuru',
                'body'        => 'Metin',
                'audience'    => PushAudience::Role->value,
                'audience_id' => 999999,
            ])
            ->assertSessionHasErrors('audience_id');
    }

    /**
     * Formda önce rol seçilip sonra "herkes"e dönülürse eski seçim kayda
     * geçmemeli; yoksa kayıt "herkese gitti" der ama hedefi bir rolü gösterir.
     */
    public function test_herkes_secildiginde_eski_hedef_temizleniyor(): void
    {
        $role = Role::create(['name' => 'Bayi', 'slug' => 'bayi-' . uniqid()]);

        $this->actingAs($this->sender())
            ->post(route('admin.push-notifications.store'), [
                'title'       => 'Duyuru',
                'body'        => 'Metin',
                'audience'    => PushAudience::All->value,
                'audience_id' => $role->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('push_notifications', [
            'audience'    => PushAudience::All->value,
            'audience_id' => null,
        ]);
    }

    /* ==================== İptal ==================== */

    public function test_siradaki_duyuru_iptal_edilebiliyor(): void
    {
        $notification = PushNotification::factory()->create();

        $this->actingAs($this->sender())
            ->from(route('admin.push-notifications.show', $notification))
            ->post(route('admin.push-notifications.cancel', $notification))
            ->assertRedirect();

        $this->assertSame(
            PushNotificationStatus::Cancelled,
            $notification->refresh()->status,
        );
    }

    public function test_baslamis_gonderim_iptal_edilemiyor(): void
    {
        $notification = PushNotification::factory()->sending()->create();

        $this->actingAs($this->sender())
            ->from(route('admin.push-notifications.show', $notification))
            ->post(route('admin.push-notifications.cancel', $notification))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(
            PushNotificationStatus::Sending,
            $notification->refresh()->status,
        );
    }

    /* ==================== Hedef büyüklüğü ==================== */

    public function test_hedef_boyutu_ucu_cihaz_sayisini_veriyor(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        PushToken::factory()->count(2)->create(['user_id' => $user->getKey()]);

        $this->actingAs($this->sender())
            ->postJson(route('admin.push-notifications.audience-size'), [
                'audience' => PushAudience::All->value,
            ])
            ->assertOk()
            ->assertJson(['count' => 2, 'pending' => false]);
    }

    public function test_kullanici_aramasi_iki_harften_kisa_terimi_bos_donuyor(): void
    {
        User::factory()->create(['first_name' => 'Zeynep', 'is_active' => true]);

        $this->actingAs($this->sender())
            ->getJson(route('admin.push-notifications.users.search', ['q' => 'Z']))
            ->assertOk()
            ->assertJson(['results' => []]);
    }

    public function test_kullanici_aramasi_ada_gore_buluyor(): void
    {
        $user = User::factory()->create(['first_name' => 'Zeynep', 'is_active' => true]);

        $this->actingAs($this->sender())
            ->getJson(route('admin.push-notifications.users.search', ['q' => 'Zeyn']))
            ->assertOk()
            ->assertJsonFragment(['id' => $user->getKey()]);
    }

    /* ==================== Gönderim ==================== */

    private function dispatcher(): PushNotificationDispatcher
    {
        return app(PushNotificationDispatcher::class);
    }

    public function test_gonderim_butun_cihazlara_ulasiyor(): void
    {
        $a = User::factory()->create(['is_active' => true]);
        $b = User::factory()->create(['is_active' => true]);
        PushToken::factory()->create(['user_id' => $a->getKey()]);
        PushToken::factory()->count(2)->create(['user_id' => $b->getKey()]);

        $notification = PushNotification::factory()->create();

        $this->dispatcher()->sendBatch($notification);

        $notification->refresh();

        $this->assertSame(3, $notification->total_devices);
        $this->assertSame(3, $notification->sent_count);
        $this->assertSame(PushNotificationStatus::Sent, $notification->status);
    }

    /**
     * Tercih ekranındaki anahtar gerçekten bir şey yapmalı.
     */
    public function test_duyuruyu_kapatmis_kullaniciya_gonderilmiyor(): void
    {
        $acik = User::factory()->create(['is_active' => true]);
        $kapali = User::factory()->create(['is_active' => true]);

        PushToken::factory()->create(['user_id' => $acik->getKey()]);
        PushToken::factory()->create(['user_id' => $kapali->getKey()]);

        app(NotificationPreferenceService::class)
            ->set($kapali, NotificationPreference::PushAnnouncements, false);

        $notification = PushNotification::factory()->create();

        $this->dispatcher()->sendBatch($notification);

        $this->assertSame(1, $notification->refresh()->total_devices);
    }

    public function test_pasif_hesaba_gonderilmiyor(): void
    {
        $pasif = User::factory()->create(['is_active' => false]);
        PushToken::factory()->create(['user_id' => $pasif->getKey()]);

        $notification = PushNotification::factory()->create();

        $this->dispatcher()->sendBatch($notification);

        $notification->refresh();

        $this->assertSame(0, $notification->total_devices);
        $this->assertSame(PushNotificationStatus::Failed, $notification->status);
        $this->assertNotNull($notification->last_error);
    }

    public function test_rol_hedefi_yalnizca_o_roldeki_cihazlara_gidiyor(): void
    {
        $role = Role::create(['name' => 'Bayi', 'slug' => 'bayi-' . uniqid()]);

        $bayi = User::factory()->create(['is_active' => true]);
        $bayi->roles()->syncWithoutDetaching([$role->id]);
        PushToken::factory()->create(['user_id' => $bayi->getKey()]);

        $digeri = User::factory()->create(['is_active' => true]);
        PushToken::factory()->create(['user_id' => $digeri->getKey()]);

        $notification = PushNotification::factory()->forRole($role->id)->create();

        $this->dispatcher()->sendBatch($notification);

        $this->assertSame(1, $notification->refresh()->total_devices);
    }

    public function test_tek_kullanici_hedefi_yalnizca_o_kisiye_gidiyor(): void
    {
        $hedef = User::factory()->create(['is_active' => true]);
        PushToken::factory()->count(2)->create(['user_id' => $hedef->getKey()]);

        $digeri = User::factory()->create(['is_active' => true]);
        PushToken::factory()->create(['user_id' => $digeri->getKey()]);

        $notification = PushNotification::factory()->forUser($hedef->getKey())->create();

        $this->dispatcher()->sendBatch($notification);

        $this->assertSame(2, $notification->refresh()->total_devices);
    }

    /**
     * Taşıyıcı kapalıyken kayıt sonsuza kadar "gönderiliyor" görünmemeli.
     */
    public function test_tasiyici_kapaliyken_cihazlar_atlaniyor(): void
    {
        config()->set('push.driver', 'null');

        $user = User::factory()->create(['is_active' => true]);
        PushToken::factory()->create(['user_id' => $user->getKey()]);

        $notification = PushNotification::factory()->create();

        $this->dispatcher()->sendBatch($notification);

        $notification->refresh();

        $this->assertSame(1, $notification->skipped_count);
        $this->assertSame(0, $notification->sent_count);
        $this->assertSame(PushNotificationStatus::Sent, $notification->status);
    }

    public function test_cron_turu_bekleyen_duyuruyu_isliyor(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        PushToken::factory()->create(['user_id' => $user->getKey()]);

        PushNotification::factory()->create();

        $sonuc = $this->dispatcher()->tick();

        $this->assertSame(1, $sonuc['processed']);
        $this->assertSame(1, $sonuc['sent']);
    }

    public function test_bekleyen_duyuru_yokken_cron_turu_bos_donuyor(): void
    {
        $this->assertSame(
            ['processed' => 0, 'sent' => 0, 'failed' => 0],
            $this->dispatcher()->tick(),
        );
    }

    public function test_iptal_edilen_duyuru_gonderilmiyor(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        PushToken::factory()->create(['user_id' => $user->getKey()]);

        PushNotification::factory()->create([
            'status' => PushNotificationStatus::Cancelled,
        ]);

        $this->assertSame(0, $this->dispatcher()->tick()['processed']);
    }

    public function test_konsol_komutu_calisiyor(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        PushToken::factory()->create(['user_id' => $user->getKey()]);

        PushNotification::factory()->create();

        $this->artisan('push:dispatch')->assertSuccessful();
    }

    /**
     * Hedefin adı silinse bile kayıt "kime gitti" sorusunu cevaplayabilmeli.
     */
    public function test_silinmis_rol_hedef_etiketinde_hala_okunabiliyor(): void
    {
        $role = Role::create(['name' => 'Kaldırılan Rol', 'slug' => 'kaldirilan-' . uniqid()]);
        $notification = PushNotification::factory()->forRole($role->id)->create();

        $role->delete();

        $this->assertSame('Kaldırılan Rol', $notification->refresh()->audienceLabel());
    }
}
