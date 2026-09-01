<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Language;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\SettingService;
use App\Support\TranslatableSettings;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Metin taşıyan ayarlar ziyaretçinin dilinde.
 *
 * settings tablosu anahtar başına tek satır tutuyordu. Ayarların çoğu için bu
 * doğru — renk, telefon, anahtar, açık/kapalı dile göre değişmiyor. Ama bir
 * kısmı ziyaretçinin okuduğu metin: alt bilgi telif satırı, mail başlığındaki
 * slogan, çalışma saatlerindeki "Kapalı". Kodun varsayılanı çevriliydi, ayar
 * doldurulduğu anda tek dile kilitleniyordu ve /en'de Türkçe çıkıyordu.
 *
 * Çözüm nullable bir `locale`: null "bütün diller" demek, dile ait satır
 * yalnız değeri eziyor. Buradaki sınavlar hem çözümlemeyi hem de kapanması
 * gereken iki tuzağı tutuyor — boş çevirinin asıl değeri silmemesi ve
 * tohumun çeviriyi ezmemesi.
 */
final class SettingsFollowTheLocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([['tr', true], ['en', false]] as [$code, $isDefault]) {
            Language::firstOrCreate(
                ['code' => $code],
                ['name' => strtoupper($code), 'native_name' => strtoupper($code), 'is_active' => true, 'is_default' => $isDefault],
            );
        }

        Setting::clearSettingsCache();
    }

    private function inLocale(string $locale, callable $callback): mixed
    {
        $previous = app()->getLocale();
        app()->setLocale($locale);

        try {
            return $callback();
        } finally {
            app()->setLocale($previous);
        }
    }

    // ── Çözümleme ──

    public function test_a_translated_setting_follows_the_locale(): void
    {
        Setting::setValue('footer_text', 'Tüm hakları saklıdır.');
        Setting::setValue('footer_text', 'All rights reserved.', 'general', 'text', 'en');

        $this->assertSame('Tüm hakları saklıdır.', $this->inLocale('tr', fn () => Setting::getValue('footer_text')));
        $this->assertSame('All rights reserved.', $this->inLocale('en', fn () => Setting::getValue('footer_text')));
    }

    /**
     * Çevirisi olmayan dil asıl değeri görmeli — boş değil.
     */
    public function test_an_untranslated_locale_falls_back(): void
    {
        Setting::setValue('footer_text', 'Tüm hakları saklıdır.');

        $this->assertSame('Tüm hakları saklıdır.', $this->inLocale('en', fn () => Setting::getValue('footer_text')));
    }

    /**
     * Boş bırakılan çeviri "çevrilmedi" demek.
     *
     * Boş dizge döndürülseydi ziyaretçi boş bir alt bilgi görürdü — çevirmemek
     * bundan iyidir.
     */
    public function test_an_empty_translation_does_not_blank_the_value(): void
    {
        Setting::setValue('footer_text', 'Tüm hakları saklıdır.');
        Setting::setValue('footer_text', '', 'general', 'text', 'en');

        $this->assertSame('Tüm hakları saklıdır.', $this->inLocale('en', fn () => Setting::getValue('footer_text')));
    }

    public function test_settings_without_a_translation_are_untouched(): void
    {
        Setting::setValue('contact_phone', '+90 555 000 00 00');

        $this->assertSame(
            '+90 555 000 00 00',
            $this->inLocale('en', fn () => Setting::getValue('contact_phone')),
        );
    }

    public function test_the_flat_map_is_resolved_too(): void
    {
        Setting::setValue('footer_text', 'Türkçe');
        Setting::setValue('footer_text', 'English', 'general', 'text', 'en');

        $this->assertSame('English', $this->inLocale('en', fn () => app(SettingService::class)->all()['footer_text']));
        $this->assertSame('Türkçe', $this->inLocale('tr', fn () => app(SettingService::class)->all()['footer_text']));
    }

    // ── Tohum çeviriyi ezmemeli ──

    public function test_reseeding_does_not_clobber_a_translation(): void
    {
        $this->seed(SettingSeeder::class);

        Setting::setValue('footer_text', 'All rights reserved.', 'general', 'text', 'en');

        // Tohum yeniden koşuyor: anahtar tek başına eşleştirilseydi bu çağrı
        // İngilizce satırı yakalayıp üzerine Türkçe asıl değeri yazardı.
        $this->seed(SettingSeeder::class);
        Setting::clearSettingsCache();

        $this->assertSame('All rights reserved.', $this->inLocale('en', fn () => Setting::getValue('footer_text')));
    }

    // ── Panel ──

    private function admin(): User
    {
        $this->seedAuthorization();

        $admin = User::firstOrCreate(
            ['email' => 'settings-admin@example.com'],
            ['first_name' => 'Ayar', 'last_name' => 'Yöneticisi', 'password' => 'password', 'is_active' => true],
        );

        $admin->roles()->syncWithoutDetaching(Role::where('slug', 'admin')->firstOrFail());

        return $admin;
    }

    public function test_the_panel_saves_a_translation(): void
    {
        Setting::setValue('footer_text', 'Tüm hakları saklıdır.');

        $this->actingAs($this->admin())
            ->put(route('admin.settings.update'), [
                'settings'              => ['footer_text' => 'Tüm hakları saklıdır.'],
                'settings_translations' => ['en' => ['footer_text' => 'All rights reserved.']],
            ])
            ->assertRedirect();

        Setting::clearSettingsCache();

        $this->assertSame('All rights reserved.', $this->inLocale('en', fn () => Setting::getValue('footer_text')));
        $this->assertSame('Tüm hakları saklıdır.', $this->inLocale('tr', fn () => Setting::getValue('footer_text')));
    }

    /**
     * Çeviri alanını boşaltmak satırı silmeli — boş dizgeyle kaydetmemeli.
     */
    public function test_clearing_a_translation_removes_the_row(): void
    {
        Setting::setValue('footer_text', 'Tüm hakları saklıdır.');
        Setting::setValue('footer_text', 'All rights reserved.', 'general', 'text', 'en');

        $this->actingAs($this->admin())
            ->put(route('admin.settings.update'), [
                'settings'              => ['footer_text' => 'Tüm hakları saklıdır.'],
                'settings_translations' => ['en' => ['footer_text' => '']],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('settings', ['key' => 'footer_text', 'locale' => 'en']);

        // Silinen çeviri geri eklenebilmeli. Yumuşak silinseydi satır
        // benzersizlik yerini tutmaya devam eder ve bu istek çakışırdı.
        $this->actingAs($this->admin())
            ->put(route('admin.settings.update'), [
                'settings'              => ['footer_text' => 'Tüm hakları saklıdır.'],
                'settings_translations' => ['en' => ['footer_text' => 'Back again.']],
            ])
            ->assertRedirect();

        Setting::clearSettingsCache();

        $this->assertSame('Back again.', $this->inLocale('en', fn () => Setting::getValue('footer_text')));
    }

    /**
     * Listede olmayan bir ayar çevrilemez: renk ya da gizli anahtar için dile
     * ait satır açmak, ayarın kimliğini bulanıklaştırırdı.
     */
    public function test_a_non_translatable_setting_is_ignored(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.settings.update'), [
                'settings'              => [],
                'settings_translations' => ['en' => ['recaptcha_secret_key' => 'sizdirma']],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('settings', ['key' => 'recaptcha_secret_key', 'locale' => 'en']);
    }

    /**
     * Etkin olmayan bir dil kodu gövdeye elle yazılabilir.
     */
    public function test_an_unknown_language_is_ignored(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.settings.update'), [
                'settings'              => [],
                'settings_translations' => ['zz' => ['footer_text' => 'uydurma']],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('settings', ['key' => 'footer_text', 'locale' => 'zz']);
    }

    public function test_the_screen_offers_a_field_for_every_translatable_setting(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('admin.settings.index'))
            ->assertOk();

        foreach (TranslatableSettings::keys() as $key) {
            $response->assertSee('settings_translations[en][' . $key . ']', false);
        }
    }

    /**
     * Panelde alanı olmayan bir anahtar listeye eklenirse çeviri kaydedilir ama
     * kimse onu göremez. Bu sınav listeyle ekranı birbirine bağlıyor.
     */
    public function test_every_translatable_key_is_a_real_setting_field(): void
    {
        $view = (string) file_get_contents(resource_path('views/admin/settings/index.blade.php'));

        foreach (TranslatableSettings::keys() as $key) {
            $this->assertStringContainsString(
                "settings[{$key}]",
                $view,
                "{$key} çevrilebilir listede ama ayarlar ekranında böyle bir alan yok.",
            );
        }
    }

    // ── API ──

    public function test_the_public_api_serves_the_translated_value(): void
    {
        Setting::setValue('site_description', 'Türkçe açıklama', 'general', 'text');
        Setting::setValue('site_description', 'English description', 'general', 'text', 'en');

        $this->getJson('/api/v1/settings', ['Accept-Language' => 'en'])
            ->assertOk()
            ->assertJsonPath('data.general.site_description', 'English description');

        $this->getJson('/api/v1/settings', ['Accept-Language' => 'tr'])
            ->assertOk()
            ->assertJsonPath('data.general.site_description', 'Türkçe açıklama');
    }
}
