<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\CacheKeys;
use App\Enums\ContentStatus;
use App\Models\BlogPost;
use App\Support\LikeSearch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

final class BlogService
{
    use \App\Services\Concerns\AttachesContentFiles;
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
        /** @var Collection<int, BlogPost> $related */
        $related = BlogPost::query()
            ->published()
            ->localeWithFallback()
            ->where('id', '!=', $post->id)
            ->where('blog_category_id', $post->blog_category_id)
            ->recent()
            ->limit($limit)
            ->get();

        if ($related->isEmpty()) {
            return $related;
        }

        // İlgili yazılar tanımı gereği aynı kategoriden: kategori zaten
        // elimizde, yeniden sorulmasına gerek yok. Sorulunca aynı satır
        // ikinci kez çekiliyordu (blog detayında mükerrer sorgu buydu).
        $post->loadMissing('category');

        if ($post->category !== null) {
            $related->each(fn (BlogPost $item) => $item->setRelation('category', $post->category));
        }

        // Yazar için aynı garanti yok; yalnız aynı yazara ait olanlara
        // elimizdeki kayıt veriliyor, kalanlar tek sorguda yükleniyor.
        $post->loadMissing('author');

        if ($post->author !== null) {
            $related->each(function (BlogPost $item) use ($post): void {
                if ($item->user_id === $post->user_id) {
                    $item->setRelation('author', $post->author);
                }
            });
        }

        $related->loadMissing('author');

        return $related;
    }

    public function paginatePublished(int $perPage = 9, ?string $search = null): LengthAwarePaginator
    {
        return $this->publishedQuery(null, $search)->paginate($perPage)->withQueryString();
    }

    public function paginateByCategory(int $categoryId, int $perPage = 9, ?string $search = null): LengthAwarePaginator
    {
        return $this->publishedQuery($categoryId, $search)->paginate($perPage)->withQueryString();
    }

    /**
     * Yayındaki yazıların sorgusu — kategori ve arama süzgeçleriyle.
     *
     * Üç ayrı yerde tekrarlanan sorgu tek yerde toplandı: kategori süzgeci
     * eklenirken ilişkilerin eager yüklenmesi ya da dil düşüşü birinde
     * unutulursa o sayfa N+1 atmaya ya da yanlış dilde içerik göstermeye başlar.
     *
     * Arama başlık ve özette yapılıyor, gövdede değil. Gövde zengin metin
     * editöründen geliyor, yani HTML: "div", "strong" gibi etiket adları
     * arandığında her yazı eşleşirdi. Yönetim ekranındaki arama da aynı iki
     * sütuna bakıyor.
     */
    private function publishedQuery(?int $categoryId, ?string $search): Builder
    {
        $query = BlogPost::with(['category', 'author'])
            ->published()
            ->localeWithFallback()
            ->recent();

        if ($categoryId !== null) {
            $query->where('blog_category_id', $categoryId);
        }

        $search = trim((string) $search);

        if ($search === '') {
            return $query;
        }

        // Ziyaretçinin yazdığı % ve _ joker değil harf sayılıyor: "%" yazan
        // biri süzgeç yaptığını sanarak bütün listeye bakmamalı. Kaçış biçimi
        // iki veritabanında da çalışan tek biçim — gerekçesi LikeSearch'te.
        $term = LikeSearch::term($search);

        $query->where(function (Builder $inner) use ($term): void {
            $inner->whereRaw(LikeSearch::clause('title'), [$term])
                ->orWhereRaw(LikeSearch::clause('excerpt'), [$term]);
        });

        return $query;
    }

    public function findBySlug(string $slug): ?BlogPost
    {
        // A slug is only unique inside its own language, so the lookup is
        // scoped; a post with no translation yet resolves through the
        // default-language fallback.
        return BlogPost::with(['category', 'author', 'files'])
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
        return ['status', 'category_id', 'search'];
    }

    /**
     * Süzgeçler uygulanmış, sayfalanmamış sorgu.
     *
     * @param array<string, mixed> $filters
     * @return Builder<BlogPost>
     */
    public function query(array $filters = []): Builder
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

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $this->whereGroupMatches($query, BlogPost::class, function ($q) use ($search): void {
                $q->whereRaw(LikeSearch::clause('title'), [LikeSearch::term($search)])
                  ->orWhereRaw(LikeSearch::clause('excerpt'), [LikeSearch::term($search)]);
            });
        }

        if (! empty($filters['category_id'])) {
            // The chosen category belongs to one language, so a post counts as
            // a match when any of its translations sits in that group.
            $this->whereGroupMatches($query, BlogPost::class, function ($q) use ($filters): void {
                $q->where('blog_category_id', $filters['category_id']);
            });
        }

        return $query;
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->attachGroupLocales($this->query($filters)->paginate($perPage), BlogPost::class);
    }

    /**
     * @return array{total: int, published: int, draft: int, total_views: int}
     */
    public function getAdminStats(): array
    {
        return Cache::remember(CacheKeys::BLOG_ADMIN_STATS, 300, function (): array {
            // "Published" means live on the site, so the date has to have
            // arrived — same rule the status tabs and the front use.
            $counts = $this->onlyGroupRepresentatives(BlogPost::withTrashed(), BlogPost::class)->selectRaw("
                SUM(CASE WHEN deleted_at IS NULL THEN 1 ELSE 0 END) as total,
                SUM(CASE WHEN deleted_at IS NULL AND status = 'published' AND published_at <= ? THEN 1 ELSE 0 END) as published,
                SUM(CASE WHEN deleted_at IS NULL AND status = 'draft' THEN 1 ELSE 0 END) as draft,
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

        $counts = $this->onlyGroupRepresentatives(BlogPost::withTrashed(), BlogPost::class)
            ->selectRaw('sum(case when deleted_at is null then 1 else 0 end) as total')
            ->selectRaw("sum(case when deleted_at is null and status = 'published' and published_at <= ? then 1 else 0 end) as published", [$now])
            ->selectRaw("sum(case when deleted_at is null and status = 'draft' then 1 else 0 end) as draft")
            ->selectRaw("sum(case when deleted_at is null and status = 'published' and published_at > ? then 1 else 0 end) as scheduled", [$now])
            ->selectRaw("sum(case when deleted_at is null and status = 'archived' then 1 else 0 end) as archived")
            ->selectRaw('sum(case when deleted_at is not null then 1 else 0 end) as trashed')
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
        // Ekler sütun değil ayrı satır; belirteçler modele ulaşmadan çıkarılıyor.
        $fileTokens = $this->extractFileTokens($translations);

        $groupId = $this->saveTranslations(
            BlogPost::class,
            $translations,
            fn (array $fields, string $locale, ?BlogPost $existing, ?BlogPost $default): array =>
                $this->prepareImageField($this->normalizePublishDate($fields) + $shared, $existing, 'blog', 'title', 'image', $default),
        );

        $this->syncPendingFiles(BlogPost::class, $groupId, $fileTokens);
        $this->clearCache();

        return $groupId;
    }

    /**
     * @param array<string, array<string, mixed>> $translations locale => fields
     * @param array<string, mixed> $shared
     */
    public function updateTranslated(BlogPost $post, array $translations, array $shared = []): string
    {
        $fileTokens = $this->extractFileTokens($translations);

        $groupId = $this->saveTranslations(
            BlogPost::class,
            $translations,
            fn (array $fields, string $locale, ?BlogPost $existing, ?BlogPost $default): array =>
                $this->prepareImageField($this->normalizePublishDate($fields) + $shared, $existing, 'blog', 'title', 'image', $default),
            $post->lang_group_id,
        );

        $this->syncPendingFiles(BlogPost::class, $groupId, $fileTokens);
        $this->clearCache();

        return $groupId;
    }

    public function delete(BlogPost $post): void
    {
        $this->deleteTranslationGroup($post);
        $this->clearCache();
    }

    /**
     * Seçilen içerikleri tek seferde yayına alır ya da taslağa çeker.
     *
     * Durum bütün çeviri grubuna işliyor: listede tek satır görünen bir
     * içeriğin Türkçesi yayında, İngilizcesi taslakta kalsaydı ön yüzde
     * yarısı görünen bir içerik olurdu.
     *
     * Yayına alırken tarihi olmayan içeriğe şimdiki zaman yazılıyor; tarihsiz
     * bir yazı "yayında" görünüp listede hiç çıkmıyordu.
     *
     * @param  list<int> $ids
     * @return int       durumu değişen içerik sayısı
     */
    public function changeStatusMany(array $ids, ContentStatus $status): int
    {
        if ($ids === []) {
            return 0;
        }

        $gruplar = BlogPost::whereIn('id', $ids)->pluck('lang_group_id')->unique()->all();

        if ($gruplar === []) {
            return 0;
        }

        DB::transaction(function () use ($gruplar, $status): void {
            $degerler = ['status' => $status->value];

            BlogPost::whereIn('lang_group_id', $gruplar)->update($degerler);

            if ($status === ContentStatus::Published) {
                BlogPost::whereIn('lang_group_id', $gruplar)
                    ->whereNull('published_at')
                    ->update(['published_at' => now()]);
            }
        });

        $this->clearCache();

        return count($gruplar);
    }

    /**
     * Listede seçilen içerikleri tek seferde siler.
     *
     * Döngü ListsTranslationGroups içinde: liste her çeviri grubunu tek
     * satırla gösteriyor, silme de grup grup işliyor — bir içerikin
     * Türkçesini silip İngilizcesini bırakmak ön yüzde sahipsiz bir çeviri
     * bırakırdı. Dönen sayı seçilen satır değil, silinen kayıt sayısı.
     *
     * @param  list<int> $ids
     * @return int       silinen kayıt sayısı
     */
    public function deleteMany(array $ids): int
    {
        $silinen = $this->deleteGroupsById(BlogPost::class, $ids);

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
        $geriYuklenen = $this->restoreGroupsById(BlogPost::class, $ids);

        if ($geriYuklenen > 0) {
            $this->clearCache();
        }

        return $geriYuklenen;
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
        Cache::forget(CacheKeys::BLOG_CATEGORIES_ACTIVE);
        Cache::forget(CacheKeys::BLOG_ADMIN_STATS);
        Cache::forget(CacheKeys::SITEMAP_URLS);
        Cache::forget(CacheKeys::SITEMAP_PAGE_GROUPS);
    }
}
