<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Redirect;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A redirect target must not be usable to bounce visitors off the site.
 *
 * HandleRedirects feeds new_url straight into redirect(), so anything that
 * survives validation is somewhere a visitor can be sent.
 */
class RedirectTargetValidationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);

        $user = User::create([
            'first_name' => 'Site',
            'last_name'  => 'Admin',
            'email'      => 'redirect-admin@example.test',
            'password'   => 'password',
            'is_active'  => true,
        ]);

        $user->roles()->attach($role);

        return $user;
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function offSiteTargets(): array
    {
        return [
            'mutlak harici adres'   => ['https://evil.test/phishing'],
            'protokole bağlı (//)'  => ['//evil.test/phishing'],
            'ters bölü hilesi'      => ['/\\evil.test'],
            'javascript şeması'     => ['javascript:alert(1)'],
            'data şeması'           => ['data:text/html,<script>alert(1)</script>'],
            'satır sonu kaçırma'    => ["/ok\nhttps://evil.test"],
        ];
    }

    #[DataProvider('offSiteTargets')]
    public function test_off_site_targets_are_rejected(string $target): void
    {
        $this->actingAs($this->admin())
            ->from('/admin/redirects')
            ->post('/admin/redirects', [
                'old_url'     => '/eski-sayfa',
                'new_url'     => $target,
                'status_code' => 301,
            ])
            ->assertSessionHasErrors('new_url');

        $this->assertDatabaseCount('redirects', 0);
    }

    public function test_internal_path_is_accepted(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/redirects', [
                'old_url'     => '/eski-sayfa',
                'new_url'     => '/yeni-sayfa',
                'status_code' => 301,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('redirects', ['new_url' => '/yeni-sayfa']);
    }

    public function test_the_applications_own_host_is_accepted(): void
    {
        $appUrl = rtrim((string) config('app.url'), '/');

        $this->actingAs($this->admin())
            ->post('/admin/redirects', [
                'old_url'     => '/eski-sayfa',
                'new_url'     => $appUrl . '/yeni-sayfa',
                'status_code' => 301,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('redirects', 1);
    }

    public function test_an_explicitly_allowed_host_is_accepted(): void
    {
        config()->set('redirects.allowed_hosts', ['eski-alan-adi.test']);

        $this->actingAs($this->admin())
            ->post('/admin/redirects', [
                'old_url'     => '/eski-sayfa',
                'new_url'     => 'https://eski-alan-adi.test/yeni-sayfa',
                'status_code' => 301,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('redirects', 1);
    }

    public function test_a_404_redirect_needs_no_target(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/redirects', [
                'old_url'     => '/kaldirilan-sayfa',
                'new_url'     => null,
                'status_code' => 410,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('redirects', [
            'old_url'     => '/kaldirilan-sayfa',
            'status_code' => 410,
        ]);
    }

    public function test_a_stored_redirect_still_sends_visitors_to_the_target(): void
    {
        Redirect::create([
            'old_url'     => '/tasinan-sayfa',
            'new_url'     => '/yeni-adres',
            'status_code' => 301,
            'is_active'   => true,
        ]);

        $this->get('/tasinan-sayfa')->assertRedirect('/yeni-adres');
    }
}
