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
    use \App\Services\Concerns\LocalizedCache;

    use \App\Services\Concerns\ListsTranslationGroups;
    use \App\Services\Concerns\SyncsTranslations;

    /**
     * @return Collection<int, GalleryCategory>
     */
    public function allActive(): Collection
    {
        return Cache::remember($this->localeCacheKey('gallery_categories.active'), 3600, fn () =>
            GalleryCategory::active()
                ->localeWithFallback()
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
        $query = $this->onlyGroupRepresentatives(GalleryCategory::withTrashed(), GalleryCategory::class)
            ->sorted()
            // Items hang off the translation they were added under, so the
            // number that means something is the group's total.
            ->selectRaw('gallery_categories.*, ('
                . 'select count(*) from gallery_items'
                . ' where gallery_items.deleted_at is null'
                . ' and gallery_items.gallery_category_id in ('
                . 'select g.id from gallery_categories as g where g.lang_group_id = gallery_categories.lang_group_id'
                . ')) as gallery_items_count');

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
                $this->whereGroupMatches($query, GalleryCategory::class, function ($q) use ($filters): void {
                    $q->where('name', 'like', "%{$filters['search']}%")
                      ->orWhere('slug', 'like', "%{$filters['search']}%");
                });
            }
        } else {
            $query->whereNull('deleted_at');
        }

        return $this->attachGroupLocales($query->paginate($perPage), GalleryCategory::class);
    }

    /**
     * @return array{total: int, active: int, passive: int, trashed: int}
     */
    public function getAdminStats(): array
    {
        return Cache::remember('admin.gallery_categories.stats', 300, function (): array {
            $counts = GalleryCategory::withTrashed()
                ->selectRaw('count(distinct case when deleted_at is null then lang_group_id end) as total')
                ->selectRaw('count(distinct case when deleted_at is null and is_active = 1 then lang_group_id end) as active')
                ->selectRaw('count(distinct case when deleted_at is null and is_active = 0 then lang_group_id end) as passive')
                ->selectRaw('count(distinct case when deleted_at is not null then lang_group_id end) as trashed')
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
            ->selectRaw('COUNT(DISTINCT CASE WHEN deleted_at IS NULL AND is_active = 1 THEN lang_group_id END) as active')
            ->selectRaw('COUNT(DISTINCT CASE WHEN deleted_at IS NULL AND is_active = 0 THEN lang_group_id END) as passive')
            ->selectRaw('COUNT(DISTINCT CASE WHEN deleted_at IS NOT NULL THEN lang_group_id END) as trashed')
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

    /**
     * @param array<string, array<string, mixed>> $translations locale => fields
     */
    public function createTranslated(array $translations): string
    {
        $groupId = $this->saveTranslations(GalleryCategory::class, $translations, static fn (array $fields): array => $fields);

        $this->clearCache();

        return $groupId;
    }

    /**
     * @param array<string, array<string, mixed>> $translations locale => fields
     */
    public function updateTranslated(GalleryCategory $category, array $translations): string
    {
        $groupId = $this->saveTranslations(
            GalleryCategory::class,
            $translations,
            static fn (array $fields): array => $fields,
            $category->lang_group_id,
        );

        $this->clearCache();

        return $groupId;
    }

    public function delete(GalleryCategory $category): void
    {
        $this->deleteTranslationGroup($category);
        $this->clearCache();
    }

    public function restore(int $id): GalleryCategory
    {
        $category = GalleryCategory::withTrashed()->findOrFail($id);

        $this->restoreTranslationGroup($category);
        $this->clearCache();

        return $category->refresh();
    }

    private function clearCache(): void
    {
        $this->forgetLocalized('gallery_categories.active');
        Cache::forget('admin.gallery_categories.stats');
    }
}
