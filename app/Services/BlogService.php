<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ContentStatus;
use App\Models\BlogPost;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class BlogService
{
    use \App\Services\Concerns\ListsTranslationGroups;
    use \App\Services\Concerns\SyncsTranslations;

    public function __construct(
        private readonly UploadService $uploadService,
    ) {}

    // ── Frontend ──

    /**
     * @return Collection<int, BlogPost>
     */
    public function latestPublished(int $limit = 3): Collection
    {
        return BlogPost::with(['category', 'author'])
            ->published()
            ->localeWithFallback()
            ->recent()
            ->limit($limit)
            ->get();
    }

    /**
     * Related posts from the same category (fallback to most recent).
     *
     * @return Collection<int, BlogPost>
     */
    public function getRelatedPosts(BlogPost $post, int $limit = 4): Collection
    {
        return BlogPost::with(['category', 'author'])
            ->published()
            ->localeWithFallback()
            ->where('id', '!=', $post->id)
            ->where('blog_category_id', $post->blog_category_id)
            ->recent()
            ->limit($limit)
            ->get();
    }

    public function paginatePublished(int $perPage = 9): LengthAwarePaginator
    {
        return BlogPost::with(['category', 'author'])
            ->published()
            ->localeWithFallback()
            ->recent()
            ->paginate($perPage);
    }

    public function paginateByCategory(int $categoryId, int $perPage = 9): LengthAwarePaginator
    {
        return BlogPost::with(['category', 'author'])
            ->published()
            ->localeWithFallback()
            ->where('blog_category_id', $categoryId)
            ->recent()
            ->paginate($perPage);
    }

    public function findBySlug(string $slug): ?BlogPost
    {
        // A slug is only unique inside its own language, so the lookup is
        // scoped; a post with no translation yet resolves through the
        // default-language fallback.
        return BlogPost::with(['category', 'author'])
            ->published()
            ->localeWithFallback()
            ->where('slug', $slug)
            ->first();
    }

    public function incrementViews(BlogPost $post): void
    {
        $post->increment('views');
    }

    // ── Admin ──

    /**
     * @param array<string, mixed> $filters
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = $this->onlyGroupRepresentatives(BlogPost::query(), BlogPost::class)
            ->with(['category', 'author'])
            ->latest();

        if (isset($filters['status'])) {
            match ($filters['status']) {
                // "Scheduled" is not stored; it is the published state with a
                // date that has not arrived yet.
                'published' => $query->where('status', ContentStatus::Published)
                    ->where('published_at', '<=', now()),
                'draft'     => $query->where('status', ContentStatus::Draft),
                'scheduled' => $query->where('status', ContentStatus::Published)
                    ->where('published_at', '>', now()),
                'archived'  => $query->where('status', ContentStatus::Archived),
                'trashed'   => $query->onlyTrashed(),
                default     => null,
            };
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $this->whereGroupMatches($query, BlogPost::class, function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('excerpt', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['category_id'])) {
            // The chosen category belongs to one language, so a post counts as
            // a match when any of its translations sits in that group.
            $this->whereGroupMatches($query, BlogPost::class, function ($q) use ($filters): void {
                $q->where('blog_category_id', $filters['category_id']);
            });
        }

        return $this->attachGroupLocales($query->paginate($perPage), BlogPost::class);
    }

    /**
     * @return array{total: int, published: int, draft: int, total_views: int}
     */
    public function getAdminStats(): array
    {
        return Cache::remember('blog.admin_stats', 300, function (): array {
            // "Published" means live on the site, so the date has to have
            // arrived — same rule the status tabs and the front use.
            $counts = BlogPost::withTrashed()->selectRaw("
                COUNT(DISTINCT CASE WHEN deleted_at IS NULL THEN lang_group_id END) as total,
                COUNT(DISTINCT CASE WHEN deleted_at IS NULL AND status = 'published' AND published_at <= ? THEN lang_group_id END) as published,
                COUNT(DISTINCT CASE WHEN deleted_at IS NULL AND status = 'draft' THEN lang_group_id END) as draft,
                COALESCE(SUM(CASE WHEN deleted_at IS NULL THEN views ELSE 0 END), 0) as total_views
            ", [now()])->first();

            return [
                'total'       => (int) $counts->total,
                'published'   => (int) $counts->published,
                'draft'       => (int) $counts->draft,
                'total_views' => (int) $counts->total_views,
            ];
        });
    }

    /**
     * @return array<string, int>
     */
    public function getStatusCounts(): array
    {
        $now = now();

        $counts = BlogPost::withTrashed()
            ->selectRaw('count(distinct case when deleted_at is null then lang_group_id end) as total')
            ->selectRaw("count(distinct case when deleted_at is null and status = 'published' and published_at <= ? then lang_group_id end) as published", [$now])
            ->selectRaw("count(distinct case when deleted_at is null and status = 'draft' then lang_group_id end) as draft")
            ->selectRaw("count(distinct case when deleted_at is null and status = 'published' and published_at > ? then lang_group_id end) as scheduled", [$now])
            ->selectRaw("count(distinct case when deleted_at is null and status = 'archived' then lang_group_id end) as archived")
            ->selectRaw('count(distinct case when deleted_at is not null then lang_group_id end) as trashed')
            ->first();

        return [
            'all'       => (int) $counts->total,
            'published' => (int) $counts->published,
            'draft'     => (int) $counts->draft,
            'scheduled' => (int) $counts->scheduled,
            'archived'  => (int) $counts->archived,
            'trashed'   => (int) $counts->trashed,
        ];
    }

    public function findById(int $id): BlogPost
    {
        return BlogPost::with(['category', 'author'])->findOrFail($id);
    }

    public function create(array $data): BlogPost
    {
        return DB::transaction(function () use ($data): BlogPost {
            if (isset($data['image']) && $data['image'] instanceof \Illuminate\Http\UploadedFile) {
                $data['image'] = $this->uploadService->uploadImage($data['image'], 'blog', $data['title']);
            }

            $post = BlogPost::create($data);
            $this->clearCache();

            return $post;
        });
    }

    public function update(BlogPost $post, array $data): BlogPost
    {
        return DB::transaction(function () use ($post, $data): BlogPost {
            if (isset($data['image']) && $data['image'] instanceof \Illuminate\Http\UploadedFile) {
                $data['image'] = $this->uploadService->replaceImage(
                    $data['image'],
                    'blog',
                    $data['title'] ?? $post->title,
                    $post->image,
                );
            }

            $post->update($data);
            $this->clearCache();

            return $post->refresh();
        });
    }

    /**
     * A post set to "published" with no date would never surface: the front
     * only shows posts whose date has arrived. Publishing without picking a
     * date therefore means "now", which is what the form promises.
     *
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private function normalizePublishDate(array $fields): array
    {
        $status = $fields['status'] ?? null;
        $published = $status === ContentStatus::Published->value || $status === ContentStatus::Published;

        if ($published && empty($fields['published_at'])) {
            $fields['published_at'] = now();
        }

        return $fields;
    }

    /**
     * Save a post in every language the form supplied.
     *
     * The author comes from outside the language blocks; the publish state is
     * per language, so a Turkish version can be live while the English one is
     * still a draft.
     *
     * @param array<string, array<string, mixed>> $translations locale => fields
     * @param array<string, mixed> $shared
     */
    public function createTranslated(array $translations, array $shared = []): string
    {
        $groupId = $this->saveTranslations(
            BlogPost::class,
            $translations,
            fn (array $fields, string $locale, ?BlogPost $existing, ?BlogPost $default): array =>
                $this->prepareImageField($this->normalizePublishDate($fields) + $shared, $existing, 'blog', 'title', 'image', $default),
        );

        $this->clearCache();

        return $groupId;
    }

    /**
     * @param array<string, array<string, mixed>> $translations locale => fields
     * @param array<string, mixed> $shared
     */
    public function updateTranslated(BlogPost $post, array $translations, array $shared = []): string
    {
        $groupId = $this->saveTranslations(
            BlogPost::class,
            $translations,
            fn (array $fields, string $locale, ?BlogPost $existing, ?BlogPost $default): array =>
                $this->prepareImageField($this->normalizePublishDate($fields) + $shared, $existing, 'blog', 'title', 'image', $default),
            $post->lang_group_id,
        );

        $this->clearCache();

        return $groupId;
    }

    public function delete(BlogPost $post): void
    {
        $this->deleteTranslationGroup($post);
        $this->clearCache();
    }

    public function restore(int $id): BlogPost
    {
        $post = BlogPost::withTrashed()->findOrFail($id);

        $this->restoreTranslationGroup($post);
        $this->clearCache();

        return $post->refresh();
    }

    private function clearCache(): void
    {
        Cache::forget('blog_categories.active');
        Cache::forget('blog.admin_stats');
        Cache::forget('sitemap.urls');
        Cache::forget('sitemap_page.groups');
    }
}
