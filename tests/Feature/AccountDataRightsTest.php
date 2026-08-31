<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BlogCategory;
use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\Role;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kişinin kendi verisi üzerindeki iki hakkı: kopyasını almak ve hesabı
 * kapatmak.
 *
 * İkisinin de sınavı aynı yerde toplanıyor: dosya kişinin *bütün* verisini
 * taşımalı ama hiçbir anahtarını taşımamalı, kapatma da gerçekten kapatmalı —
 * "giriş yapamaz" demek yetmiyor, açık oturumların ve jetonların da düşmesi
 * gerekiyor.
 */
class AccountDataRightsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
    }

    private function user(string $email = 'veri@example.test'): User
    {
        $user = User::create([
            'first_name' => 'Deneme',
            'last_name'  => 'Kullanici',
            'email'      => $email,
            'password'   => 'sifre-123456',
            'is_active'  => true,
        ]);

        $user->markEmailAsVerified();

        return $user;
    }

    // ── Veri indirme ──

    public function test_the_export_carries_the_profile_and_the_related_records(): void
    {
        $user = $this->user();

        Subscriber::create(['email' => $user->email, 'status' => 'subscribed', 'source' => 'footer']);

        $category = BlogCategory::create(['locale' => 'tr', 'name' => 'Genel', 'slug' => 'genel', 'is_active' => true]);
        $post = BlogPost::create([
            'locale' => 'tr', 'blog_category_id' => $category->id, 'title' => 'Yazı',
            'slug' => 'yazi', 'body' => 'Gövde', 'status' => 'published', 'published_at' => now(),
        ]);
        BlogComment::create([
            'blog_post_id' => $post->id, 'name' => $user->full_name,
            'email' => $user->email, 'body' => 'Yorumum', 'status' => 'approved',
        ]);

        $data = app(\App\Services\AccountDataService::class)->export($user);

        $this->assertSame($user->email, $data['profile']['email']);
        $this->assertCount(1, $data['blog_comments']);
        $this->assertSame('Yorumum', $data['blog_comments'][0]['body']);
        $this->assertCount(1, $data['newsletter']);
    }

    /**
     * Dosya bir kez sızarsa hesabın anahtarları da sızmamalı.
     */
    public function test_the_export_never_carries_secrets(): void
    {
        $user = $this->user();
        app(\App\Services\TwoFactorService::class)->beginSetup($user);
        $user->createToken('Telefon');

        $json = (string) json_encode(app(\App\Services\AccountDataService::class)->export($user->fresh()));

        $this->assertStringNotContainsString('password', $json);
        $this->assertStringNotContainsString('two_factor_secret', $json);
        $this->assertStringNotContainsString((string) $user->fresh()?->two_factor_secret, $json);
        $this->assertStringNotContainsString('remember_token', $json);
    }

    public function test_another_users_records_are_not_in_the_export(): void
    {
        $user = $this->user();
        $other = $this->user('baskasi@example.test');

        Subscriber::create(['email' => $other->email, 'status' => 'subscribed', 'source' => 'footer']);

        $json = (string) json_encode(app(\App\Services\AccountDataService::class)->export($user));

        $this->assertStringNotContainsString($other->email, $json);
    }

    public function test_the_download_returns_a_json_attachment(): void
    {
        $user = $this->user();

        $response = $this->actingAs($user)->get('/tr/hesabim/veriler/indir');

        $response->assertOk();
        $this->assertStringContainsString('application/json', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment;', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('cache-control'));
    }

    public function test_the_download_is_closed_to_guests(): void
    {
        $this->get('/tr/hesabim/veriler/indir')->assertRedirect();
    }

    // ── Hesabı kapatma ──

    public function test_closing_needs_the_current_password(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->delete('/tr/hesabim/veriler/hesabi-kapat', ['password' => 'yanlis'])
            ->assertSessionHasErrors('password');

        $this->assertNotSoftDeleted('users', ['id' => $user->getKey()]);
    }

    public function test_closing_soft_deletes_the_account_and_signs_the_person_out(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->delete('/tr/hesabim/veriler/hesabi-kapat', ['password' => 'sifre-123456'])
            ->assertRedirect(route('home'));

        $this->assertSoftDeleted('users', ['id' => $user->getKey()]);

        // Oturumun gerçekten kapandığı bir sonraki istekte görülüyor: aynı
        // süreçte yaşayan test guard'ı, isteğin içinde düşürülen oturumu
        // ancak yeni bir istekte yeniden çözüyor.
        $this->get('/tr/hesabim')->assertRedirect();
    }

    public function test_a_closed_account_cannot_sign_in_again(): void
    {
        $user = $this->user();

        $this->actingAs($user)->delete('/tr/hesabim/veriler/hesabi-kapat', ['password' => 'sifre-123456']);

        $this->post('/tr/giris', ['email' => $user->email, 'password' => 'sifre-123456']);

        $this->assertGuest();
    }

    /**
     * Jeton oturum çerezinden farklı: kendiliğinden sona ermiyor. Kapatma
     * yalnız oturumu düşürseydi mobil uygulama aylarca erişmeye devam ederdi.
     */
    public function test_closing_revokes_the_api_tokens(): void
    {
        $user = $this->user();
        $user->createToken('Telefon');

        $this->actingAs($user)->delete('/tr/hesabim/veriler/hesabi-kapat', ['password' => 'sifre-123456']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_closing_clears_the_two_factor_secret(): void
    {
        $user = $this->user();
        app(\App\Services\TwoFactorService::class)->beginSetup($user);

        $this->actingAs($user->fresh())->delete('/tr/hesabim/veriler/hesabi-kapat', ['password' => 'sifre-123456']);

        $row = \Illuminate\Support\Facades\DB::table('users')->where('id', $user->getKey())->first();

        $this->assertNull($row?->two_factor_secret);
    }

    /**
     * Kapanan hesabın adresi serbest kalmalı: kişi fikrini değiştirip aynı
     * adresle yeniden kayıt olabilmeli.
     */
    public function test_the_email_is_free_again_after_closing(): void
    {
        $user = $this->user();

        $this->actingAs($user)->delete('/tr/hesabim/veriler/hesabi-kapat', ['password' => 'sifre-123456']);

        // Kayıt ekranı misafirlere açık; test guard'ı hâlâ eski kullanıcıyı
        // tutuyorsa istek oraya hiç ulaşmaz.
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $this->post('/tr/kayit', [
            'first_name'            => 'Yeni',
            'last_name'             => 'Kullanici',
            'email'                 => $user->email,
            'password'              => 'yeni-sifre-123',
            'password_confirmation' => 'yeni-sifre-123',
        ]);

        $this->assertDatabaseHas('users', ['email' => $user->email, 'first_name' => 'Yeni', 'deleted_at' => null]);
    }

    /**
     * Son yöneticinin kendi hesabını kapatması siteyi yönetilemez bırakırdı.
     */
    public function test_a_staff_account_cannot_be_closed_from_here(): void
    {
        $admin = $this->user('yonetici@example.test');
        $admin->roles()->attach(Role::where('slug', 'admin')->firstOrFail()->id);

        $this->actingAs($admin->fresh())
            ->delete('/tr/hesabim/veriler/hesabi-kapat', ['password' => 'sifre-123456'])
            ->assertSessionHasErrors('password');

        $this->assertNotSoftDeleted('users', ['id' => $admin->getKey()]);
    }
}
