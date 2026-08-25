<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BlogPost;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class BlogService
{
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
            ->recent()
            ->paginate($perPage);
    }

    public function paginateByCategory(int $categoryId, int $perPage = 9): LengthAwarePaginator
    {
        return BlogPost::with(['category', 'author'])
            ->published()
            ->where('blog_category_id', $categoryId)
            ->recent()
            ->paginate($perPage);
    }

    public function findBySlug(string $slug): ?BlogPost
    {
        return BlogPost::with(['category', 'author'])
            ->published()
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
        $query = BlogPost::with(['category', 'author'])->latest();

        if (isset($filters['status'])) {
            match ($filters['status']) {
                'published' => $query->where('is_published', true)
                    ->where('published_at', '<=', now()),
                'draft'     => $query->where('is_published', false),
                'scheduled' => $query->where('is_published', true)
                    ->where('published_at', '>', now()),
                'trashed'   => $query->onlyTrashed(),
                default     => null,
            };
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('excerpt', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['category_id'])) {
            $query->where('blog_category_id', $filters['category_id']);
        }

        return $query->paginate($perPage);
    }

    /**
     * @return array{total: int, published: int, draft: int, total_views: int}
     */
    public function getAdminStats(): array
    {
        return Cache::remember('blog.admin_stats', 300, function (): array {
            $counts = BlogPost::withTrashed()->selectRaw("
                SUM(CASE WHEN deleted_at IS NULL THEN 1 ELSE 0 END) as total,
                SUM(CASE WHEN deleted_at IS NULL AND is_published = 1 THEN 1 ELSE 0 END) as published,
                SUM(CASE WHEN deleted_at IS NULL AND is_published = 0 THEN 1 ELSE 0 END) as draft,
                COALESCE(SUM(CASE WHEN deleted_at IS NULL THEN views ELSE 0 END), 0) as total_views
            ")->first();

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
            ->selectRaw('sum(case when deleted_at is null then 1 else 0 end) as total')
            ->selectRaw('sum(case when deleted_at is null and is_published = 1 and published_at <= ? then 1 else 0 end) as published', [$now])
            ->selectRaw('sum(case when deleted_at is null and is_published = 0 then 1 else 0 end) as draft')
            ->selectRaw('sum(case when deleted_at is null and is_published = 1 and published_at > ? then 1 else 0 end) as scheduled', [$now])
            ->selectRaw('sum(case when deleted_at is not null then 1 else 0 end) as trashed')
            ->first();

        return [
            'all'       => (int) $counts->total,
            'published' => (int) $counts->published,
            'draft'     => (int) $counts->draft,
            'scheduled' => (int) $counts->scheduled,
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
     * Save a post in every language the form supplied.
     *
     * The publish flag and the author come from outside the language blocks:
     * publishing is a decision about the post, not about one translation.
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
                $this->prepareImageField($fields + $shared, $existing, 'blog', 'title', 'image', $default),
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
                $this->prepareImageField($fields + $shared, $existing, 'blog', 'title', 'image', $default),
            $post->lang_group_id,
        );

        $this->clearCache();

        return $groupId;
    }

    public function delete(BlogPost $post): void
    {
        DB::transaction(function () use ($post): void {
            $post->delete();
            $this->clearCache();
        });
    }

    public function restore(int $id): BlogPost
    {
        $post = BlogPost::withTrashed()->findOrFail($id);
        $post->restore();
        $this->clearCache();

        return $post;
    }

    private function clearCache(): void
    {
        Cache::forget('blog_categories.active');
        Cache::forget('blog.admin_stats');
        Cache::forget('sitemap.urls');
        Cache::forget('sitemap_page.groups');
    }
}
