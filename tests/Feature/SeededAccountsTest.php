<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The demo accounts are the first thing a cloned project exposes.
 *
 * Their password comes from SEED_PASSWORD so each deployment can set its own;
 * a base kit that shipped one fixed password would hand every project derived
 * from it the same known credentials.
 */
class SeededAccountsTest extends TestCase
{
    use RefreshDatabase;

    private function seedUsers(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(UserSeeder::class);
    }

    public function test_the_seeded_password_comes_from_configuration(): void
    {
        config(['seeding.password' => 'Ortama*Ozel99.']);

        $this->seedUsers();

        $admin = User::where('email', 'admin@example.com')->firstOrFail();

        $this->assertTrue(
            Hash::check('Ortama*Ozel99.', $admin->password),
            'Seeder yapılandırmadaki şifreyi kullanmıyor',
        );
    }

    public function test_every_demo_account_gets_the_same_configured_password(): void
    {
        config(['seeding.password' => 'Ortama*Ozel99.']);

        $this->seedUsers();

        foreach (['admin@example.com', 'editor@example.com', 'user@example.com'] as $email) {
            $user = User::where('email', $email)->firstOrFail();

            $this->assertTrue(Hash::check('Ortama*Ozel99.', $user->password), "{$email} şifresi farklı");
        }
    }

    /**
     * The password is hashed by the model cast; a plain-text column would mean
     * the seeded accounts are readable straight from the database.
     */
    public function test_the_seeded_password_is_stored_hashed(): void
    {
        config(['seeding.password' => 'Ortama*Ozel99.']);

        $this->seedUsers();

        $admin = User::where('email', 'admin@example.com')->firstOrFail();

        $this->assertNotSame('Ortama*Ozel99.', $admin->password);
        $this->assertStringStartsWith('$2y$', $admin->password);
    }

    /**
     * An empty SEED_PASSWORD must not seed accounts with a blank password —
     * config/seeding.php uses ?: rather than a default argument for exactly
     * this reason, since env("X") on "X=" returns an empty string.
     */
    public function test_an_empty_setting_falls_back_instead_of_seeding_a_blank_password(): void
    {
        $this->assertNotSame('', (string) config('seeding.password'));
        $this->assertSame('Demo*12345.', (string) config('seeding.password'));
    }

    public function test_the_shipped_default_is_not_the_old_weak_password(): void
    {
        $this->assertNotSame('password', (string) config('seeding.password'));
    }

    /**
     * Re-running the seeder is how a deployment rotates the demo password, so
     * it has to update the existing rows rather than skip them.
     */
    public function test_reseeding_updates_the_password_of_existing_accounts(): void
    {
        config(['seeding.password' => 'Ilk*Sifre11.']);
        $this->seedUsers();

        config(['seeding.password' => 'Ikinci*Sifre22.']);
        $this->seed(UserSeeder::class);

        $admin = User::where('email', 'admin@example.com')->firstOrFail();

        $this->assertTrue(Hash::check('Ikinci*Sifre22.', $admin->password), 'Yeniden seed şifreyi güncellemedi');
        $this->assertSame(1, User::where('email', 'admin@example.com')->count(), 'Yeniden seed kullanıcıyı kopyaladı');
    }

    public function test_the_seeded_admin_can_sign_in(): void
    {
        config(['seeding.password' => 'Ortama*Ozel99.']);
        $this->seedUsers();

        $this->post(route('login'), [
            'email'    => 'admin@example.com',
            'password' => 'Ortama*Ozel99.',
        ])->assertRedirect();

        $this->assertAuthenticated();
    }

    public function test_the_seeded_admin_reaches_the_panel(): void
    {
        config(['seeding.password' => 'Ortama*Ozel99.']);
        $this->seedUsers();

        $admin = User::where('email', 'admin@example.com')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    }
}
