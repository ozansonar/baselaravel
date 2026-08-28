<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BlogCategory;
use App\Services\LanguageService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

final class BlogCategoryService
{
    use \App\Services\Concerns\LocalizedCache;

    use \App\Services\Concerns\ListsTranslationGroups;

    use \App\Services\Concerns\SyncsTranslations;

    /**
     * @return Collection<int, BlogCategory>
     */
    public function allActive(): Collection
    {
        return Cache::remember($this->localeCacheKey('blog_categories.active'), 3600, fn () =>
            BlogCategory::active()
                ->localeWithFallback()
                ->sorted()
                ->withCount(['posts' => fn ($q) => $q->published()])
                ->get(),
        );
    }

    /**
     * One row per language group, not per translation.
     *
     * The edit form is group based — every translation of a category opens the
     * same tabbed form — so the list mirrors it: a category appears once, with
     * the languages it has been translated into.
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
     * @param array<string, mixed>|null $filters
     * @return Builder<BlogCategory>
     */
    public function query(?array $filters = null): Builder
    {
        $query = $this->onlyGroupRepresentatives(BlogCategory::withTrashed(), BlogCategory::class)
            ->sorted()
            // Posts hang off the translation they were written in, so the
            // number that means something is the group's total.
            ->selectRaw('blog_categories.*, ('
                . 'select count(*) from blog_posts'
                . ' where blog_posts.deleted_at is null'
                . ' and blog_posts.blog_category_id in ('
                . 'select g.id from blog_categories as g where g.lang_group_id = blog_categories.lang_group_id'
                . ')) as posts_count');

        if ($filters) {
            if (isset($filters['status']) && $filters['status'] !== '') {
                $query = match ($filters['status']) {
                    'active'  => $query->whereNull('deleted_at')->where('is_active', true),
                    'passive' => $query->whereNull('deleted_at')->where('is_active', false),
                    'trashed' => $query->whereNotNull('deleted_at'),
                    default   => $query,
                };
            }

            if (isset($filters['search']) && $filters['search'] !== '') {
                $this->whereGroupMatches($query, BlogCategory::class, function ($q) use ($filters): void {
                    $q->where('name', 'like', "%{$filters['search']}%")
                      ->orWhere('slug', 'like', "%{$filters['search']}%");
                });
            }
        }

        return $query;
    }

    /**
     * @param array<string, mixed>|null $filters
     */
    public function paginate(int $perPage = 15, ?array $filters = null): LengthAwarePaginator
    {
        return $this->attachGroupLocales($this->query($filters)->paginate($perPage), BlogCategory::class);
    }

    /**
     * @return array{total: int, active: int, passive: int, trashed: int}
     */
    public function getAdminStats(): array
    {
        return Cache::remember('admin.blog_categories.stats', 300, function (): array {
            $counts = $this->onlyGroupRepresentatives(BlogCategory::withTrashed(), BlogCategory::class)
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
            'active'  => $this->countGroups(BlogCategory::where('is_active', true)),
            'passive' => $this->countGroups(BlogCategory::where('is_active', false)),
            'trashed' => $this->countGroups(BlogCategory::onlyTrashed()),
        ];
    }

    public function findBySlug(string $slug): ?BlogCategory
    {
        return BlogCategory::active()->where('slug', $slug)->first();
    }

    public function findById(int $id): BlogCategory
    {
        return BlogCategory::findOrFail($id);
    }

    public function create(array $data): BlogCategory
    {
        return DB::transaction(function () use ($data): BlogCategory {
            $category = BlogCategory::create($data);
            $this->clearCache();

            return $category;
        });
    }

    public function update(BlogCategory $category, array $data): BlogCategory
    {
        return DB::transaction(function () use ($category, $data): BlogCategory {
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
        $groupId = $this->saveTranslations(BlogCategory::class, $translations, static fn (array $fields): array => $fields);

        $this->clearCache();

        return $groupId;
    }

    /**
     * @param array<string, array<string, mixed>> $translations locale => fields
     */
    public function updateTranslated(BlogCategory $category, array $translations): string
    {
        $groupId = $this->saveTranslations(
            BlogCategory::class,
            $translations,
            static fn (array $fields): array => $fields,
            $category->lang_group_id,
        );

        $this->clearCache();

        return $groupId;
    }

    public function delete(BlogCategory $category): void
    {
        $this->deleteTranslationGroup($category);
        $this->clearCache();
    }

    /**
     * Listede seçilen kategorileri tek seferde siler.
     *
     * Döngü ListsTranslationGroups içinde: liste her çeviri grubunu tek
     * satırla gösteriyor, silme de grup grup işliyor — bir kategoriin
     * Türkçesini silip İngilizcesini bırakmak ön yüzde sahipsiz bir çeviri
     * bırakırdı. Dönen sayı seçilen satır değil, silinen kayıt sayısı.
     *
     * @param  list<int> $ids
     * @return int       silinen kayıt sayısı
     */
    public function deleteMany(array $ids): int
    {
        $silinen = $this->deleteGroupsById(BlogCategory::class, $ids);

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
        $geriYuklenen = $this->restoreGroupsById(BlogCategory::class, $ids);

        if ($geriYuklenen > 0) {
            $this->clearCache();
        }

        return $geriYuklenen;
    }

    public function restore(int $id): BlogCategory
    {
        $category = BlogCategory::withTrashed()->findOrFail($id);

        $this->restoreTranslationGroup($category);
        $this->clearCache();

        return $category->refresh();
    }

    private function clearCache(): void
    {
        $this->forgetLocalized('blog_categories.active');
        Cache::forget('admin.blog_categories.stats');
        // Modül 7 — blog kategori değişikliği sitemap'e anında yansısın.
        Cache::forget('sitemap.urls');
        Cache::forget('sitemap_page.groups');
    }
}
