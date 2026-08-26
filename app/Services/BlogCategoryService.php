<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BlogCategory;
use App\Services\LanguageService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class BlogCategoryService
{
    use \App\Services\Concerns\LocalizedCache;

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
    public function paginate(int $perPage = 15, ?array $filters = null): LengthAwarePaginator
    {
        $default = app(LanguageService::class)->defaultCode();

        // The row that represents its group: the default language when that
        // translation exists, otherwise the one created first.
        $representatives = BlogCategory::withTrashed()
            ->selectRaw('coalesce(min(case when locale = ? then id end), min(id))', [$default])
            ->groupBy('lang_group_id');

        $query = BlogCategory::withTrashed()
            ->whereIn('id', $representatives)
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
                // Searching matches any translation, then the whole group shows.
                $matching = BlogCategory::withTrashed()
                    ->select('lang_group_id')
                    ->where(function ($q) use ($filters): void {
                        $q->where('name', 'like', "%{$filters['search']}%")
                          ->orWhere('slug', 'like', "%{$filters['search']}%");
                    });

                $query->whereIn('lang_group_id', $matching);
            }
        }

        $categories = $query->paginate($perPage);

        return $this->attachGroupLocales($categories);
    }

    /**
     * Hangs the group's language list on each row in one extra query, so the
     * table can show which translations exist without an N+1.
     */
    private function attachGroupLocales(LengthAwarePaginator $categories): LengthAwarePaginator
    {
        $groupIds = collect($categories->items())->pluck('lang_group_id')->filter()->unique();

        if ($groupIds->isEmpty()) {
            return $categories;
        }

        $locales = BlogCategory::withTrashed()
            ->whereIn('lang_group_id', $groupIds)
            ->get(['lang_group_id', 'locale'])
            ->groupBy('lang_group_id')
            ->map(fn ($rows) => $rows->pluck('locale')->unique()->values()->all());

        foreach ($categories->items() as $category) {
            $category->setAttribute('group_locales', $locales[$category->lang_group_id] ?? []);
        }

        return $categories;
    }

    /**
     * @return array{total: int, active: int, passive: int, trashed: int}
     */
    public function getAdminStats(): array
    {
        return Cache::remember('admin.blog_categories.stats', 300, function (): array {
            $counts = BlogCategory::withTrashed()
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
        return [
            'active'  => BlogCategory::where('is_active', true)->distinct()->count('lang_group_id'),
            'passive' => BlogCategory::where('is_active', false)->distinct()->count('lang_group_id'),
            'trashed' => BlogCategory::onlyTrashed()->distinct()->count('lang_group_id'),
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

    /**
     * Deleting from the list removes the category in every language.
     *
     * The list shows one row per translation group, so leaving the siblings
     * behind would look like the delete silently failed. A single translation
     * is removed by emptying its tab in the form instead.
     */
    public function delete(BlogCategory $category): void
    {
        DB::transaction(function () use ($category): void {
            BlogCategory::where('lang_group_id', $category->lang_group_id)
                ->get()
                ->each(fn (BlogCategory $row) => $row->delete());

            $this->clearCache();
        });
    }

    public function restore(int $id): BlogCategory
    {
        $category = BlogCategory::withTrashed()->findOrFail($id);

        DB::transaction(function () use ($category): void {
            BlogCategory::withTrashed()
                ->where('lang_group_id', $category->lang_group_id)
                ->onlyTrashed()
                ->get()
                ->each(fn (BlogCategory $row) => $row->restore());
        });

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
