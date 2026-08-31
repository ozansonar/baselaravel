<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\LikeSearch;
use App\Models\Faq;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

final class FaqService
{
    use \App\Services\Concerns\LocalizedCache;
    use \App\Services\Concerns\ListsTranslationGroups;
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
     * @return Builder<Faq>
     */
    public function query(array $filters = []): Builder
    {
        $query = $this->onlyGroupRepresentatives(Faq::withTrashed(), Faq::class)->sorted();

        if (! empty($filters['status'])) {
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

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $this->whereGroupMatches($query, Faq::class, function ($q) use ($search): void {
                $q->whereRaw(LikeSearch::clause('question'), [LikeSearch::term($search)])
                    ->orWhereRaw(LikeSearch::clause('answer'), [LikeSearch::term($search)]);
            });
        }

        return $query;
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->attachGroupLocales($this->query($filters)->paginate($perPage), Faq::class);
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
        $this->deleteTranslationGroup($faq);
        $this->clearCache();
    }

    /**
     * Listede seçilen soruleri tek seferde siler.
     *
     * Döngü ListsTranslationGroups içinde: liste her çeviri grubunu tek
     * satırla gösteriyor, silme de grup grup işliyor — bir soruin
     * Türkçesini silip İngilizcesini bırakmak ön yüzde sahipsiz bir çeviri
     * bırakırdı. Dönen sayı seçilen satır değil, silinen kayıt sayısı.
     *
     * @param  list<int> $ids
     * @return int       silinen kayıt sayısı
     */
    public function deleteMany(array $ids): int
    {
        $silinen = $this->deleteGroupsById(Faq::class, $ids);

        if ($silinen > 0) {
            $this->clearCache();
        }

        return $silinen;
    }

    /**
     * Seçilenleri çöpten tek seferde çıkarır.
     *
     * @param  list<int> $ids
     * @return int       geri yüklenen kayıt sayısı
     */
    public function restoreMany(array $ids): int
    {
        $geriYuklenen = $this->restoreGroupsById(Faq::class, $ids);

        if ($geriYuklenen > 0) {
            $this->clearCache();
        }

        return $geriYuklenen;
    }

    public function restore(int $id): Faq
    {
        $faq = Faq::withTrashed()->findOrFail($id);

        $this->restoreTranslationGroup($faq);
        $this->clearCache();

        return $faq->refresh();
    }

    /**
     * @return array<string, int>
     */
    public function getAdminStats(): array
    {
        return Cache::remember('admin.faqs.stats', 300, function (): array {
            $counts = $this->onlyGroupRepresentatives(Faq::withTrashed(), Faq::class)
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
            'active'  => $this->countGroups(Faq::where('is_active', true)),
            'passive' => $this->countGroups(Faq::where('is_active', false)),
            'trashed' => $this->countGroups(Faq::onlyTrashed()),
        ];
    }

    private function clearCache(): void
    {
        $this->forgetLocalized('faqs.active');
        Cache::forget('admin.faqs.stats');
    }
}
