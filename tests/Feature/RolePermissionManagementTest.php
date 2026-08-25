<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PermissionKey;
use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Roles carry permissions in the database, so what a role can do is changed
 * from this screen rather than by editing a policy.
 */
class RolePermissionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', $slug)->firstOrFail());

        return $user;
    }

    public function test_every_permission_in_the_enum_exists_in_the_database(): void
    {
        foreach (PermissionKey::cases() as $case) {
            $this->assertDatabaseHas('permissions', [
                'key'   => $case->value,
                'name'  => $case->label(),
                'group' => $case->group()->value,
            ]);
        }

        $this->assertSame(count(PermissionKey::cases()), Permission::count());
    }

    public function test_the_screen_lists_every_role_and_permission(): void
    {
        $html = $this->actingAs($this->userWithRole('admin'))->get('/admin/roller')->getContent();

        foreach (Role::all() as $role) {
            $this->assertStringContainsString($role->name, $html, "{$role->slug} rolü ekranda yok");
        }

        foreach (PermissionKey::cases() as $case) {
            $this->assertStringContainsString(
                'value="' . $case->value . '"',
                $html,
                "{$case->value} izni matriste yok",
            );
        }
    }

    public function test_seeded_roles_keep_the_abilities_they_had_before(): void
    {
        $admin = $this->userWithRole('admin');
        $editor = $this->userWithRole('editor');
        $moderator = $this->userWithRole('moderator');

        $this->assertTrue($admin->hasPermission(PermissionKey::SettingsManage));
        $this->assertTrue($admin->hasPermission(PermissionKey::PagesDelete));

        $this->assertTrue($editor->hasPermission(PermissionKey::PagesManage));
        $this->assertFalse($editor->hasPermission(PermissionKey::PagesDelete));
        $this->assertFalse($editor->hasPermission(PermissionKey::SettingsView));

        $this->assertTrue($moderator->hasPermission(PermissionKey::CommentsModerate));
        $this->assertFalse($moderator->hasPermission(PermissionKey::PagesView));
    }

    public function test_granting_a_permission_takes_effect_immediately(): void
    {
        $editor = $this->userWithRole('editor');

        // Before: the editor is refused the settings screen.
        $this->actingAs($editor)->get('/admin/settings')->assertForbidden();

        $this->actingAs($this->userWithRole('admin'))
            ->put('/admin/roller/izinler', [
                'permissions' => [
                    UserRole::Editor->value => [
                        PermissionKey::SettingsView->value,
                        PermissionKey::PagesView->value,
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // After: the same user reaches it without a code change.
        $this->actingAs($editor->fresh())->get('/admin/settings')->assertOk();
    }

    public function test_revoking_a_permission_closes_the_screen(): void
    {
        $editor = $this->userWithRole('editor');

        $this->actingAs($editor)->get('/admin/pages')->assertOk();

        $this->actingAs($this->userWithRole('admin'))
            ->put('/admin/roller/izinler', ['permissions' => [UserRole::Editor->value => []]])
            ->assertRedirect();

        $this->actingAs($editor->fresh())->get('/admin/pages')->assertForbidden();
    }

    /**
     * Letting an administrator strip the admin role would lock everyone out of
     * the panel, so that role is always given the full set.
     */
    public function test_the_admin_role_cannot_lose_its_permissions(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->put('/admin/roller/izinler', ['permissions' => [UserRole::Admin->value => []]])
            ->assertRedirect();

        $adminRole = Role::where('slug', UserRole::Admin->value)->firstOrFail();

        $this->assertSame(count(PermissionKey::cases()), $adminRole->permissions()->count());
        $this->assertTrue($admin->fresh()->hasPermission(PermissionKey::SettingsManage));
    }

    public function test_an_unknown_permission_key_is_rejected(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->from('/admin/roller')
            ->put('/admin/roller/izinler', [
                'permissions' => [UserRole::Editor->value => ['uydurma.izin']],
            ])
            ->assertSessionHasErrors();
    }

    public function test_a_custom_role_can_be_created_and_given_permissions(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post('/admin/roller', [
                'name'        => 'Denetçi',
                'slug'        => 'denetci',
                'description' => 'Yalnızca raporları görür',
            ])
            ->assertSessionHasNoErrors();

        $role = Role::where('slug', 'denetci')->firstOrFail();

        $this->actingAs($admin)
            ->put('/admin/roller/izinler', [
                'permissions' => ['denetci' => [PermissionKey::AnalyticsView->value]],
            ])
            ->assertRedirect();

        $auditor = User::factory()->create();
        $auditor->roles()->attach($role);

        $this->assertTrue($auditor->hasPermission(PermissionKey::AnalyticsView));
        $this->actingAs($auditor)->get('/admin/analytics')->assertOk();
        $this->actingAs($auditor)->get('/admin/settings')->assertForbidden();
    }

    public function test_a_system_role_cannot_be_deleted(): void
    {
        $admin = $this->userWithRole('admin');
        $editorRole = Role::where('slug', UserRole::Editor->value)->firstOrFail();

        $this->actingAs($admin)
            ->delete("/admin/roller/{$editorRole->id}")
            ->assertRedirect();

        $this->assertNotSoftDeleted('roles', ['id' => $editorRole->id]);
    }

    public function test_a_custom_role_can_be_deleted(): void
    {
        $admin = $this->userWithRole('admin');

        $role = Role::create(['name' => 'Geçici', 'slug' => 'gecici', 'description' => null]);

        $this->actingAs($admin)
            ->delete("/admin/roller/{$role->id}")
            ->assertRedirect();

        $this->assertSoftDeleted('roles', ['id' => $role->id]);
    }

    public function test_a_duplicate_role_key_is_rejected(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->from('/admin/roller')
            ->post('/admin/roller', ['name' => 'Kopya', 'slug' => UserRole::Editor->value])
            ->assertSessionHasErrors('slug');
    }

    public function test_an_editor_cannot_reach_the_roles_screen(): void
    {
        $this->actingAs($this->userWithRole('editor'))
            ->get('/admin/roller')
            ->assertForbidden();

        $this->actingAs($this->userWithRole('editor'))
            ->from('/admin/roller')
            ->put('/admin/roller/izinler', ['permissions' => []])
            ->assertForbidden();
    }

    /**
     * Panel access now follows from holding any permission at all, so a role
     * with none is kept out entirely.
     */
    public function test_a_role_without_permissions_cannot_reach_the_panel(): void
    {
        $this->actingAs($this->userWithRole('user'))
            ->get('/admin')
            ->assertForbidden();
    }
}
