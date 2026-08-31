<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Mail\PasswordResetCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Mobil şifre sıfırlama — bağlantı yerine altı haneli kod.
 *
 * Kodun kısalığı ancak üç şey bir aradayken güvenli: süre sınırı, hız sınırı ve
 * başarıdan sonra kodun silinmesi. Üçü de burada sınanıyor — biri düşerse kod
 * kırılabilir hâle gelir ve bunu fark etmenin başka yolu yok.
 */
class ApiPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        Mail::fake();
    }

    private function user(string $email = 'ozan@ornek.com'): User
    {
        return User::factory()->create([
            'email'             => $email,
            'password'          => 'Eski*12345',
            'email_verified_at' => now(),
        ]);
    }

    /**
     * Maile giden kodu yakalar — veritabanında hash'li durduğu için okunamıyor.
     */
    private function requestCode(string $email): ?string
    {
        $this->postJson('/api/v1/auth/password/forgot', ['email' => $email])->assertOk();

        $code = null;

        Mail::assertQueued(PasswordResetCodeMail::class, function (PasswordResetCodeMail $mail) use (&$code): bool {
            $code = $mail->code;

            return true;
        });

        return $code;
    }

    public function test_a_reset_code_is_mailed_and_stored_hashed(): void
    {
        $user = $this->user();

        $code = $this->requestCode($user->email);

        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $code);

        $row = DB::table('password_reset_tokens')->where('email', $user->email)->first();

        $this->assertNotNull($row);
        // Düz metin saklansaydı veritabanını gören herkes her hesabı açabilirdi.
        $this->assertNotSame($code, $row->token);
        $this->assertTrue(Hash::check((string) $code, $row->token));
    }

    public function test_the_code_changes_the_password(): void
    {
        $user = $this->user();
        $code = $this->requestCode($user->email);

        $this->postJson('/api/v1/auth/password/reset', [
            'email'                 => $user->email,
            'code'                  => $code,
            'password'              => 'Yeni*12345',
            'password_confirmation' => 'Yeni*12345',
        ])->assertOk()->assertJsonPath('success', true);

        // Eski şifre artık geçmiyor, yenisi geçiyor.
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Eski*12345'])
            ->assertStatus(401);

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Yeni*12345'])
            ->assertOk();
    }

    public function test_the_code_is_single_use(): void
    {
        $user = $this->user();
        $code = $this->requestCode($user->email);

        $payload = [
            'email'                 => $user->email,
            'code'                  => $code,
            'password'              => 'Yeni*12345',
            'password_confirmation' => 'Yeni*12345',
        ];

        $this->postJson('/api/v1/auth/password/reset', $payload)->assertOk();

        // İkinci kez kullanılamaz: satır silindi.
        $this->postJson('/api/v1/auth/password/reset', $payload)->assertStatus(422);

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_a_wrong_code_is_refused(): void
    {
        $user = $this->user();
        $code = $this->requestCode($user->email);

        $wrong = $code === '000000' ? '111111' : '000000';

        $this->postJson('/api/v1/auth/password/reset', [
            'email'                 => $user->email,
            'code'                  => $wrong,
            'password'              => 'Yeni*12345',
            'password_confirmation' => 'Yeni*12345',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['code']]);

        // Şifre değişmemiş olmalı.
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Eski*12345'])
            ->assertOk();
    }

    public function test_an_expired_code_is_refused(): void
    {
        $user = $this->user();
        $code = $this->requestCode($user->email);

        // Süre config/auth.php'den geliyor; sınama onu okuyor ki ayar
        // değiştiğinde burası yalan söylemesin.
        $minutes = (int) config('auth.passwords.users.expire');

        DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->update(['created_at' => now()->subMinutes($minutes + 1)]);

        $this->postJson('/api/v1/auth/password/reset', [
            'email'                 => $user->email,
            'code'                  => $code,
            'password'              => 'Yeni*12345',
            'password_confirmation' => 'Yeni*12345',
        ])->assertStatus(422);
    }

    /**
     * Sıfırlama, o ana kadarki bütün erişimi düşürmeli. Düşürmezse hesabı ele
     * geçiren kişi şifre değişse de içeride kalır — ve sıfırlamanın varlık
     * sebebi tam olarak bu durumdur.
     */
    public function test_resetting_revokes_every_existing_token(): void
    {
        $user = $this->user();

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'Eski*12345',
        ])->json('data.token');

        $code = $this->requestCode($user->email);

        $this->postJson('/api/v1/auth/password/reset', [
            'email'                 => $user->email,
            'code'                  => $code,
            'password'              => 'Yeni*12345',
            'password_confirmation' => 'Yeni*12345',
        ])->assertOk();

        $this->assertSame(0, $user->tokens()->count());

        $this->app['auth']->forgetGuards();

        $this->withToken((string) $token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    /**
     * Kayıtlı olmayan adres, kayıtlı olandan ayırt edilememeli: aksi hâlde bu
     * uç, hangi adreslerin sistemde olduğunu öğrenmenin en kolay yolu olurdu.
     */
    public function test_an_unknown_address_gets_the_same_answer(): void
    {
        $user = $this->user();

        $known = $this->postJson('/api/v1/auth/password/forgot', ['email' => $user->email])->assertOk();
        $unknown = $this->postJson('/api/v1/auth/password/forgot', ['email' => 'yok@ornek.com'])->assertOk();

        $this->assertSame($known->json('message'), $unknown->json('message'));
        $this->assertSame($known->status(), $unknown->status());

        // Bilinmeyen adres için ne mail çıkıyor ne satır yazılıyor.
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'yok@ornek.com']);
    }

    public function test_a_deactivated_account_gets_no_code(): void
    {
        $user = $this->user();
        $user->update(['is_active' => false]);

        $this->postJson('/api/v1/auth/password/forgot', ['email' => $user->email])->assertOk();

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        Mail::assertNotQueued(PasswordResetCodeMail::class);
    }

    /**
     * Altı hanenin güvenliği bu sınıra bağlı. Sınır kalkarsa bir milyon
     * olasılık dakikalar içinde taranabilir.
     */
    public function test_reset_attempts_are_throttled(): void
    {
        $user = $this->user();
        $this->requestCode($user->email);

        // Kod isteme ve kod deneme aynı kovayı paylaşıyor: saldırgan yeni bir
        // kod isteyerek deneme kotasını tazeleyemesin. requestCode() yukarıda
        // kovadan bir hak yedi, kalanı bu.
        $remaining = (int) config('api.rate_limits.password') - 1;

        for ($attempt = 0; $attempt < $remaining; $attempt++) {
            $this->postJson('/api/v1/auth/password/reset', [
                'email'                 => $user->email,
                'code'                  => '000000',
                'password'              => 'Yeni*12345',
                'password_confirmation' => 'Yeni*12345',
            ])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/password/reset', [
            'email'                 => $user->email,
            'code'                  => '000000',
            'password'              => 'Yeni*12345',
            'password_confirmation' => 'Yeni*12345',
        ])
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    public function test_the_web_and_api_flows_share_one_active_request(): void
    {
        $user = $this->user();

        $first = $this->requestCode($user->email);
        $firstRow = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');

        // Aynı tabloda tek satır: ikinci istek birincinin yerine geçiyor.
        $this->travel(2)->minutes();
        $second = $this->requestCode($user->email);
        $secondRow = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');

        $this->assertSame(1, DB::table('password_reset_tokens')->where('email', $user->email)->count());
        $this->assertNotSame($firstRow, $secondRow);
        $this->assertFalse(Hash::check((string) $first, (string) $secondRow));
        $this->assertTrue(Hash::check((string) $second, (string) $secondRow));
    }
}
