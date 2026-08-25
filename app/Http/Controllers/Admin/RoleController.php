<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\SyncPermissionMatrixRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Models\Role;
use App\Services\RoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class RoleController extends Controller
{
    public function __construct(
        private readonly RoleService $roleService,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', Role::class);

        return view('admin.roles.index', [
            'roles'       => $this->roleService->allWithCounts(),
            'matrix'      => $this->roleService->permissionMatrix(),
            'stats'       => $this->roleService->getAdminStats(),
            'roleService' => $this->roleService,
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $this->authorize('create', Role::class);

        $this->roleService->create($request->validated());

        return redirect()->route('admin.roles.index')
            ->with('success', 'Rol oluşturuldu.');
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $this->authorize('update', $role);

        $this->roleService->update($role, $request->validated());

        return redirect()->route('admin.roles.index')
            ->with('success', 'Rol güncellendi.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        if ($this->roleService->isSystemRole($role)) {
            return redirect()->route('admin.roles.index')
                ->with('error', 'Sistem rolleri silinemez.');
        }

        $this->roleService->delete($role);

        return redirect()->route('admin.roles.index')
            ->with('success', 'Rol silindi.');
    }

    /**
     * Save the whole permission matrix in one submit.
     */
    public function syncPermissions(SyncPermissionMatrixRequest $request): RedirectResponse
    {
        $this->authorize('managePermissions', Role::class);

        $this->roleService->syncMatrix($request->validated('permissions', []));

        return redirect()->route('admin.roles.index')
            ->with('success', 'İzinler güncellendi.');
    }
}
