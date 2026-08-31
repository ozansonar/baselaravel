<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\AccountDeviceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * "Cihazlarım" ekranı.
 *
 * Hesabına erişimin nereden olduğunu göremeyen kişinin, şüphelendiğinde elinde
 * tek seçenek şifresini değiştirmek oluyordu. Ekranın iki kaynağı birden
 * listelemesi şart: tarayıcı oturumu `sessions` tablosunda, mobil uygulama
 * `personal_access_tokens` içinde duruyor ve yalnız birini göstermek "başka
 * yerde açık oturum yok" demenin yanlış bir yolu.
 *
 * Test ortamı oturumları dizide tutuyor (phpunit.xml). Tarayıcı oturumlarının
 * sınandığı yerlerde sürücü bilerek veritabanına çevriliyor — yoksa sınanan
 * kod yolu hiç çalışmıyor.
 */
class AccountDeviceTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/tr/hesabim/cihazlar';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
    }

    private function user(string $email = 'cihaz@example.test'): User
    {
        $user = User::create([
            'first_name' => 'Deneme',
            'last_name'  => 'Kullanici',
            'email'      => $email,
            'password'   => 'sifre-123456',
            'is_active'  => true,
        ]);

        // Hesap alanı `verified` ara katmanının arkasında; damga fillable değil.
        $user->markEmailAsVerified();

        return $user;
    }

    private function useDatabaseSessions(): void
    {
        config(['session.driver' => 'database']);
    }

    private function insertSession(string $id, ?User $user, string $userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36', string $ip = '10.0.0.1'): void
    {
        // updateOrInsert: sürücü veritabanına çevrildiğinde çerçevenin kendisi
        // de mevcut oturumu bu tabloya yazıyor, aynı kimliği ikinci kez
        // eklemek benzersizlik kısıtına takılırdı.
        DB::table('sessions')->updateOrInsert(['id' => $id], [
            'user_id'       => $user?->getKey(),
            'ip_address'    => $ip,
            'user_agent'    => $userAgent,
            'payload'       => base64_encode(serialize([])),
            'last_activity' => now()->getTimestamp(),
        ]);
    }

    public function test_the_screen_lists_the_browser_sessions_of_the_signed_in_user(): void
    {
        $this->useDatabaseSessions();

        $user = $this->user();
        $this->insertSession('oturum-diger-tarayici', $user);

        $html = $this->actingAs($user)->get(self::URL)->assertOk()->getContent();

        // Ayrıştırılmış hâliyle: kullanıcı ham User-Agent satırını okumamalı.
        $this->assertStringContainsString('Chrome 120', (string) $html);
        $this->assertStringNotContainsString('AppleWebKit', (string) $html);
        $this->assertStringContainsString('10.0.0.1', (string) $html);
    }

    public function test_another_users_session_is_not_listed(): void
    {
        $this->useDatabaseSessions();

        $user = $this->user();
        $other = $this->user('baskasi@example.test');

        $this->insertSession('oturum-baskasinin', $other, ip: '203.0.113.9');

        $html = $this->actingAs($user)->get(self::URL)->assertOk()->getContent();

        $this->assertStringNotContainsString('203.0.113.9', (string) $html);
    }

    public function test_the_screen_lists_api_tokens(): void
    {
        $user = $this->user();
        $user->createToken('iPhone 15');

        $this->actingAs($user)->get(self::URL)
            ->assertOk()
            ->assertSee('iPhone 15');
    }

    public function test_an_expired_token_is_not_listed(): void
    {
        $user = $this->user();
        $user->createToken('Eski telefon');
        $user->tokens()->update(['expires_at' => now()->subDay()]);

        $this->actingAs($user)->get(self::URL)
            ->assertOk()
            ->assertDontSee('Eski telefon');
    }

    public function test_a_browser_session_can_be_closed(): void
    {
        $this->useDatabaseSessions();

        $user = $this->user();
        $this->insertSession('oturumkapatilacak', $user);

        $this->actingAs($user)
            ->delete('/tr/hesabim/cihazlar/oturum/oturumkapatilacak')
            ->assertRedirect(route('account.devices'));

        $this->assertDatabaseMissing('sessions', ['id' => 'oturumkapatilacak']);
    }

    /**
     * Kimlikleri tek tek deneyen biri başkasının oturumunu kapatamamalı — ve
     * "yetkin yok" cevabı bile o oturumun var olduğunu söylerdi.
     */
    public function test_a_session_belonging_to_someone_else_cannot_be_closed(): void
    {
        $this->useDatabaseSessions();

        $user = $this->user();
        $other = $this->user('kurban@example.test');
        $this->insertSession('baskasininoturumu', $other);

        $this->actingAs($user)
            ->delete('/tr/hesabim/cihazlar/oturum/baskasininoturumu')
            ->assertRedirect(route('account.devices'));

        $this->assertDatabaseHas('sessions', ['id' => 'baskasininoturumu']);
    }

    public function test_a_token_can_be_revoked(): void
    {
        $user = $this->user();
        $token = $user->createToken('Tablet')->accessToken;

        $this->actingAs($user)
            ->delete('/tr/hesabim/cihazlar/uygulama/' . $token->getKey())
            ->assertRedirect(route('account.devices'));

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->getKey()]);
    }

    public function test_a_token_belonging_to_someone_else_cannot_be_revoked(): void
    {
        $user = $this->user();
        $other = $this->user('jeton@example.test');
        $token = $other->createToken('Kurbanın telefonu')->accessToken;

        $this->actingAs($user)
            ->delete('/tr/hesabim/cihazlar/uygulama/' . $token->getKey())
            ->assertRedirect(route('account.devices'));

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->getKey()]);
    }

    /**
     * Toplu kapatma her iki kaynağı da temizliyor ama kişinin içinde bulunduğu
     * oturuma dokunmuyor: düğmeye basan kişi kendi tarayıcısından atılmayı
     * beklemiyor.
     *
     * Servis katmanından sınanıyor: HTTP üzerinden mevcut oturumun kimliği
     * istekten isteğe değişiyor (test ortamı oturumları dizide tutuyor) ve
     * sınanan şey "hangi kimlik korunuyor" olduğu için o değişkenlik sınavın
     * kendisini geçersiz kılıyor.
     */
    public function test_closing_the_others_keeps_the_current_session_and_clears_the_rest(): void
    {
        $this->useDatabaseSessions();

        $user = $this->user();
        $user->createToken('Telefon');
        $this->insertSession('butarayici', $user, ip: '127.0.0.1');
        $this->insertSession('digertarayici', $user);

        $count = app(AccountDeviceService::class)->revokeOthers($user, 'butarayici');

        $this->assertDatabaseHas('sessions', ['id' => 'butarayici']);
        $this->assertDatabaseMissing('sessions', ['id' => 'digertarayici']);
        $this->assertSame(0, $user->tokens()->count());

        // Bir tarayıcı oturumu + bir jeton.
        $this->assertSame(2, $count);
    }

    public function test_the_bulk_endpoint_closes_the_tokens(): void
    {
        $user = $this->user();
        $user->createToken('Telefon');

        $this->actingAs($user)
            ->delete('/tr/hesabim/cihazlar')
            ->assertRedirect(route('account.devices'));

        $this->assertSame(0, $user->tokens()->count());
    }

    /**
     * Beni hatırla damgası kalsaydı kapatılan tarayıcı bir sonraki istekte
     * kendini yeniden doğrular ve oturum geri açılırdı.
     */
    public function test_closing_the_others_drops_the_remember_token(): void
    {
        $user = $this->user();
        $user->forceFill(['remember_token' => 'eski-damga'])->saveQuietly();

        $this->actingAs($user)->delete('/tr/hesabim/cihazlar')->assertRedirect(route('account.devices'));

        $this->assertNull($user->fresh()?->remember_token);
    }

    public function test_the_screen_is_closed_to_guests(): void
    {
        $this->get(self::URL)->assertRedirect();
    }

    /**
     * Oturumlar veritabanında tutulmuyorsa bölüm boş liste değil açıklama
     * gösteriyor: boş liste "hiçbir yerden giriş yok" demek olurdu.
     */
    public function test_the_browser_section_explains_itself_when_sessions_are_not_in_the_database(): void
    {
        config(['session.driver' => 'array']);

        $user = $this->user();

        $this->actingAs($user)->get(self::URL)
            ->assertOk()
            ->assertSee(__('site.devices.browsers_unavailable'));
    }

    public function test_the_account_navigation_links_to_the_screen(): void
    {
        $user = $this->user();

        $this->actingAs($user)->get('/tr/hesabim')
            ->assertOk()
            ->assertSee(route('account.devices'));
    }
}
