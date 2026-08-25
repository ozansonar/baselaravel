<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\VerifyEmailMail;
use App\Models\MailTemplate;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Registration hands out an account, the verification link proves the address
 * belongs to whoever registered, and the account area stays closed until then.
 */
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
    }

    private function unverifiedUser(): User
    {
        return User::factory()->unverified()->create();
    }

    private function verificationUrl(User $user): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addHour(),
            ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())],
        );
    }

    public function test_the_account_area_is_closed_until_the_address_is_verified(): void
    {
        $this->actingAs($this->unverifiedUser())
            ->get('/hesabim')
            ->assertRedirect(route('verification.notice'));
    }

    public function test_a_verified_user_reaches_the_account_area(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/hesabim')
            ->assertOk();
    }

    public function test_the_notice_page_is_shown_to_an_unverified_user(): void
    {
        $user = $this->unverifiedUser();

        $this->actingAs($user)
            ->get(route('verification.notice'))
            ->assertOk()
            ->assertSee($user->email);
    }

    public function test_the_notice_page_sends_a_verified_user_onwards(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('verification.notice'))
            ->assertRedirect(route('account.dashboard'));
    }

    public function test_a_valid_signed_link_verifies_the_address(): void
    {
        $user = $this->unverifiedUser();

        $this->assertFalse($user->hasVerifiedEmail());

        $this->actingAs($user)
            ->get($this->verificationUrl($user))
            ->assertRedirect(route('account.dashboard'));

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_a_tampered_link_does_not_verify_the_address(): void
    {
        $user = $this->unverifiedUser();

        $this->actingAs($user)
            ->get($this->verificationUrl($user) . 'bozuk')
            ->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_another_users_link_does_not_verify_the_address(): void
    {
        $user = $this->unverifiedUser();
        $other = $this->unverifiedUser();

        $this->actingAs($user)
            ->get($this->verificationUrl($other))
            ->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_the_link_can_be_requested_again(): void
    {
        Mail::fake();

        $user = $this->unverifiedUser();

        $this->actingAs($user)
            ->from(route('verification.notice'))
            ->post(route('verification.send'))
            ->assertRedirect(route('verification.notice'));

        Mail::assertQueued(VerifyEmailMail::class);
    }

    public function test_registration_sends_the_verification_mail(): void
    {
        Mail::fake();

        Role::firstOrCreate(['slug' => 'user'], ['name' => 'Kullanıcı']);
        Setting::setValue('registration_enabled', '1');

        $this->post('/kayit', [
            'first_name'            => 'Yeni',
            'last_name'             => 'Uye',
            'email'                 => 'yeni-uye@example.test',
            'phone'                 => '05001112233',
            'password'              => 'gizli-sifre-123',
            'password_confirmation' => 'gizli-sifre-123',
        ])->assertRedirect(route('verification.notice'));

        $registered = User::where('email', 'yeni-uye@example.test')->first();

        $this->assertNotNull($registered);
        $this->assertFalse($registered->hasVerifiedEmail());

        Mail::assertQueued(VerifyEmailMail::class);
    }

    /**
     * The mail goes through the project's own template system rather than
     * Laravel's built-in notification, so the template has to exist.
     */
    public function test_the_verification_template_is_available_and_renders(): void
    {
        $template = MailTemplate::where('key', 'verify_email')->first();

        $this->assertNotNull($template, 'verify_email şablonu seed edilmemiş');

        $user = $this->unverifiedUser();
        $html = (new VerifyEmailMail($user, 'https://example.test/dogrula/1/abc'))->render();

        $this->assertStringContainsString('https://example.test/dogrula/1/abc', $html);
        $this->assertStringNotContainsString('{verification_url}', $html);
        $this->assertStringNotContainsString('{user_name}', $html);
    }
}
