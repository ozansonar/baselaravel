<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PopupPage;
use App\Models\Popup;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class PopupService
{
    public function __construct(
        private readonly UploadService $uploadService,
    ) {}

    /**
     * @return Collection<int, Popup>
     */
    public function getForPage(string $page): Collection
    {
        return Cache::remember("popups.page.{$page}", 300, fn () =>
            Popup::active()->scheduled()->forPage($page)->sorted()->get(),
        );
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = Popup::withTrashed()->sorted();

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

    public function findById(int $id): Popup
    {
        return Popup::findOrFail($id);
    }

    public function create(array $data): Popup
    {
        return DB::transaction(function () use ($data): Popup {
            if (isset($data['image']) && $data['image'] instanceof \Illuminate\Http\UploadedFile) {
                $data['image'] = $this->uploadService->uploadImage(
                    $data['image'],
                    'popups',
                    $data['title'],
                    ['md', 'lg'],
                );
            }

            $popup = Popup::create($data);
            $this->clearCache();

            return $popup;
        });
    }

    public function update(Popup $popup, array $data): Popup
    {
        return DB::transaction(function () use ($popup, $data): Popup {
            if (isset($data['image']) && $data['image'] instanceof \Illuminate\Http\UploadedFile) {
                $data['image'] = $this->uploadService->replaceImage(
                    $data['image'],
                    'popups',
                    $data['title'] ?? $popup->title,
                    $popup->image,
                    ['md', 'lg'],
                );
            }

            $popup->update($data);
            $this->clearCache();

            return $popup->refresh();
        });
    }

    public function delete(Popup $popup): void
    {
        DB::transaction(function () use ($popup): void {
            if ($popup->image) {
                $this->uploadService->deleteImage($popup->image);
            }

            $popup->delete();
            $this->clearCache();
        });
    }

    public function restore(int $id): Popup
    {
        $popup = Popup::withTrashed()->findOrFail($id);
        $popup->restore();
        $this->clearCache();

        return $popup;
    }

    /**
     * @return array<string, int>
     */
    public function getAdminStats(): array
    {
        return Cache::remember('admin.popups.stats', 300, function (): array {
            $today = now()->toDateString();

            $counts = Popup::withTrashed()
                ->selectRaw('sum(case when deleted_at is null then 1 else 0 end) as total')
                ->selectRaw('sum(case when deleted_at is null and is_active = 1 then 1 else 0 end) as active')
                ->selectRaw("sum(case when deleted_at is null and is_active = 1 and (start_date is not null or end_date is not null) and (start_date is null or start_date <= '{$today}') and (end_date is null or end_date >= '{$today}') then 1 else 0 end) as scheduled")
                ->selectRaw("sum(case when deleted_at is null and end_date is not null and end_date < '{$today}' then 1 else 0 end) as expired")
                ->selectRaw('sum(case when deleted_at is not null then 1 else 0 end) as trashed')
                ->first();

            return [
                'total'     => (int) $counts->total,
                'active'    => (int) $counts->active,
                'scheduled' => (int) $counts->scheduled,
                'expired'   => (int) $counts->expired,
                'trashed'   => (int) $counts->trashed,
            ];
        });
    }

    /**
     * @return array<string, int>
     */
    public function statusCounts(): array
    {
        return [
            'active'  => Popup::where('is_active', true)->count(),
            'passive' => Popup::where('is_active', false)->count(),
            'trashed' => Popup::onlyTrashed()->count(),
        ];
    }

    private function clearCache(): void
    {
        Cache::forget('admin.popups.stats');

        foreach (PopupPage::cases() as $page) {
            Cache::forget("popups.page.{$page->value}");
        }
    }
}
