<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Slider;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class SliderService
{
    use \App\Services\Concerns\LocalizedCache;
    use \App\Services\Concerns\SyncsTranslations;

    public function __construct(
        private readonly UploadService $uploadService,
    ) {}

    /**
     * @return Collection<int, Slider>
     */
    public function allActive(): Collection
    {
        return Cache::remember($this->localeCacheKey('sliders.active'), 3600, fn () =>
            Slider::active()->localeWithFallback()->sorted()->get(),
        );
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = Slider::withTrashed()->sorted();

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
                    ->orWhere('subtitle', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    public function findById(int $id): Slider
    {
        return Slider::findOrFail($id);
    }

    public function create(array $data): Slider
    {
        return DB::transaction(function () use ($data): Slider {
            if (isset($data['image']) && $data['image'] instanceof \Illuminate\Http\UploadedFile) {
                $data['image'] = $this->uploadService->uploadImage(
                    $data['image'],
                    'sliders',
                    $data['title'],
                    ['lg'],
                );
            }

            $slider = Slider::create($data);
            $this->clearCache();

            return $slider;
        });
    }

    /**
     * @param array<string, array<string, mixed>> $translations locale => fields
     */
    public function createTranslated(array $translations): string
    {
        $groupId = $this->saveTranslations(
            Slider::class,
            $translations,
            fn (array $fields, string $locale, ?Slider $existing, ?Slider $default): array =>
                $this->prepareImageField($fields, $existing, 'sliders', 'title', 'image', $default),
        );

        $this->clearCache();

        return $groupId;
    }

    /**
     * @param array<string, array<string, mixed>> $translations locale => fields
     */
    public function updateTranslated(Slider $slider, array $translations): string
    {
        $groupId = $this->saveTranslations(
            Slider::class,
            $translations,
            fn (array $fields, string $locale, ?Slider $existing, ?Slider $default): array =>
                $this->prepareImageField($fields, $existing, 'sliders', 'title', 'image', $default),
            $slider->lang_group_id,
        );

        $this->clearCache();

        return $groupId;
    }

    public function update(Slider $slider, array $data): Slider
    {
        return DB::transaction(function () use ($slider, $data): Slider {
            if (isset($data['image']) && $data['image'] instanceof \Illuminate\Http\UploadedFile) {
                $data['image'] = $this->uploadService->replaceImage(
                    $data['image'],
                    'sliders',
                    $data['title'] ?? $slider->title,
                    $slider->image,
                    ['lg'],
                );
            }

            $slider->update($data);
            $this->clearCache();

            return $slider->refresh();
        });
    }

    public function delete(Slider $slider): void
    {
        DB::transaction(function () use ($slider): void {
            if ($slider->image) {
                $this->uploadService->deleteImage($slider->image);
            }

            $slider->delete();
            $this->clearCache();
        });
    }

    public function restore(int $id): Slider
    {
        $slider = Slider::withTrashed()->findOrFail($id);
        $slider->restore();
        $this->clearCache();

        return $slider;
    }

    /**
     * @return array<string, int>
     */
    public function getAdminStats(): array
    {
        return Cache::remember('admin.sliders.stats', 300, function (): array {
            $counts = Slider::withTrashed()
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
        return [
            'active'  => Slider::where('is_active', true)->count(),
            'passive' => Slider::where('is_active', false)->count(),
            'trashed' => Slider::onlyTrashed()->count(),
        ];
    }

    private function clearCache(): void
    {
        $this->forgetLocalized('sliders.active');
        Cache::forget('admin.sliders.stats');
    }
}
