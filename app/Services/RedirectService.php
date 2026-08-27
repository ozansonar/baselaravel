<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RedirectStatus;
use App\Models\Redirect;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class RedirectService
{
    public function paginate(int $perPage = 25, ?array $filters = null): LengthAwarePaginator
    {
        $query = Redirect::query();

        // Çöp kutusu ayrı bir görünüm: silinmiş kayıtlar listeye karışmıyor,
        // istendiğinde yalnız onlar gösteriliyor.
        if (($filters['trashed'] ?? '') === '1') {
            $query->onlyTrashed();
        }

        if ($filters) {
            $this->applyFilters($query, $filters);
        }

        $this->applySort($query, (string) ($filters['sort'] ?? ''));

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Listede seçilebilecek sıralamalar. İstekten gelen değer bu kümeyle
     * sınırlı; serbest bırakılsaydı sütun adı doğrudan sorguya girerdi.
     *
     * @var array<string, string>
     */
    public const SORT_OPTIONS = [
        'recent'    => 'En yeni eklenen',
        'oldest'    => 'İlk eklenen',
        'hits'      => 'En çok kullanılan',
        'last_hit'  => 'En son kullanılan',
        'path'      => 'Yola göre (A-Z)',
    ];

    /**
     * @param \Illuminate\Database\Eloquent\Builder<Redirect> $query
     */
    private function applySort(\Illuminate\Database\Eloquent\Builder $query, string $sort): void
    {
        match ($sort) {
            'oldest'   => $query->orderBy('id'),
            'hits'     => $query->orderByDesc('hit_count')->orderByDesc('id'),
            // Hiç kullanılmamış kayıtların tarihi yok; sona düşsünler.
            'last_hit' => $query->orderByRaw('last_hit_at IS NULL')->orderByDesc('last_hit_at')->orderByDesc('id'),
            'path'     => $query->orderBy('old_url'),
            default    => $query->orderByDesc('id'),
        };
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<Redirect> $query
     */
    private function applyFilters(\Illuminate\Database\Eloquent\Builder $query, array $filters): void
    {
        if (isset($filters['search']) && $filters['search'] !== '') {
            // Joker karakterler düz metin sayılıyor; ESCAPE açıkça bildiriliyor
            // çünkü ters bölüyü kaçış sayan tek veritabanı MySQL.
            $term = '%' . addcslashes((string) $filters['search'], '%_\\') . '%';

            $query->where(function ($q) use ($term): void {
                $q->whereRaw("old_url LIKE ? ESCAPE '\\'", [$term])
                    ->orWhereRaw("new_url LIKE ? ESCAPE '\\'", [$term])
                    ->orWhereRaw("note LIKE ? ESCAPE '\\'", [$term]);
            });
        }

        if (isset($filters['status_code']) && $filters['status_code'] !== '') {
            $query->where('status_code', (int) $filters['status_code']);
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            match ($filters['status']) {
                'active'   => $query->where('is_active', true),
                'inactive' => $query->where('is_active', false),
                default    => null,
            };
        }

        // Hiç kullanılmamış yönlendirme ya yanlış yazılmıştır ya da artık
        // gereksizdir; ikisi de gözden geçirilmeye değer.
        match ($filters['usage'] ?? '') {
            'used'   => $query->where('hit_count', '>', 0),
            'unused' => $query->where('hit_count', 0),
            default  => null,
        };

        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }
    }

    /**
     * @return array{total: int, active: int, total_hits: int, today_hits: int}
     */
    public function getAdminStats(): array
    {
        return Cache::remember('redirects.admin_stats', 300, function (): array {
            $counts = Redirect::selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
                SUM(hit_count) as total_hits
            ")->first();

            $todayHits = Redirect::where('last_hit_at', '>=', today())->sum('hit_count');

            return [
                'total'      => (int) $counts->total,
                'active'     => (int) $counts->active,
                'total_hits' => (int) $counts->total_hits,
                'today_hits' => (int) $todayHits,
            ];
        });
    }

    public function create(array $data): Redirect
    {
        return DB::transaction(function () use ($data): Redirect {
            $redirect = Redirect::create($data);
            $this->clearCache();

            return $redirect;
        });
    }

    public function update(Redirect $redirect, array $data): Redirect
    {
        return DB::transaction(function () use ($redirect, $data): Redirect {
            $redirect->update($data);
            $this->clearCache();

            return $redirect->refresh();
        });
    }

    public function delete(Redirect $redirect): void
    {
        DB::transaction(function () use ($redirect): void {
            $redirect->delete();
            $this->clearCache();
        });
    }

    public function restore(int $id): Redirect
    {
        $redirect = Redirect::withTrashed()->findOrFail($id);
        $redirect->restore();
        $this->clearCache();

        return $redirect;
    }

    public function toggleActive(Redirect $redirect): Redirect
    {
        $redirect->update(['is_active' => !$redirect->is_active]);
        $this->clearCache();

        return $redirect;
    }

    public function createAutoRedirect(
        string $oldUrl,
        string $newUrl,
        RedirectStatus $statusCode = RedirectStatus::MovedPermanently,
    ): void {
        $oldUrl = '/' . ltrim($oldUrl, '/');
        $newUrl = '/' . ltrim($newUrl, '/');

        if ($oldUrl === $newUrl) {
            return;
        }

        Redirect::withTrashed()->updateOrCreate(
            ['old_url' => $oldUrl],
            [
                'new_url'     => $newUrl,
                'status_code' => $statusCode,
                'is_active'   => true,
                'note'        => 'Otomatik oluşturuldu',
                'deleted_at'  => null,
            ],
        );

        $this->clearCache();
    }

    public function clearCache(): void
    {
        Cache::forget('redirects.active');
        Cache::forget('redirects.admin_stats');
    }
}
