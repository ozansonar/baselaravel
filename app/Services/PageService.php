<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ContentStatus;
use App\Models\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

final class PageService
{
    use \App\Services\Concerns\LocalizedCache;

    use \App\Services\Concerns\ListsTranslationGroups;
    use \App\Services\Concerns\SyncsTranslations;

    public function __construct(
        private readonly UploadService $uploadService,
    ) {}

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
     * @return Builder<Page>
     */
    public function query(array $filters = []): Builder
    {
        $query = $this->onlyGroupRepresentatives(Page::withTrashed(), Page::class)->sorted();

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'trashed') {
                $query->onlyTrashed();
            } else {
                $status = ContentStatus::tryFrom($filters['status']);
                if ($status !== null) {
                    $query->whereNull('deleted_at')->where('status', $status);
                }
            }
        } else {
            $query->whereNull('deleted_at');
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $this->whereGroupMatches($query, Page::class, function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->attachGroupLocales($this->query($filters)->paginate($perPage), Page::class);
    }

    /**
     * @return Collection<int, Page>
     */
    public function allPublished(): Collection
    {
        return Cache::remember($this->localeCacheKey('pages.published'), 3600, fn () =>
            Page::published()->localeWithFallback()->sorted()->get(['id', 'title', 'slug', 'sort_order', 'locale', 'lang_group_id']),
        );
    }

    public function findBySlug(string $slug): Page
    {
        // A slug only has to be unique inside its own language, so the lookup
        // is scoped; content with no translation yet still resolves through the
        // default-language fallback.
        return Page::where('slug', $slug)->published()->localeWithFallback()->firstOrFail();
    }

    public function findById(int $id): Page
    {
        return Page::findOrFail($id);
    }

    public function create(array $data): Page
    {
        return DB::transaction(function () use ($data): Page {
            if (isset($data['image']) && $data['image'] instanceof \Illuminate\Http\UploadedFile) {
                $data['image'] = $this->uploadService->uploadImage($data['image'], 'pages', $data['title']);
            }

            $page = Page::create($data);
            $this->clearCache();

            return $page;
        });
    }

    /**
     * Save a page in every language the form supplied.
     *
     * Each language block carries its own image, so artwork with text on it can
     * differ per language.
     *
     * @param array<string, array<string, mixed>> $translations locale => fields
     */
    public function createTranslated(array $translations): string
    {
        $groupId = $this->saveTranslations(
            Page::class,
            $translations,
            fn (array $fields, string $locale, ?Page $existing, ?Page $default): array => $this->prepareFields($fields, $locale, $existing, $default),
        );

        $this->clearCache();

        return $groupId;
    }

    /**
     * @param array<string, array<string, mixed>> $translations locale => fields
     */
    public function updateTranslated(Page $page, array $translations): string
    {
        $groupId = $this->saveTranslations(
            Page::class,
            $translations,
            fn (array $fields, string $locale, ?Page $existing, ?Page $default): array => $this->prepareFields($fields, $locale, $existing, $default),
            $page->lang_group_id,
        );

        $this->clearCache();

        return $groupId;
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private function prepareFields(array $fields, string $locale, ?Page $existing, ?Page $default = null): array
    {
        $image = $fields['image'] ?? null;

        if ($image instanceof \Illuminate\Http\UploadedFile) {
            $fields['image'] = $existing?->image
                ? $this->uploadService->replaceImage($image, 'pages', $fields['title'] ?? 'page', $existing->image)
                : $this->uploadService->uploadImage($image, 'pages', $fields['title'] ?? 'page');
        } else {
            // No new file in this block: keep whatever the translation already
            // has, or borrow the default language's while this one is prepared.
            unset($fields['image']);

            if ($existing === null && $default?->image) {
                $fields['image'] = $default->image;
            }
        }

        return $fields;
    }

    public function delete(Page $page): void
    {
        $this->deleteTranslationGroup($page);
        $this->clearCache();
    }

    public function restore(int $id): Page
    {
        $page = Page::withTrashed()->findOrFail($id);

        $this->restoreTranslationGroup($page);
        $this->clearCache();

        return $page->refresh();
    }

    /**
     * @return array<string, int>
     */
    public function getAdminStats(): array
    {
        return Cache::remember('admin.pages.stats', 300, function (): array {
            $counts = $this->onlyGroupRepresentatives(Page::withTrashed(), Page::class)
                ->selectRaw('sum(case when deleted_at is null then 1 else 0 end) as total')
                ->selectRaw('sum(case when deleted_at is null and status = ? then 1 else 0 end) as published', [ContentStatus::Published->value])
                ->selectRaw('sum(case when deleted_at is null and status = ? then 1 else 0 end) as draft', [ContentStatus::Draft->value])
                ->selectRaw('sum(case when deleted_at is not null then 1 else 0 end) as trashed')
                ->first();

            return [
                'total'     => (int) $counts->total,
                'published' => (int) $counts->published,
                'draft'     => (int) $counts->draft,
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
            'published' => $this->countGroups(Page::where('status', ContentStatus::Published)),
            'draft'     => $this->countGroups(Page::where('status', ContentStatus::Draft)),
            'archived'  => $this->countGroups(Page::where('status', ContentStatus::Archived)),
            'trashed'   => $this->countGroups(Page::onlyTrashed()),
        ];
    }

    private function clearCache(): void
    {
        $this->forgetLocalized('pages.published');
        Cache::forget('admin.pages.stats');
        // Modül 7 — yeni/güncellenen sayfa sitemap'e anında yansısın.
        Cache::forget('sitemap.urls');
        Cache::forget('sitemap_page.groups');
    }
}
