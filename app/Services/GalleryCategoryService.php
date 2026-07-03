<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GalleryCategory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class GalleryCategoryService
{
    /**
     * @return Collection<int, GalleryCategory>
     */
    public function allActive(): Collection
    {
        return Cache::remember('gallery_categories.active', 3600, fn () =>
            GalleryCategory::active()
                ->sorted()
                ->withCount('galleryItems')
                ->get(),
        );
    }

    /**
     * @param array<string, mixed>|null $filters
     */
    public function paginate(int $perPage = 15, ?array $filters = null): LengthAwarePaginator
    {
        $query = GalleryCategory::withTrashed()->sorted()->withCount('galleryItems');

        if ($filters) {
            if (isset($filters['status']) && $filters['status'] !== '') {
                $query = match ($filters['status']) {
                    'active'  => $query->whereNull('deleted_at')->where('is_active', true),
                    'passive' => $query->whereNull('deleted_at')->where('is_active', false),
                    'trashed' => $query->whereNotNull('deleted_at'),
                    default   => $query,
                };
            } else {
                $query->whereNull('deleted_at');
            }

            if (isset($filters['search']) && $filters['search'] !== '') {
                $query->where(function ($q) use ($filters): void {
                    $q->where('name', 'like', "%{$filters['search']}%")
                      ->orWhere('slug', 'like', "%{$filters['search']}%");
                });
            }
        } else {
            $query->whereNull('deleted_at');
        }

        return $query->paginate($perPage);
    }

    /**
     * @return array{total: int, active: int, passive: int, trashed: int}
     */
    public function getAdminStats(): array
    {
        return Cache::remember('admin.gallery_categories.stats', 300, function (): array {
            $counts = GalleryCategory::withTrashed()
                ->selectRaw('sum(case when deleted_at is null then 1 else 0 end) as total')
                ->selectRaw('sum(case when deleted_at is null and is_active = 1 then 1 else 0 end) as active')
                ->selectRaw('sum(case when deleted_at is null and is_active = 0 then 1 else 0 end) as passive')
                ->selectRaw('sum(case when deleted_at is not null then 1 else 0 end) as trashed')
                ->first();

            return [
                'total'   => (int) $counts->total,
                'active'  => (int) $counts->active,
                'passive' => (int) $counts->passive,
                'trashed' => (int) $counts->trashed,
            ];
        });
    }

    /**
     * @return array<string, int>
     */
    public function statusCounts(): array
    {
        $counts = GalleryCategory::withTrashed()
            ->selectRaw('SUM(CASE WHEN deleted_at IS NULL AND is_active = 1 THEN 1 ELSE 0 END) as active')
            ->selectRaw('SUM(CASE WHEN deleted_at IS NULL AND is_active = 0 THEN 1 ELSE 0 END) as passive')
            ->selectRaw('SUM(CASE WHEN deleted_at IS NOT NULL THEN 1 ELSE 0 END) as trashed')
            ->first();

        return [
            'active'  => (int) $counts->active,
            'passive' => (int) $counts->passive,
            'trashed' => (int) $counts->trashed,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): GalleryCategory
    {
        return DB::transaction(function () use ($data): GalleryCategory {
            $category = GalleryCategory::create($data);
            $this->clearCache();

            return $category;
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(GalleryCategory $category, array $data): GalleryCategory
    {
        return DB::transaction(function () use ($category, $data): GalleryCategory {
            $category->update($data);
            $this->clearCache();

            return $category->refresh();
        });
    }

    public function delete(GalleryCategory $category): void
    {
        DB::transaction(function () use ($category): void {
            $category->delete();
            $this->clearCache();
        });
    }

    public function restore(int $id): GalleryCategory
    {
        $category = GalleryCategory::withTrashed()->findOrFail($id);
        $category->restore();
        $this->clearCache();

        return $category;
    }

    private function clearCache(): void
    {
        Cache::forget('gallery_categories.active');
        Cache::forget('admin.gallery_categories.stats');
    }
}
