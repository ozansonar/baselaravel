<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Cihazlarım" — kullanıcının kendi oturumlarını görüp kapatabilmesi.
 *
 * Jeton, oturum çerezinden farklı olarak kendiliğinden sona ermiyor ve sahibi
 * hangi cihazlarda açık olduğunu göremiyordu: telefonunu kaybeden kişinin elinde
 * tek seçenek şifresini değiştirmekti.
 */
class ApiDeviceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
    }

    /**
     * Guard'ların istek içi belleğini boşaltır — gerçek bir istekte her
     * seferinde yeni bir uygulama örneği doğuyor, testte ise aynı örnek bütün
     * istekler boyunca yaşıyor.
     */
    private function asToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function signIn(User $user, string $device): string
    {
        $this->app['auth']->forgetGuards();

        return (string) $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'Gizli*12345', 'device_name' => $device,
        ])->json('data.token');
    }

    public function test_the_list_shows_every_open_session_and_marks_this_one(): void
    {
        $user = User::factory()->create(['password' => 'Gizli*12345']);

        $this->signIn($user, 'tablet');
        $phone = $this->signIn($user, 'telefon');

        $devices = $this->asToken($phone)
            ->getJson('/api/v1/auth/devices')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'current', 'last_used_at', 'created_at', 'expires_at']]])
            ->json('data');

        $this->assertCount(2, $devices);

        // Tam olarak bir satır "bu cihaz" olmalı — kullanıcı yanlışlıkla kendi
        // oturumunu kapatmasın diye.
        $current = array_values(array_filter($devices, fn (array $d): bool => $d['current']));
        $this->assertCount(1, $current);
        $this->assertSame('telefon', $current[0]['name']);
    }

    /**
     * Jetonun kendisi hiçbir koşulda listelenemez: Sanctum onu hash'li tutuyor
     * ve düz metni yalnız üretildiği anda görülüyor.
     */
    public function test_the_list_never_leaks_the_token(): void
    {
        $user = User::factory()->create(['password' => 'Gizli*12345']);
        $token = $this->signIn($user, 'telefon');

        $body = (string) $this->asToken($token)->getJson('/api/v1/auth/devices')->getContent();

        $this->assertStringNotContainsString(explode('|', $token)[1], $body);
    }

    public function test_a_single_device_can_be_signed_out(): void
    {
        $user = User::factory()->create(['password' => 'Gizli*12345']);

        $tablet = $this->signIn($user, 'tablet');
        $phone = $this->signIn($user, 'telefon');

        $tabletId = $user->tokens()->where('name', 'tablet')->value('id');

        $this->asToken($phone)
            ->deleteJson('/api/v1/auth/devices/' . $tabletId)
            ->assertOk()
            ->assertJsonPath('success', true);

        // Tablet düştü, telefon ayakta.
        $this->asToken($tablet)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->asToken($phone)->getJson('/api/v1/auth/me')->assertOk();
    }

    /**
     * Başkasının jeton kimliğini yazan biri "yetkin yok" değil "yok" cevabı
     * almalı: ayrımı söylemek, kimlikleri tek tek deneyerek başka hesapların
     * oturumlarını haritalamaya yarardı.
     */
    public function test_another_users_session_cannot_be_touched(): void
    {
        $mine = User::factory()->create(['password' => 'Gizli*12345']);
        $theirs = User::factory()->create(['password' => 'Gizli*12345']);

        $theirToken = $this->signIn($theirs, 'kurbanin-telefonu');
        $theirId = $theirs->tokens()->first()?->id;

        $myToken = $this->signIn($mine, 'benim-telefonum');

        $this->asToken($myToken)
            ->deleteJson('/api/v1/auth/devices/' . $theirId)
            ->assertNotFound()
            ->assertJsonPath('success', false);

        // Ve gerçekten dokunulmamış.
        $this->assertSame(1, $theirs->tokens()->count());
        $this->asToken($theirToken)->getJson('/api/v1/auth/me')->assertOk();
    }

    /**
     * "Diğer cihazlardan çık" mevcut oturumu korumalı: düğmeye basan kişi
     * kendi uygulamasından atılmayı beklemiyor.
     */
    public function test_signing_out_everywhere_else_keeps_this_session(): void
    {
        $user = User::factory()->create(['password' => 'Gizli*12345']);

        $tablet = $this->signIn($user, 'tablet');
        $laptop = $this->signIn($user, 'dizustu');
        $phone = $this->signIn($user, 'telefon');

        $this->asToken($phone)
            ->deleteJson('/api/v1/auth/devices')
            ->assertOk()
            ->assertJsonPath('data.revoked', 2);

        $this->asToken($tablet)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->asToken($laptop)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->asToken($phone)->getJson('/api/v1/auth/me')->assertOk();

        $this->assertSame(1, $user->tokens()->count());
    }

    /**
     * Süresi dolmuş jeton listede görünmemeli: Sanctum onu zaten kabul etmiyor,
     * listede durması kullanıcıya kapatabileceği bir oturum varmış gibi
     * gösterirdi.
     */
    public function test_expired_sessions_are_not_listed(): void
    {
        $user = User::factory()->create(['password' => 'Gizli*12345']);
        $token = $this->signIn($user, 'telefon');

        $user->tokens()->create([
            'name'       => 'unutulmus',
            'token'      => hash('sha256', 'eski-jeton'),
            'abilities'  => ['*'],
            'expires_at' => now()->subDay(),
        ]);

        $names = collect($this->asToken($token)->getJson('/api/v1/auth/devices')->json('data'))->pluck('name');

        $this->assertContains('telefon', $names);
        $this->assertNotContains('unutulmus', $names);
    }

    public function test_the_list_is_closed_to_anonymous_requests(): void
    {
        $this->getJson('/api/v1/auth/devices')->assertUnauthorized();
        $this->deleteJson('/api/v1/auth/devices')->assertUnauthorized();
        $this->deleteJson('/api/v1/auth/devices/1')->assertUnauthorized();
    }

    /**
     * Doğrulanmamış e-posta buradan çevirmemeli: hesabına şüpheli bir erişim
     * olduğunu düşünen kişi, doğrulama adımını tamamlayamamış olsa bile
     * oturumları kapatabilmeli.
     */
    public function test_an_unverified_account_can_still_manage_its_sessions(): void
    {
        $user = User::factory()->create(['password' => 'Gizli*12345', 'email_verified_at' => null]);
        $token = $this->signIn($user, 'telefon');

        $this->asToken($token)->getJson('/api/v1/auth/devices')->assertOk();
    }
}
