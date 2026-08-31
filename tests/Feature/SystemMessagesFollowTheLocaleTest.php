<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Doğrulama ve sistem metinleri ziyaretçinin dilinde.
 *
 * Uygulama yalnız lang/tr/validation.php taşıyordu ve yedek dil de Türkçe.
 * Sonuç iki yönlü bozuktu: İngilizce sayfada "The Ad field is required."
 * çıkıyordu — İngilizce cümle, Türkçe alan adı; Türkçe sayfada ise kimlik
 * doğrulama ve şifre sıfırlama metinleri hiç bulunamayıp anahtarın kendisi
 * ekrana geliyordu ("auth.failed").
 *
 * Bunlar özel mesaj yazılmamış her kuralda ortaya çıkıyor, yani formların
 * kendi messages() metotlarını düzeltmek yetmiyordu.
 */
final class SystemMessagesFollowTheLocaleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Çerçevenin sağladığı, uygulamanın karşılığını taşıması gereken gruplar.
     *
     * @var list<string>
     */
    private const GROUPS = ['validation', 'auth', 'passwords'];

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

    // ── Doğrulama ──

    public function test_a_validation_message_speaks_the_visitors_language(): void
    {
        $tr = $this->inLocale('tr', fn (): string => Validator::make(['email' => ''], ['email' => 'required'])->errors()->first('email'));
        $en = $this->inLocale('en', fn (): string => Validator::make(['email' => ''], ['email' => 'required'])->errors()->first('email'));

        $this->assertStringContainsString('zorunludur', $tr);
        $this->assertStringContainsString('required', $en);
        $this->assertStringNotContainsString('zorunludur', $en);
    }

    /**
     * Alan adı da çevrilmeli.
     *
     * Cümle İngilizce ama alan adı Türkçeyse hata mesajı yarı yolda kalıyor:
     * "The Ad field is required."
     */
    public function test_the_field_name_is_translated_too(): void
    {
        $en = $this->inLocale('en', fn (): string => Validator::make(['first_name' => ''], ['first_name' => 'required'])->errors()->first('first_name'));

        $this->assertStringContainsString('first name', $en);
        $this->assertStringNotContainsString('Ad', $en);
    }

    /** Türkçe alan adları da yerinde kalmalı. */
    public function test_the_turkish_field_names_still_work(): void
    {
        $tr = $this->inLocale('tr', fn (): string => Validator::make(['first_name' => ''], ['first_name' => 'required'])->errors()->first('first_name'));

        $this->assertStringStartsWith('Ad', $tr);
    }

    /**
     * İki dil aynı alan kümesini tanımalı: birinde olup ötekinde olmayan alan
     * adı, o dilde ham sütun adının ekrana çıkması demek.
     */
    public function test_both_languages_name_the_same_fields(): void
    {
        $tr = array_keys((require lang_path('tr/validation.php'))['attributes'] ?? []);
        $en = array_keys((require lang_path('en/validation.php'))['attributes'] ?? []);

        sort($tr);
        sort($en);

        $this->assertNotEmpty($tr);
        $this->assertSame($tr, $en, 'Alan adları iki dilde aynı değil');
    }

    // ── Sistem metinleri ──

    /**
     * Anahtar bulunamayınca Laravel anahtarın kendisini basıyor: hata
     * vermiyor, ekranda "auth.failed" yazıyor.
     */
    public function test_no_system_message_falls_through_as_a_raw_key(): void
    {
        $anahtarlar = [
            'auth.failed', 'auth.password', 'auth.throttle',
            'passwords.reset', 'passwords.sent', 'passwords.throttled',
            'passwords.token', 'passwords.user',
            'pagination.previous', 'pagination.next',
        ];

        $ham = [];

        foreach (['tr', 'en'] as $locale) {
            foreach ($anahtarlar as $key) {
                $metin = $this->inLocale($locale, fn (): string => (string) __($key, ['seconds' => 60]));

                if ($metin === $key) {
                    $ham[] = "{$locale}: {$key}";
                }
            }
        }

        $this->assertSame([], $ham, "Ham anahtar ekrana çıkıyor:\n  " . implode("\n  ", $ham));
    }

    public function test_the_system_messages_actually_differ_between_languages(): void
    {
        foreach (['auth.failed', 'passwords.sent'] as $key) {
            $tr = $this->inLocale('tr', fn (): string => (string) __($key));
            $en = $this->inLocale('en', fn (): string => (string) __($key));

            $this->assertNotSame($tr, $en, "{$key} iki dilde de aynı metni veriyor");
        }
    }

    /**
     * Çerçevenin sağladığı her grubun uygulama tarafında da karşılığı olmalı.
     *
     * Yedek dil Türkçe: İngilizcede eksik bir grup sessizce Türkçeye düşüyor,
     * Türkçede eksik bir grup ise hiçbir yere düşemiyor.
     */
    public function test_each_language_carries_the_groups_it_needs(): void
    {
        $eksik = [];

        foreach (['tr', 'en'] as $locale) {
            foreach (self::GROUPS as $group) {
                $cerceve = base_path("vendor/laravel/framework/src/Illuminate/Translation/lang/{$locale}/{$group}.php");

                // Çerçeve o dili sağlamıyorsa uygulama sağlamak zorunda.
                if (! is_file($cerceve) && ! is_file(lang_path("{$locale}/{$group}.php"))) {
                    $eksik[] = "{$locale}/{$group}.php";
                }
            }
        }

        $this->assertSame([], $eksik, "Eksik dil dosyası:\n  " . implode("\n  ", $eksik));
    }

    // ── Ekranlarda ──

    public function test_a_failed_sign_in_answers_in_the_visitors_language(): void
    {
        $this->seed(\Database\Seeders\LanguageSeeder::class);
        app(\App\Services\LanguageService::class)->clearCache();

        foreach ([['tr', 'site.login.failed'], ['en', 'site.login.failed']] as [$locale, $key]) {
            $this->post(route('login', ['locale' => $locale]), [
                'email' => 'yok@ornek.com', 'password' => 'yanlissifre',
            ])->assertSessionHasErrors(['email' => $this->inLocale($locale, fn (): string => (string) __($key))]);

            $this->flushSession();
        }
    }

    public function test_an_unknown_address_in_password_reset_answers_in_the_visitors_language(): void
    {
        $this->seed(\Database\Seeders\LanguageSeeder::class);
        app(\App\Services\LanguageService::class)->clearCache();

        $this->post(route('password.email', ['locale' => 'en']), ['email' => 'yok@ornek.com'])
            ->assertSessionHasErrors(['email' => $this->inLocale('en', fn (): string => (string) __('site.password.no_account'))]);
    }
}
