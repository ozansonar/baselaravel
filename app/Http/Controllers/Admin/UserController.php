<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Services\RoleService;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
        private readonly RoleService $roleService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50, 100], true)
            ? (int) $request->input('per_page')
            : 10;

        return view('admin.users.index', [
            'users'        => $this->userService->paginate($perPage, $request->only(['status', 'search', 'role'])),
            'roles'        => $this->roleService->all(),
            'stats'        => $this->userService->getAdminStats(),
            'statusCounts' => $this->userService->getStatusCounts(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('admin.users.create', [
            'roles' => $this->roleService->all(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        DB::transaction(function () use ($request): void {
            $data = $request->safe()->except(['roles', 'avatar', 'password_confirmation']);

            $this->userService->create(
                $data,
                $request->file('avatar'),
                $request->validated('roles'),
            );
        });

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Kullanıcı başarıyla oluşturuldu.');
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('admin.users.edit', [
            'user'  => $user->load('roles'),
            'roles' => $this->roleService->all(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        DB::transaction(function () use ($request, $user): void {
            $data = $request->safe()->except(['roles', 'avatar', 'password', 'password_confirmation', 'remove_avatar']);

            $removeAvatar = $request->input('remove_avatar') === '1';

            $this->userService->update(
                $user,
                $data,
                $request->file('avatar'),
                $request->has('roles') ? ($request->validated('roles') ?? []) : null,
                $request->filled('password') ? $request->validated('password') : null,
                $removeAvatar,
            );
        });

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Kullanıcı başarıyla güncellendi.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $this->userService->delete($user);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Kullanıcı başarıyla silindi.');
    }

    public function restore(int $id): RedirectResponse
    {
        $user = User::withTrashed()->findOrFail($id);
        $this->authorize('restore', $user);

        $this->userService->restore($user);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Kullanıcı başarıyla geri yüklendi.');
    }
}
