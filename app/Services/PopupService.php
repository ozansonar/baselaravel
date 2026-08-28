<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PopupPage;
use App\Models\Popup;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

final class PopupService
{
    use \App\Services\Concerns\LocalizedCache;

    use \App\Services\Concerns\ListsTranslationGroups;
    use \App\Services\Concerns\SyncsTranslations;

    public function __construct(
        private readonly UploadService $uploadService,
    ) {}

    /**
     * @return Collection<int, Popup>
     */
    public function getForPage(string $page): Collection
    {
        return Cache::remember($this->localeCacheKey("popups.page.{$page}"), 300, fn () =>
            Popup::active()->localeWithFallback()->scheduled()->forPage($page)->sorted()->get(),
        );
    }

    /**
     * @param array<string, mixed> $filters
     */
    /**
     * Liste ekranının tanıdığı süzgeç anahtarları.
     *
     * Ekran da dışa aktarma da bu listeyi okur; iki yerde ayrı yazılsaydı
     * dosyaya inen ile ekranda görünen zamanla ayrışırdı.
     *
     * @return list<string>
     */
    public function filterKeys(): array
    {
        return ['status', 'search'];
    }

    /**
     * Süzgeçler uygulanmış, sayfalanmamış sorgu.
     *
     * @param array<string, mixed> $filters
     * @return Builder<Popup>
     */
    public function query(array $filters = []): Builder
    {
        $query = $this->onlyGroupRepresentatives(Popup::withTrashed(), Popup::class)->sorted();

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
            $this->whereGroupMatches($query, Popup::class, function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->attachGroupLocales($this->query($filters)->paginate($perPage), Popup::class);
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

    /**
     * @param array<string, array<string, mixed>> $translations locale => fields
     */
    public function createTranslated(array $translations): string
    {
        $groupId = $this->saveTranslations(
            Popup::class,
            $translations,
            fn (array $fields, string $locale, ?Popup $existing, ?Popup $default): array =>
                $this->prepareImageField($fields, $existing, 'popups', 'title', 'image', $default),
        );

        $this->clearCache();

        return $groupId;
    }

    /**
     * @param array<string, array<string, mixed>> $translations locale => fields
     */
    public function updateTranslated(Popup $popup, array $translations): string
    {
        $groupId = $this->saveTranslations(
            Popup::class,
            $translations,
            fn (array $fields, string $locale, ?Popup $existing, ?Popup $default): array =>
                $this->prepareImageField($fields, $existing, 'popups', 'title', 'image', $default),
            $popup->lang_group_id,
        );

        $this->clearCache();

        return $groupId;
    }

    public function delete(Popup $popup): void
    {
        $this->deleteTranslationGroup($popup);
        $this->clearCache();
    }

    public function restore(int $id): Popup
    {
        $popup = Popup::withTrashed()->findOrFail($id);

        $this->restoreTranslationGroup($popup);
        $this->clearCache();

        return $popup->refresh();
    }

    /**
     * @return array<string, int>
     */
    public function getAdminStats(): array
    {
        return Cache::remember('admin.popups.stats', 300, function (): array {
            $today = now()->toDateString();

            $counts = $this->onlyGroupRepresentatives(Popup::withTrashed(), Popup::class)
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
            'active'  => $this->countGroups(Popup::where('is_active', true)),
            'passive' => $this->countGroups(Popup::where('is_active', false)),
            'trashed' => $this->countGroups(Popup::onlyTrashed()),
        ];
    }

    private function clearCache(): void
    {
        Cache::forget('admin.popups.stats');

        foreach (PopupPage::cases() as $page) {
            $this->forgetLocalized("popups.page.{$page->value}");
        }
    }
}
