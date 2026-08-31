<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exceptions\EmailAlreadyTakenException;
use App\Http\Controllers\Admin\Concerns\ReturnsToList;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkUserRequest;
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
    use ReturnsToList;

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
            'users'        => $this->userService->paginate($perPage, $request->only($this->userService->filterKeys())),
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

        // Adres değişirse doğrulama damgası düşüyor ve yeni adrese doğrulama
        // maili gidiyor (UserObserver). Yöneticinin bunu bilmesi gerekiyor:
        // kullanıcı kendisine sorulmadan doğrulanmamış duruma geçiyor.
        $emailChanged = $request->validated('email') !== $user->email;

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
            ->with('success', $emailChanged
                ? 'Kullanıcı güncellendi. E-posta adresi değiştiği için doğrulama durumu sıfırlandı ve yeni adrese doğrulama bağlantısı gönderildi.'
                : 'Kullanıcı başarıyla güncellendi.');
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

        try {
            $this->userService->restore($user);
        } catch (EmailAlreadyTakenException $e) {
            // Adres silindikten sonra serbest kalıyor ve başkası alabiliyor.
            // Yakalanmasaydı yönetici ham bir veritabanı hatası görürdü.
            return redirect()
                ->route('admin.users.index')
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Kullanıcı başarıyla geri yüklendi.');
    }

    /**
     * Listede seçilen kullanıcıları tek seferde siler.
     */
    public function bulkDestroy(BulkUserRequest $request): RedirectResponse
    {
        $this->authorize('delete', new User());

        $silinen = $this->userService->deleteMany($request->ids(), $request->user()?->id);

        return $this->backToList($request, 'admin.users.index')->with(
            $silinen > 0 ? 'success' : 'error',
            $silinen > 0 ? "{$silinen} kullanıcı silindi." : 'Hiçbir kullanıcı silinemedi.',
        );
    }

    /**
     * Çöpteki kullanıcıları tek seferde geri yükler.
     */
    public function bulkRestore(BulkUserRequest $request): RedirectResponse
    {
        $this->authorize('restore', new User());

        $istenen = count($request->ids());
        $geriYuklenen = $this->userService->restoreMany($request->ids());
        $atlanan = $istenen - $geriYuklenen;

        // Atlananlar sessizce yutulmuyor: adresi bu arada başkasına geçmiş bir
        // kullanıcı geri yüklenemez ve yönetici bunu bilmeli.
        $mesaj = $geriYuklenen > 0
            ? "{$geriYuklenen} kullanıcı geri yüklendi."
            : 'Hiçbir kullanıcı geri yüklenemedi.';

        if ($atlanan > 0) {
            $mesaj .= " {$atlanan} kullanıcı atlandı: e-posta adresleri silindikten sonra başka hesaplara verilmiş.";
        }

        return $this->backToList($request, 'admin.users.index')->with(
            $geriYuklenen > 0 ? 'success' : 'error',
            $mesaj,
        );
    }
}
