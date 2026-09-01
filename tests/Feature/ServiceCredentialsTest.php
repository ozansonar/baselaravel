<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\ServiceCredentialResolver;
use App\Support\ServiceCredentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Üçüncü taraf anahtarları panelden yönetiliyor.
 *
 * Google, Apple ve Firebase anahtarları yalnız `.env`'de duruyordu: yeni bir
 * anahtar girmek sunucuya bağlanıp dosya düzenlemek, sonra önbelleği düşürmek
 * demekti. Artık panelden giriliyor ve **kaydedildiği anda** geçerli oluyor.
 *
 * Sıra bozulmamalı: panel → .env → config varsayılanı. Panelde boş bırakılan
 * bir alan `.env`'i ezmemeli, yoksa bugüne kadar `.env`'e yazmış her kurulum
 * güncellemeyle birlikte kırılırdı.
 */
final class ServiceCredentialsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seedAuthorization();

        $admin = User::firstOrCreate(
            ['email' => 'svc-admin@example.com'],
            ['first_name' => 'Servis', 'last_name' => 'Yöneticisi', 'password' => 'password', 'is_active' => true],
        );

        $admin->roles()->syncWithoutDetaching(Role::where('slug', 'admin')->firstOrFail());

        return $admin;
    }

    private function resolve(): void
    {
        Setting::clearSettingsCache();
        app(ServiceCredentialResolver::class)->apply();
    }

    // ── Çözümleme sırası ──

    public function test_a_panel_value_overrides_the_env_value(): void
    {
        config(['services.google.client_ids' => 'env-den-gelen']);

        Setting::setValue('google_client_ids', 'panelden-gelen', 'services');
        $this->resolve();

        $this->assertSame('panelden-gelen', config('services.google.client_ids'));
    }

    public function test_an_empty_panel_value_leaves_env_alone(): void
    {
        config(['services.apple.client_ids' => 'env-den-gelen']);

        $this->resolve();

        $this->assertSame('env-den-gelen', config('services.apple.client_ids'));
    }

    /**
     * Açık/kapalı düğmeleri her servis için aynı şeyi yazmıyor: push sürücüsü
     * mantıksal değil sürücü adı bekliyor.
     */
    public function test_a_toggle_writes_the_value_the_config_expects(): void
    {
        Setting::setValue('push_driver', '1', 'services');
        $this->resolve();
        $this->assertSame('fcm', config('push.driver'));

        Setting::setValue('push_driver', '0', 'services');
        $this->resolve();
        $this->assertSame('null', config('push.driver'));
    }

    /**
     * Bazı ayarları servisler doğrudan okuyor; onlar için config yolu yok ve
     * çözümleyici uydurma bir yol yazmamalı.
     */
    public function test_a_field_without_a_config_path_is_skipped(): void
    {
        Setting::setValue('recaptcha_enabled', '1', 'services');
        $this->resolve();

        $this->assertNull(config('services.recaptcha.enabled'));
        $this->assertSame('1', Setting::getValue('recaptcha_enabled'));
    }

    /**
     * Veritabanı yokken uygulama yine ayağa kalkmalı: taze bir klon, göç
     * öncesi. O durumda .env zaten geçerli.
     */
    public function test_it_survives_without_a_database(): void
    {
        config(['services.google.client_ids' => 'env-den-gelen']);
        Setting::clearSettingsCache();

        DB::shouldReceive('connection')->andThrow(new \RuntimeException('veritabanı yok'));

        app(ServiceCredentialResolver::class)->apply();

        $this->assertSame('env-den-gelen', config('services.google.client_ids'));
    }

    // ── Şifreleme ──

    public function test_a_secret_is_encrypted_at_rest(): void
    {
        Setting::setValue('recaptcha_secret_key', 'cok-gizli-anahtar', 'services', 'password');

        $stored = (string) DB::table('settings')->where('key', 'recaptcha_secret_key')->value('value');

        $this->assertNotSame('cok-gizli-anahtar', $stored, 'Gizli anahtar düz metin duruyor.');
        $this->assertStringNotContainsString('cok-gizli-anahtar', $stored);

        Setting::clearSettingsCache();
        $this->assertSame('cok-gizli-anahtar', Setting::getValue('recaptcha_secret_key'));
    }

    public function test_a_non_secret_is_stored_as_written(): void
    {
        Setting::setValue('google_client_ids', 'acik-deger', 'services');

        $this->assertSame(
            'acik-deger',
            DB::table('settings')->where('key', 'google_client_ids')->value('value'),
        );
    }

    /**
     * APP_KEY değişmiş bir kurulumda tek bir ayar bütün siteyi düşürmemeli.
     */
    public function test_an_undecryptable_secret_does_not_break_the_app(): void
    {
        Setting::setValue('recaptcha_secret_key', 'gizli', 'services', 'password');

        DB::table('settings')
            ->where('key', 'recaptcha_secret_key')
            ->update(['value' => 'bu-gecerli-bir-sifreli-metin-degil']);

        Setting::clearSettingsCache();

        $this->assertNull(Setting::getValue('recaptcha_secret_key'));
    }

    // ── Panel ──

    public function test_the_screen_lists_every_registered_field(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('admin.service-credentials.index'))
            ->assertOk();

        foreach (array_keys(ServiceCredentials::fields()) as $key) {
            $response->assertSee('credentials[' . $key . ']', false);
        }
    }

    /**
     * Her alanın yanında anahtarın nereden alınacağı yazmalı — kayıt defteri
     * bunu zorunlu tutuyor, ekran da basıyor.
     */
    public function test_every_field_carries_a_help_text(): void
    {
        foreach (ServiceCredentials::fields() as $key => $field) {
            $this->assertNotSame('', trim($field['help']), "{$key} için rehber metni yok.");
            $this->assertGreaterThan(40, mb_strlen($field['help']), "{$key} rehber metni fazla kısa.");
        }
    }

    public function test_saving_takes_effect_immediately(): void
    {
        config(['services.google.client_ids' => 'env-den-gelen']);

        $this->actingAs($this->admin())
            ->put(route('admin.service-credentials.update'), [
                'credentials' => ['google_client_ids' => 'yeni-istemci-kimligi'],
            ])
            ->assertRedirect();

        // Kaydetmek önbelleği düşürüyor; bir sonraki istekte yeni değer geçerli.
        $this->resolve();

        $this->assertSame('yeni-istemci-kimligi', config('services.google.client_ids'));
    }

    /**
     * Gizli alan ekrana bir daha basılmamalı.
     */
    public function test_a_secret_is_never_rendered_back(): void
    {
        Setting::setValue('recaptcha_secret_key', 'ekranda-gorunmemeli', 'services', 'password');

        $this->actingAs($this->admin())
            ->get(route('admin.service-credentials.index'))
            ->assertOk()
            ->assertDontSee('ekranda-gorunmemeli');
    }

    /**
     * Gizli alanı boş bırakıp kaydetmek onu silmemeli: ekranda zaten boş
     * görünüyor, "kaydet" demek "sil" anlamına gelmemeli.
     */
    public function test_leaving_a_secret_blank_keeps_the_stored_value(): void
    {
        Setting::setValue('recaptcha_secret_key', 'kayitli-anahtar', 'services', 'password');

        $this->actingAs($this->admin())
            ->put(route('admin.service-credentials.update'), [
                'credentials' => ['recaptcha_secret_key' => ''],
            ])
            ->assertRedirect();

        Setting::clearSettingsCache();

        $this->assertSame('kayitli-anahtar', Setting::getValue('recaptcha_secret_key'));
    }

    /**
     * Açık bir alanı boşaltmak satırı silmeli — boş dizgeyle kaydetmemeli.
     * Boş satır .env yedeğini ezerdi.
     */
    public function test_clearing_a_visible_field_falls_back_to_env(): void
    {
        Setting::setValue('google_client_ids', 'panelden', 'services');

        $this->actingAs($this->admin())
            ->put(route('admin.service-credentials.update'), [
                'credentials' => ['google_client_ids' => ''],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('settings', ['key' => 'google_client_ids']);

        config(['services.google.client_ids' => 'env-den-gelen']);
        $this->resolve();

        $this->assertSame('env-den-gelen', config('services.google.client_ids'));
    }

    public function test_an_unknown_key_cannot_be_written(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.service-credentials.update'), [
                'credentials' => ['app_key' => 'ele-gecirme'],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('settings', ['key' => 'app_key']);
    }

    // ── "Bu alan nereden besleniyor" rozeti ──

    /**
     * Rozet `env()` çağırarak hesaplanamaz: `config:cache` sonrası —üretimin
     * varsayılan durumu— env() null döner ve rozet asla ".env" demezdi.
     * Cevabı, config'i ezmeden hemen önce gördüğü değerle çözümleyici veriyor.
     */
    public function test_the_badge_reports_a_value_that_comes_from_env(): void
    {
        config(['services.google.client_ids' => 'env-den-gelen']);
        $this->resolve();

        $this->actingAs($this->admin())
            ->get(route('admin.service-credentials.index'))
            ->assertOk()
            ->assertSee('svc-badge--env', false);
    }

    public function test_the_badge_reports_a_value_that_comes_from_the_panel(): void
    {
        Setting::setValue('google_client_ids', 'panelden', 'services');
        $this->resolve();

        $this->actingAs($this->admin())
            ->get(route('admin.service-credentials.index'))
            ->assertOk()
            ->assertSee('svc-badge--panel', false);
    }

    /**
     * Çözümleyici paylaşılmazsa ekran boş bir kayıt görür ve rozet hep "boş"
     * der. Bu sınav tekilliği tutuyor.
     */
    public function test_the_resolver_is_shared(): void
    {
        $this->assertSame(
            app(ServiceCredentialResolver::class),
            app(ServiceCredentialResolver::class),
        );
    }

    // ── Sızıntı ──

    /**
     * Servis anahtarları API'nin genel ayar ucundan dışarı çıkmamalı.
     */
    public function test_secrets_never_reach_the_public_api(): void
    {
        Setting::setValue('recaptcha_secret_key', 'sizmamali', 'services', 'password');
        Setting::setValue('fcm_service_account', '{"private_key":"sizmamali-2"}', 'services', 'password');

        $body = $this->getJson('/api/v1/settings')->assertOk()->getContent();

        $this->assertStringNotContainsString('sizmamali', (string) $body);
        $this->assertStringNotContainsString('fcm_service_account', (string) $body);
    }
}
