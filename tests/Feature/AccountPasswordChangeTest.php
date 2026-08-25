<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Changing a password has to prove ownership of the current one, otherwise a
 * hijacked session can lock the real owner out of the account.
 */
class AccountPasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create([
            'first_name' => 'Deneme',
            'last_name'  => 'Kullanici',
            'email'      => 'hesap@example.test',
            'password'   => 'eski-sifre-123',
            'is_active'  => true,
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Deneme',
            'last_name'  => 'Kullanici',
            'email'      => 'hesap@example.test',
        ], $overrides);
    }

    public function test_password_changes_when_the_current_one_is_correct(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->put('/hesabim/profil', $this->payload([
                'current_password'      => 'eski-sifre-123',
                'password'              => 'yeni-sifre-456',
                'password_confirmation' => 'yeni-sifre-456',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertTrue(Hash::check('yeni-sifre-456', $user->fresh()->password));
    }

    public function test_password_does_not_change_without_the_current_one(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->from('/hesabim/profil')
            ->put('/hesabim/profil', $this->payload([
                'password'              => 'yeni-sifre-456',
                'password_confirmation' => 'yeni-sifre-456',
            ]))
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('eski-sifre-123', $user->fresh()->password));
    }

    public function test_password_does_not_change_when_the_current_one_is_wrong(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->from('/hesabim/profil')
            ->put('/hesabim/profil', $this->payload([
                'current_password'      => 'yanlis-sifre',
                'password'              => 'yeni-sifre-456',
                'password_confirmation' => 'yeni-sifre-456',
            ]))
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('eski-sifre-123', $user->fresh()->password));
    }

    public function test_the_new_password_must_differ_from_the_current_one(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->from('/hesabim/profil')
            ->put('/hesabim/profil', $this->payload([
                'current_password'      => 'eski-sifre-123',
                'password'              => 'eski-sifre-123',
                'password_confirmation' => 'eski-sifre-123',
            ]))
            ->assertSessionHasErrors('password');
    }

    /**
     * The password section is optional; editing only the profile fields must
     * keep working without touching it.
     */
    public function test_profile_can_be_updated_without_touching_the_password(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->put('/hesabim/profil', $this->payload(['first_name' => 'Güncel']))
            ->assertSessionHasNoErrors();

        $fresh = $user->fresh();

        $this->assertSame('Güncel', $fresh->first_name);
        $this->assertTrue(Hash::check('eski-sifre-123', $fresh->password));
    }

    public function test_the_form_asks_for_the_current_password(): void
    {
        $html = $this->actingAs($this->user())->get('/hesabim/profil')->getContent();

        $this->assertStringContainsString('name="current_password"', $html);
    }

    /**
     * The admin panel has its own profile screen; an admin account is the more
     * valuable target, so it gets the same protection.
     */
    public function test_admin_profile_also_requires_the_current_password(): void
    {
        $role = \App\Models\Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        $admin = $this->user();
        $admin->roles()->attach($role);

        $payload = [
            'first_name'            => 'Deneme',
            'last_name'             => 'Kullanici',
            'email'                 => 'hesap@example.test',
            'password'              => 'yeni-sifre-456',
            'password_confirmation' => 'yeni-sifre-456',
        ];

        $this->actingAs($admin)
            ->from('/admin/profile')
            ->put('/admin/profile', $payload)
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('eski-sifre-123', $admin->fresh()->password));

        $this->actingAs($admin)
            ->put('/admin/profile', $payload + ['current_password' => 'eski-sifre-123'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('yeni-sifre-456', $admin->fresh()->password));
    }

    public function test_admin_profile_form_asks_for_the_current_password(): void
    {
        $role = \App\Models\Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        $admin = $this->user();
        $admin->roles()->attach($role);

        $html = $this->actingAs($admin)->get('/admin/profile')->getContent();

        $this->assertStringContainsString('name="current_password"', $html);
    }
}
