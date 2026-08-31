<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\EmailAlreadyTakenException;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use App\Observers\DashboardStatsObserver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class UserService
{
    public function __construct(
        private readonly RoleService $roleService,
        private readonly UploadService $uploadService,
        private readonly SessionRevoker $sessionRevoker,
    ) {}

    // ── Admin Stats ──

    /**
     * @return array{total: int, active: int, new_this_month: int, inactive: int}
     */
    public function getAdminStats(): array
    {
        return Cache::remember('admin_user_stats', 300, function (): array {
            $startOfMonth = Carbon::now()->startOfMonth();

            $counts = User::withTrashed()->selectRaw("
                SUM(CASE WHEN deleted_at IS NULL THEN 1 ELSE 0 END) as total,
                SUM(CASE WHEN deleted_at IS NULL AND is_active = 1 THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN deleted_at IS NULL AND created_at >= ? THEN 1 ELSE 0 END) as new_this_month,
                SUM(CASE WHEN deleted_at IS NULL AND is_active = 0 THEN 1 ELSE 0 END) as inactive
            ", [$startOfMonth])->first();

            return [
                'total'          => (int) $counts->total,
                'active'         => (int) $counts->active,
                'new_this_month' => (int) $counts->new_this_month,
                'inactive'       => (int) $counts->inactive,
            ];
        });
    }

    /**
     * @return array{all: int, active: int, inactive: int, trashed: int}
     */
    public function getStatusCounts(): array
    {
        $counts = User::withTrashed()
            ->selectRaw('sum(case when deleted_at is null then 1 else 0 end) as total')
            ->selectRaw('sum(case when deleted_at is null and is_active = 1 then 1 else 0 end) as active')
            ->selectRaw('sum(case when deleted_at is null and is_active = 0 then 1 else 0 end) as inactive')
            ->selectRaw('sum(case when deleted_at is not null then 1 else 0 end) as trashed')
            ->first();

        return [
            'all'      => (int) $counts->total,
            'active'   => (int) $counts->active,
            'inactive' => (int) $counts->inactive,
            'trashed'  => (int) $counts->trashed,
        ];
    }

    // ── Admin Paginate ──

    /**
     * Liste ekranının tanıdığı süzgeç anahtarları.
     *
     * Ekran da dışa aktarma da bu listeyi okur; anahtarlar iki yerde ayrı ayrı
     * yazılsaydı zamanla dosyaya inen ile ekranda görünen ayrışırdı.
     *
     * @return list<string>
     */
    public function filterKeys(): array
    {
        return ['status', 'search', 'role'];
    }

    /**
     * Süzgeçler uygulanmış, sayfalanmamış sorgu.
     *
     * @param array<string, mixed> $filters
     * @return Builder<User>
     */
    public function query(array $filters = []): Builder
    {
        $query = User::with('roles')->latest();

        if (isset($filters['status'])) {
            match ($filters['status']) {
                'active'  => $query->where('is_active', true),
                'inactive' => $query->where('is_active', false),
                'trashed' => $query->onlyTrashed(),
                default   => null,
            };
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['role'])) {
            $query->whereHas('roles', function ($q) use ($filters): void {
                $q->where('slug', $filters['role']);
            });
        }

        return $query;
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function paginate(int $perPage = 10, array $filters = []): LengthAwarePaginator
    {
        return $this->query($filters)->paginate($perPage)->withQueryString();
    }

    // ── CRUD ──

    /**
     * @param array<string, mixed> $data
     * @param \Illuminate\Http\UploadedFile|null $avatar
     * @param array<int>|null $roles
     */
    public function create(array $data, $avatar = null, ?array $roles = null): User
    {
        if ($avatar) {
            $data['avatar'] = $this->uploadService->uploadImage(
                $avatar,
                'users',
                $data['first_name'] . '-' . $data['last_name'],
            );
        }

        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);

        if ($roles) {
            $this->roleService->syncUserRoles($user, $roles);
        }

        Cache::forget('admin_user_stats');

        return $user;
    }

    /**
     * @param array<string, mixed> $data
     * @param \Illuminate\Http\UploadedFile|null $avatar
     * @param array<int>|null $roles
     */
    public function update(User $user, array $data, $avatar = null, ?array $roles = null, ?string $password = null, bool $removeAvatar = false): User
    {
        if ($removeAvatar && !$avatar && $user->avatar) {
            $this->uploadService->deleteImage($user->avatar);
            $data['avatar'] = null;
        } elseif ($avatar) {
            $data['avatar'] = $this->uploadService->replaceImage(
                $avatar,
                'users',
                ($data['first_name'] ?? $user->first_name) . '-' . ($data['last_name'] ?? $user->last_name),
                $user->avatar,
            );
        }

        if ($password) {
            $data['password'] = Hash::make($password);
        }

        $user->update($data);

        if ($roles !== null) {
            $this->roleService->syncUserRoles($user, $roles);
        }

        Cache::forget('admin_user_stats');

        return $user;
    }

    public function delete(User $user): void
    {
        $user->delete();
        Cache::forget('admin_user_stats');
    }

    /**
     * Listede seçilen kullanıcıları tek seferde siler.
     *
     * Önce tarayıcı her kayıt için ayrı bir istek atıyordu; elli kullanıcı
     * elli istek demekti ve yarısı düşerse ortada karışık bir sonuç kalıyordu.
     * Tek istek, tek işlem.
     *
     * Kişi kendini silemez: oturumu açık olan kullanıcı listede seçili olsa
     * bile atlanıyor, yoksa yönetici kendi erişimini kapatabilirdi.
     *
     * @param  list<int> $ids
     * @return int       silinen kullanıcı sayısı
     */
    public function deleteMany(array $ids, ?int $exceptId = null): int
    {
        $ids = array_values(array_diff($ids, $exceptId === null ? [] : [$exceptId]));

        if ($ids === []) {
            return 0;
        }

        $silinen = DB::transaction(fn (): int => User::whereIn('id', $ids)->delete());

        if ($silinen > 0) {
            // Toplu silme sorgu kurucusundan gidiyor, model olayı doğmuyor:
            // panonun önbelleğini gözlemci değil bu satır düşürüyor. Açık
            // oturumları kapatan gözlemci de aynı sebeple devre dışı, o iş de
            // buradan çağrılıyor — yoksa toplu silinen kullanıcı panelde
            // kalmaya devam ederdi.
            $this->sessionRevoker->revokeMany($ids);

            Cache::forget('admin_user_stats');
            Cache::forget(DashboardStatsObserver::CACHE_KEY);
        }

        return $silinen;
    }

    /**
     * Seçilen kullanıcıları çöpten tek seferde çıkarır.
     *
     * @param  list<int> $ids
     * @return int       geri yüklenen kullanıcı sayısı
     */
    public function restoreMany(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        // Adresi bu arada başkasına geçenler atlanıyor. Geri yüklenselerdi iki
        // canlı kullanıcı aynı adrese binerdi; veritabanı zaten reddeder ama
        // toplu işlemin tamamı tek bir çakışma yüzünden düşerdi.
        $ids = $this->restorableIds($ids);

        if ($ids === []) {
            return 0;
        }

        $geriYuklenen = DB::transaction(fn (): int => User::onlyTrashed()->whereIn('id', $ids)->restore());

        if ($geriYuklenen > 0) {
            Cache::forget('admin_user_stats');
            Cache::forget(DashboardStatsObserver::CACHE_KEY);
        }

        return $geriYuklenen;
    }

    /**
     * @throws EmailAlreadyTakenException adres bu arada başka bir hesaba geçmişse
     */
    public function restore(User $user): void
    {
        if ($this->emailTakenByAnother($user)) {
            throw EmailAlreadyTakenException::for((string) $user->email);
        }

        $user->restore();
        Cache::forget('admin_user_stats');
    }

    /**
     * Kullanıcının adresini yaşayan başka biri tutuyor mu?
     */
    private function emailTakenByAnother(User $user): bool
    {
        return User::query()
            ->where('email', $user->email)
            ->whereKeyNot($user->getKey())
            ->exists();
    }

    /**
     * Verilen kimliklerden gerçekten geri yüklenebilecek olanlar.
     *
     * İki eleme var. Birincisi adresi yaşayan bir hesaba geçmiş olanlar.
     * İkincisi çöpte aynı adresi paylaşan kayıtlar: aynı adres birden çok kez
     * silinmiş olabilir ve hepsi birden geri yüklenirse bu kez kendi
     * aralarında çakışırlar, o yüzden her adresten yalnız biri geçiyor.
     *
     * @param  list<int> $ids
     * @return list<int>
     */
    private function restorableIds(array $ids): array
    {
        $copteki = User::onlyTrashed()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->pluck('email', 'id');

        if ($copteki->isEmpty()) {
            return [];
        }

        $yasayan = User::query()
            ->whereIn('email', $copteki->values()->unique()->all())
            ->pluck('email')
            ->all();

        return $copteki
            ->reject(fn (?string $email): bool => in_array($email, $yasayan, true))
            ->unique()
            ->keys()
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();
    }
}
