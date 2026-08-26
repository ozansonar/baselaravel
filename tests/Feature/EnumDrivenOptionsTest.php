<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AppTimezone;
use App\Enums\AuditEvent;
use App\Enums\MailEncryption;
use App\Enums\RedirectStatus;
use App\Enums\TelegramNotifyLevel;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Fixed option lists must be rendered from an enum, never typed into a Blade
 * file (CLAUDE.md / laravel skill).
 *
 * These assertions fail the moment somebody adds a case to an enum but forgets
 * the screen, or hardcodes a list next to one.
 */
class EnumDrivenOptionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
    }

    private function admin(): User
    {
        $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);

        $user = User::create([
            'first_name' => 'Enum',
            'last_name'  => 'Tester',
            'email'      => 'enum@example.test',
            'password'   => 'password',
            'is_active'  => true,
        ]);

        $user->roles()->attach($role);

        return $user;
    }

    /**
     * @return array<string, array{0: string, 1: class-string<\BackedEnum>}>
     */
    public static function screens(): array
    {
        return [
            'ayarlar → mail şifreleme'    => ['/admin/settings', MailEncryption::class],
            'ayarlar → telegram seviyesi' => ['/admin/settings', TelegramNotifyLevel::class],
            'ayarlar → saat dilimi'       => ['/admin/settings', AppTimezone::class],
            'yönlendirmeler → durum kodu' => ['/admin/redirects', RedirectStatus::class],
            'aktivite → olay filtresi'    => ['/admin/aktivite-loglari', AuditEvent::class],
        ];
    }

    /**
     * @param class-string<\BackedEnum> $enum
     */
    #[DataProvider('screens')]
    public function test_every_enum_case_reaches_the_screen(string $route, string $enum): void
    {
        $html = $this->actingAs($this->admin())->get($route)->getContent();

        foreach ($enum::cases() as $case) {
            $this->assertStringContainsString(
                'value="' . $case->value . '"',
                $html,
                sprintf('%s::%s seçeneği %s ekranında yok', class_basename($enum), $case->name, $route),
            );
        }
    }

    public function test_redirect_options_carry_the_behaviour_flags_the_script_reads(): void
    {
        $html = $this->actingAs($this->admin())->get('/admin/redirects')->getContent();

        // redirects.js decides whether to hide the target field from these
        // attributes instead of keeping its own list of status codes.
        foreach (RedirectStatus::cases() as $case) {
            $this->assertStringContainsString(
                'data-redirects="' . ($case->redirectsSomewhere() ? '1' : '0') . '"',
                $html,
            );
        }

        $this->assertStringContainsString('data-description=', $html);
    }

    /**
     * Role slugs live in the UserRole enum; the seeder and AdminMiddleware read
     * from it rather than repeating the list.
     */
    public function test_roles_are_seeded_from_the_user_role_enum(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);

        foreach (\App\Enums\UserRole::cases() as $role) {
            $this->assertDatabaseHas('roles', [
                'slug'        => $role->value,
                'name'        => $role->label(),
                'description' => $role->description(),
            ]);
        }

        $this->assertSame(
            ['admin', 'editor', 'moderator'],
            \App\Enums\UserRole::adminPanelSlugs(),
        );
    }

    public function test_timezone_offsets_are_computed_not_hardcoded(): void
    {
        // Europe/Istanbul has no daylight saving, so this one is stable.
        $this->assertSame('UTC+3', AppTimezone::EuropeIstanbul->utcOffset());
        $this->assertSame('Europe/Istanbul (UTC+3)', AppTimezone::EuropeIstanbul->label());

        // Half-hour offsets must not be rendered as "UTC+5".
        $this->assertSame('UTC+5:30', AppTimezone::AsiaKolkata->utcOffset());
    }
}
