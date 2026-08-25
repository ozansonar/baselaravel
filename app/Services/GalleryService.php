<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\GalleryType;
use App\Models\GalleryItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class GalleryService
{
    use \App\Services\Concerns\LocalizedCache;

    use \App\Services\Concerns\SyncsTranslations;

    public function __construct(
        private readonly UploadService $uploadService,
    ) {}

    /**
     * @return Collection<int, GalleryItem>
     */
    public function activePhotos(): Collection
    {
        return Cache::remember($this->localeCacheKey('gallery.photos'), 3600, fn () =>
            GalleryItem::active()->localeWithFallback()->photos()->sorted()->with('galleryCategory')->get(),
        );
    }

    /**
     * @return Collection<int, GalleryItem>
     */
    public function activeVideos(): Collection
    {
        return Cache::remember($this->localeCacheKey('gallery.videos'), 3600, fn () =>
            GalleryItem::active()->localeWithFallback()->videos()->sorted()->with('galleryCategory')->get(),
        );
    }

    /**
     * @return array<string, Collection<int, GalleryItem>>
     */
    public function allActiveGrouped(): array
    {
        return [
            'photos' => $this->activePhotos(),
            'videos' => $this->activeVideos(),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = GalleryItem::withTrashed()->sorted()->with('galleryCategory');

        if (!empty($filters['type'])) {
            $type = GalleryType::tryFrom($filters['type']);
            if ($type) {
                $query->where('type', $type);
            }
        }

        if (!empty($filters['category'])) {
            $categoryId = (int) $filters['category'];
            if ($categoryId > 0) {
                $query->where('gallery_category_id', $categoryId);
            }
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'trashed') {
                $query->onlyTrashed();
            } elseif ($filters['status'] === 'active') {
                $query->whereNull('deleted_at')->where('is_active', true);
            } elseif ($filters['status'] === 'passive') {
                $query->whereNull('deleted_at')->where('is_active', false);
            }
        } else {
            $query->whereNull('deleted_at');
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    public function findById(int $id): GalleryItem
    {
        return GalleryItem::with('galleryCategory')->findOrFail($id);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): GalleryItem
    {
        return DB::transaction(function () use ($data): GalleryItem {
            if (isset($data['image']) && $data['image'] instanceof \Illuminate\Http\UploadedFile) {
                $data['image'] = $this->uploadService->uploadImage(
                    $data['image'],
                    'gallery',
                    $data['title'],
                    ['lg', 'md'],
                );
            }

            $item = GalleryItem::create($data);
            $this->clearCache();

            return $item;
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(GalleryItem $item, array $data): GalleryItem
    {
        return DB::transaction(function () use ($item, $data): GalleryItem {
            if (isset($data['image']) && $data['image'] instanceof \Illuminate\Http\UploadedFile) {
                $data['image'] = $this->uploadService->replaceImage(
                    $data['image'],
                    'gallery',
                    $data['title'] ?? $item->title,
                    $item->image,
                    ['lg', 'md'],
                );
            }

            $item->update($data);
            $this->clearCache();

            return $item->refresh();
        });
    }

    /**
     * @param array<string, array<string, mixed>> $translations locale => fields
     */
    public function createTranslated(array $translations): string
    {
        $groupId = $this->saveTranslations(
            GalleryItem::class,
            $translations,
            fn (array $fields, string $locale, ?GalleryItem $existing, ?GalleryItem $default): array =>
                $this->prepareImageField($fields, $existing, 'gallery', 'title', 'image', $default),
        );

        $this->clearCache();

        return $groupId;
    }

    /**
     * @param array<string, array<string, mixed>> $translations locale => fields
     */
    public function updateTranslated(GalleryItem $galleryItem, array $translations): string
    {
        $groupId = $this->saveTranslations(
            GalleryItem::class,
            $translations,
            fn (array $fields, string $locale, ?GalleryItem $existing, ?GalleryItem $default): array =>
                $this->prepareImageField($fields, $existing, 'gallery', 'title', 'image', $default),
            $galleryItem->lang_group_id,
        );

        $this->clearCache();

        return $groupId;
    }

    public function delete(GalleryItem $item): void
    {
        DB::transaction(function () use ($item): void {
            if ($item->image) {
                $this->uploadService->deleteImage($item->image);
            }

            $item->delete();
            $this->clearCache();
        });
    }

    public function restore(int $id): GalleryItem
    {
        $item = GalleryItem::withTrashed()->findOrFail($id);
        $item->restore();
        $this->clearCache();

        return $item;
    }

    /**
     * @return array<string, int>
     */
    public function getAdminStats(): array
    {
        return Cache::remember('admin.gallery.stats', 300, function (): array {
            $counts = GalleryItem::withTrashed()
                ->selectRaw('sum(case when deleted_at is null then 1 else 0 end) as total')
                ->selectRaw('sum(case when deleted_at is null and type = ? then 1 else 0 end) as photos', [GalleryType::Photo->value])
                ->selectRaw('sum(case when deleted_at is null and type = ? then 1 else 0 end) as videos', [GalleryType::Video->value])
                ->selectRaw('sum(case when deleted_at is null and is_active = 1 then 1 else 0 end) as active')
                ->selectRaw('sum(case when deleted_at is not null then 1 else 0 end) as trashed')
                ->first();

            return [
                'total'   => (int) $counts->total,
                'photos'  => (int) $counts->photos,
                'videos'  => (int) $counts->videos,
                'active'  => (int) $counts->active,
                'trashed' => (int) $counts->trashed,
            ];
        });
    }

    /**
     * @return array<string, int>
     */
    public function statusCounts(): array
    {
        $counts = GalleryItem::withTrashed()->selectRaw("
            SUM(CASE WHEN deleted_at IS NULL AND is_active = 1 THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN deleted_at IS NULL AND is_active = 0 THEN 1 ELSE 0 END) as passive,
            SUM(CASE WHEN deleted_at IS NOT NULL THEN 1 ELSE 0 END) as trashed
        ")->first();

        return [
            'active'  => (int) $counts->active,
            'passive' => (int) $counts->passive,
            'trashed' => (int) $counts->trashed,
        ];
    }

    private function clearCache(): void
    {
        $this->forgetLocalized('gallery.photos');
        $this->forgetLocalized('gallery.videos');
        Cache::forget('admin.gallery.stats');
    }
}
