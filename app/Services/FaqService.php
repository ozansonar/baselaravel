<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Faq;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class FaqService
{
    use \App\Services\Concerns\LocalizedCache;
    use \App\Services\Concerns\SyncsTranslations;

    /**
     * @return Collection<int, Faq>
     */
    public function allActive(): Collection
    {
        return Cache::remember($this->localeCacheKey('faqs.active'), 3600, fn () =>
            Faq::active()->localeWithFallback()->sorted()->get(),
        );
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = Faq::withTrashed()->sorted();

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
                $q->where('question', 'like', "%{$search}%")
                    ->orWhere('answer', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    public function findById(int $id): Faq
    {
        return Faq::findOrFail($id);
    }

    public function create(array $data): Faq
    {
        return DB::transaction(function () use ($data): Faq {
            $faq = Faq::create($data);
            $this->clearCache();

            return $faq;
        });
    }

    /**
     * @param array<string, array<string, mixed>> $translations locale => fields
     */
    public function createTranslated(array $translations): string
    {
        $groupId = $this->saveTranslations(Faq::class, $translations, static fn (array $fields): array => $fields);

        $this->clearCache();

        return $groupId;
    }

    /**
     * @param array<string, array<string, mixed>> $translations locale => fields
     */
    public function updateTranslated(Faq $faq, array $translations): string
    {
        $groupId = $this->saveTranslations(
            Faq::class,
            $translations,
            static fn (array $fields): array => $fields,
            $faq->lang_group_id,
        );

        $this->clearCache();

        return $groupId;
    }

    public function update(Faq $faq, array $data): Faq
    {
        return DB::transaction(function () use ($faq, $data): Faq {
            $faq->update($data);
            $this->clearCache();

            return $faq->refresh();
        });
    }

    public function delete(Faq $faq): void
    {
        DB::transaction(function () use ($faq): void {
            $faq->delete();
            $this->clearCache();
        });
    }

    public function restore(int $id): Faq
    {
        $faq = Faq::withTrashed()->findOrFail($id);
        $faq->restore();
        $this->clearCache();

        return $faq;
    }

    /**
     * @return array<string, int>
     */
    public function getAdminStats(): array
    {
        return Cache::remember('admin.faqs.stats', 300, function (): array {
            $counts = Faq::withTrashed()
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
            'active'  => Faq::where('is_active', true)->count(),
            'passive' => Faq::where('is_active', false)->count(),
            'trashed' => Faq::onlyTrashed()->count(),
        ];
    }

    private function clearCache(): void
    {
        $this->forgetLocalized('faqs.active');
        Cache::forget('admin.faqs.stats');
    }
}
