<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Panele girebilen biri kendi yetkisini yükseltemez.
 *
 * Bir zamanlar yükseltebiliyordu: `UserPolicy::update` "kendi kaydına
 * dokunabilir" diyordu, kullanıcı düzenleme rotasında izin denetimi yok
 * (yalnız AdminMiddleware) ve form `roles[]` kabul ediyor. Üçü birleşince
 * panele erişimi olan herhangi biri — bir editör, bir moderatör — kendi
 * kaydına tek bir PUT atıp admin rolünü kendine veriyordu.
 *
 * Kendi bilgilerini düzenlemenin yeri ProfileController; o form rol alanı
 * taşımıyor. Buradaki sınavlar iki kapıyı da tutuyor: yükseltme reddedilmeli,
 * ama kendi profilini düzenlemek çalışmaya devam etmeli.
 */
final class PanelUserCannotEscalateItselfTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        $this->seedAuthorization();

        $editor = User::create([
            'first_name' => 'Sınırlı',
            'last_name'  => 'Editör',
            'email'      => 'editor@example.com',
            'password'   => 'password',
            'is_active'  => true,
        ]);

        $editor->roles()->attach(Role::where('slug', 'editor')->firstOrFail());

        return $editor;
    }

    public function test_an_editor_cannot_grant_itself_the_admin_role(): void
    {
        $editor = $this->editor();
        $adminRole = Role::where('slug', 'admin')->firstOrFail();

        $this->actingAs($editor)
            ->put(route('admin.users.update', $editor), [
                'first_name' => 'Sınırlı',
                'last_name'  => 'Editör',
                'email'      => 'editor@example.com',
                'is_active'  => 1,
                'roles'      => [$adminRole->id],
            ])
            ->assertForbidden();

        $this->assertFalse($editor->fresh()->hasRole('admin'));
    }

    /**
     * Başkasının kaydına dokunmak da aynı kapıdan geçiyor.
     */
    public function test_an_editor_cannot_edit_another_user(): void
    {
        $editor = $this->editor();

        $other = User::create([
            'first_name' => 'Başka',
            'last_name'  => 'Kullanıcı',
            'email'      => 'other@example.com',
            'password'   => 'password',
            'is_active'  => true,
        ]);

        $this->actingAs($editor)
            ->put(route('admin.users.update', $other), [
                'first_name' => 'Ele',
                'last_name'  => 'Geçti',
                'email'      => 'other@example.com',
                'is_active'  => 1,
            ])
            ->assertForbidden();

        $this->assertSame('Başka', $other->fresh()->first_name);
    }

    /**
     * Kapı kapanırken kendi profilini düzenleme yolu açık kalmalı.
     */
    public function test_an_editor_can_still_edit_its_own_profile(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)
            ->put(route('admin.profile.update'), [
                'first_name' => 'Yeni',
                'last_name'  => 'Ad',
                'email'      => 'editor@example.com',
            ])
            ->assertRedirect();

        $this->assertSame('Yeni', $editor->fresh()->first_name);
    }

    /**
     * Yetkisi olan hâlâ rol atayabilmeli — kapı herkese kapanmadı.
     */
    public function test_a_user_manager_can_still_assign_roles(): void
    {
        $this->seedAuthorization();

        $admin = User::create([
            'first_name' => 'Yönetici',
            'last_name'  => 'Kullanıcı',
            'email'      => 'admin@example.com',
            'password'   => 'password',
            'is_active'  => true,
        ]);
        $admin->roles()->attach(Role::where('slug', 'admin')->firstOrFail());

        $target = User::create([
            'first_name' => 'Terfi',
            'last_name'  => 'Eden',
            'email'      => 'target@example.com',
            'password'   => 'password',
            'is_active'  => true,
        ]);

        $editorRole = Role::where('slug', 'editor')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.users.update', $target), [
                'first_name' => 'Terfi',
                'last_name'  => 'Eden',
                'email'      => 'target@example.com',
                'is_active'  => 1,
                'roles'      => [$editorRole->id],
            ])
            ->assertRedirect();

        $this->assertTrue($target->fresh()->hasRole('editor'));
    }
}
