<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Session\MigratingStore;
use App\Session\SessionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\FileSessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Oturum serileştirmesi.
 *
 * Oturum verisi varsayılan olarak PHP'nin `serialize()` biçiminde yazılıyordu.
 * O biçimin bilinen bir tehlikesi var: `unserialize()` veri okumakla kalmıyor,
 * **nesne kuruyor** — saklanan dizgeyi değiştirebilen biri uygulamadaki
 * sınıflardan bir zincir kurup kod çalıştırabiliyor. `json` bu yüzeyi tamamen
 * kapatıyor.
 *
 * Geçiş uzun süre ertelenmişti çünkü ayarı çevirmek o anda açık olan bütün
 * oturumları okunamaz hâle getiriyor ve herkes aynı anda çıkış yapıyordu.
 * `migrate` modu bu bedeli kaldırıyor; buradaki sınavların çoğu onun gerçekten
 * çalıştığını gösteriyor.
 */
class SessionSerializationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
        $this->seed(\Database\Seeders\LanguageSeeder::class);
    }

    // ── Varsayılan ──

    public function test_the_default_is_json(): void
    {
        $this->assertSame('json', config('session.serialization'));
    }

    /**
     * Yeni kurulum hiçbir geçiş katmanı taşımıyor: ortada eski biçimde oturum
     * olmadığı için `unserialize()` yolunun açık kalması için sebep yok.
     */
    public function test_the_plain_store_is_used_when_not_migrating(): void
    {
        $this->assertInstanceOf(SessionManager::class, app('session'));
        $this->assertFalse(app('session')->migrating());
        $this->assertInstanceOf(Store::class, app('session')->driver());
        $this->assertNotInstanceOf(MigratingStore::class, app('session')->driver());
    }

    public function test_migrate_mode_builds_the_migrating_store(): void
    {
        $this->withSerialization('migrate', function (): void {
            $this->assertTrue(app('session')->migrating());
            $this->assertInstanceOf(MigratingStore::class, app('session')->driver());
        });
    }

    /**
     * Geri dönüş yolu kapanmamalı: bir kurulum sorun yaşarsa eski biçime
     * dönebilmeli.
     */
    public function test_the_old_format_is_still_selectable(): void
    {
        $this->withSerialization('php', function (): void {
            $this->assertFalse(app('session')->migrating());
            $this->assertInstanceOf(Store::class, app('session')->driver());
        });
    }

    // ── Geçişin kendisi ──

    /**
     * Sorunun kanıtı: saf JSON deposu eski biçimde yazılmış bir oturumu
     * okuyamıyor. Kullanıcı için bu, sessizce çıkış yapmış olmak demek.
     */
    public function test_a_plain_json_store_cannot_read_a_legacy_session(): void
    {
        [$handler, $id] = $this->legacySession(['login_web_x' => 42]);

        $store = new Store('deneme', $handler, $id, 'json');
        $store->start();

        $this->assertNull($store->get('login_web_x'));

        $handler->destroy($id);
    }

    /**
     * Çözümün kanıtı: geçiş deposu aynı oturumu okuyor.
     */
    public function test_the_migrating_store_reads_a_legacy_session(): void
    {
        [$handler, $id] = $this->legacySession([
            '_token'      => 'abc123',
            'login_web_x' => 42,
            'locale'      => 'tr',
        ]);

        $store = new MigratingStore('deneme', $handler, $id, 'json');
        $store->start();

        $this->assertSame('abc123', $store->get('_token'));
        $this->assertSame(42, $store->get('login_web_x'));
        $this->assertSame('tr', $store->get('locale'));

        $handler->destroy($id);
    }

    /**
     * Geçiş tek yönlü: okunan eski oturum bir sonraki kayıtta yeni biçime
     * dönüyor. Kullanıcı hiçbir şey fark etmiyor, kayıt kendiliğinden taşınıyor.
     */
    public function test_a_legacy_session_is_rewritten_as_json_on_save(): void
    {
        [$handler, $id] = $this->legacySession(['login_web_x' => 42]);

        $this->assertNull(json_decode((string) $handler->read($id), true), 'Kurulum eski biçimde değil.');

        $store = new MigratingStore('deneme', $handler, $id, 'json');
        $store->start();
        $store->put('yeni', 'deger');
        $store->save();

        $this->assertIsArray(
            json_decode((string) $handler->read($id), true),
            'Kayıt JSON olarak yazılmadı.',
        );

        // Artık geçiş katmanı olmadan da okunuyor.
        $plain = new Store('deneme', $handler, $id, 'json');
        $plain->start();

        $this->assertSame(42, $plain->get('login_web_x'));
        $this->assertSame('deger', $plain->get('yeni'));

        $handler->destroy($id);
    }

    /**
     * Geçiş dönemi bile nesne kurmuyor.
     *
     * `unserialize()` sınırlı çağrılıyor (`allowed_classes: false`), yani eski
     * bir oturumda nesne bulunsa da kurulmuyor. Geçişin amacı bu yüzeyi
     * kapatmaktı; geçiş boyunca açık bırakmak amacı boşa çıkarırdı.
     */
    public function test_the_migrating_store_never_builds_objects(): void
    {
        $handler = $this->handler();
        $id = Str::random(40);

        // İçinde nesne olan eski bir oturum.
        $handler->write($id, serialize([
            'login_web_x' => 42,
            'nesne'       => new \stdClass(),
        ]));

        $store = new MigratingStore('deneme', $handler, $id, 'json');
        $store->start();

        $this->assertSame(42, $store->get('login_web_x'));
        $this->assertNull($store->get('nesne'), 'Eski oturumdaki nesne kuruldu.');

        $handler->destroy($id);
    }

    // ── Uçtan uca ──

    /**
     * JSON ile giriş akışı bozulmuyor.
     */
    public function test_a_member_can_sign_in_with_json_sessions(): void
    {
        $user = $this->member();

        $response = $this->post('/tr/giris', [
            'email'    => $user->email,
            'password' => 'sifre-123456',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user->fresh());
    }

    /**
     * Doğrulama hataları JSON'da da taşınıyor.
     *
     * Bu, geçişin en ince noktası: hata torbası aslında bir nesne
     * (`ViewErrorBag`) ve JSON nesne saklayamıyor. Çerçeve onu yazarken düz
     * diziye çeviriyor, okurken geri kuruyor — çalıştığını burada görüyoruz.
     */
    public function test_validation_errors_survive_a_json_session(): void
    {
        $response = $this->from('/tr/giris')->post('/tr/giris', [
            'email'    => 'gecersiz-adres',
            'password' => '',
        ]);

        $response->assertRedirect('/tr/giris');
        $response->assertSessionHasErrors(['email']);

        // Yönlendirmenin ardından hata ekranda görünüyor mu?
        $this->followingRedirects()
            ->from('/tr/giris')
            ->post('/tr/giris', ['email' => 'gecersiz-adres', 'password' => ''])
            ->assertOk();
    }

    /**
     * Flash mesajı bir sonraki isteğe taşınıyor.
     */
    public function test_a_flash_message_survives_a_json_session(): void
    {
        $admin = $this->admin();

        // Bir içerik kaydetmek başarı mesajı bırakıyor; mesajın bir sonraki
        // istekte hâlâ orada olması flash'ın oturumda taşındığını gösteriyor.
        $response = $this->actingAs($admin)->post('/admin/blog-categories', [
            'active_locale' => 'tr',
            'translations'  => ['tr' => [
                'name'      => 'Oturum Denemesi',
                'slug'      => 'oturum-denemesi',
                'is_active' => 1,
            ]],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Yönlendirme sonrası mesaj ekranda görünüyor: flash bir sonraki
        // isteğe taşındı ve orada tüketildi.
        $this->actingAs($admin)
            ->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee('Oturum Denemesi');
    }

    /**
     * Dil seçimi oturumda taşınıyor — kit'in oturuma yazdığı üç şeyden biri.
     */
    public function test_the_chosen_locale_survives_a_json_session(): void
    {
        $this->get('/dil/en')->assertRedirect();

        $this->assertSame('en', session()->get(\App\Http\Middleware\SetLocale::SESSION_KEY));
    }

    // ── Bekçi ──

    /**
     * Oturuma nesne yazılmamalı.
     *
     * JSON yalnız veri saklıyor: bir model, bir `Carbon` ya da bir
     * `Collection` oturuma konursa sessizce düz diziye dönüyor ve okurken
     * beklenen türü bulamayan kod patlıyor. Kit bugün oturuma yalnız skaler
     * yazıyor; bu bekçi onu koruyor.
     */
    public function test_nothing_writes_an_object_into_the_session(): void
    {
        $offenders = [];
        $scanned = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->getExtension() !== 'php') {
                continue;
            }

            ++$scanned;
            $source = (string) file_get_contents($file->getPathname());
            $relative = str_replace(base_path() . '/', '', $file->getPathname());

            preg_match_all(
                '/(?:session\(\)->put|Session::put|session\(\)->flash|Session::flash)\(([^;]{0,200})/',
                $source,
                $matches,
                PREG_OFFSET_CAPTURE,
            );

            foreach ($matches[1] as [$args, $offset]) {
                // Nesne işareti: new, model çağrısı, Carbon, collect()
                if (preg_match('/\bnew\s+[A-Z]|::(?:find|first|get|create)\(|\bcollect\(|\bCarbon::|now\(\)(?!->)/', $args) !== 1) {
                    continue;
                }

                $line = substr_count(substr($source, 0, $offset), "\n") + 1;
                $offenders[] = "{$relative}:{$line}  " . trim(mb_substr($args, 0, 90));
            }
        }

        $this->assertGreaterThan(100, $scanned, 'app/ dizini taranamadı.');

        sort($offenders);

        $this->assertSame(
            [],
            $offenders,
            "Oturuma nesne yazılıyor — JSON yalnız veri saklar, bu değer\n"
                . "okurken beklenen türde dönmez:\n  " . implode("\n  ", $offenders),
        );
    }

    // ── Yardımcılar ──

    /**
     * @param  array<string, mixed> $data
     * @return array{0: FileSessionHandler, 1: string}
     */
    private function legacySession(array $data): array
    {
        $handler = $this->handler();
        $id = Str::random(40);

        $handler->write($id, serialize($data));

        return [$handler, $id];
    }

    private function handler(): FileSessionHandler
    {
        $dizin = storage_path('framework/testing/sessions');

        // Test dizini depoda tutulmuyor (git boş dizin taşımıyor); ilk
        // koşuda açılması gerekiyor.
        if (! is_dir($dizin)) {
            mkdir($dizin, 0755, true);
        }

        return new FileSessionHandler(app('files'), $dizin, 120);
    }

    private function withSerialization(string $mode, \Closure $work): void
    {
        $previous = config('session.serialization');

        config(['session.serialization' => $mode]);
        app()->forgetInstance('session');

        try {
            $work();
        } finally {
            config(['session.serialization' => $previous]);
            app()->forgetInstance('session');
        }
    }

    private function member(): User
    {
        $user = User::create([
            'first_name' => 'Oturum', 'last_name' => 'Uye',
            'email' => 'oturum@example.test', 'password' => 'sifre-123456', 'is_active' => true,
        ]);
        $user->markEmailAsVerified();
        $user->roles()->attach(Role::where('slug', 'user')->firstOrFail()->id);

        return $user->fresh();
    }

    private function admin(): User
    {
        $user = User::create([
            'first_name' => 'Oturum', 'last_name' => 'Yonetici',
            'email' => 'oturum-admin@example.test', 'password' => 'sifre-123456', 'is_active' => true,
        ]);
        $user->markEmailAsVerified();
        $user->roles()->attach(Role::where('slug', 'admin')->firstOrFail()->id);

        return $user->fresh();
    }
}
