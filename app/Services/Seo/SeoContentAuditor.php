<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Enums\SeoLevel;
use App\Models\BlogPost;
use App\Models\Page;
use App\Support\CacheKeys;
use App\Support\Seo\BodyDocument;
use App\Support\Seo\SeoSubject;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\Cache;

/**
 * Kayıtlı içerikleri toplu denetler.
 *
 * Form paneli tek bir içeriğe bakıyor; bu, "sitede genel olarak ne durumdayız"
 * sorusunu yanıtlıyor. İkisi aynı motoru kullanıyor — yoksa panelde temiz
 * görünen bir sayfa listede sorunlu çıkabilirdi.
 *
 * Denetim pahalı: her içeriğin gövdesi okunup ayrıştırılıyor. Bu yüzden sonuç
 * kısa süre önbellekte tutuluyor ve içerik kaydedildiğinde kendi satırı
 * düşüyor. Sıralama ve süzme önbellekten sonra, bellekte yapılıyor: skor
 * veritabanında durmadığı için SQL ile sıralanamıyor.
 */
final class SeoContentAuditor
{
    public function __construct(
        private readonly SeoAuditor $auditor,
        private readonly \App\Services\CachePurger $cache,
    ) {}

    /**
     * Listenin tanıdığı süzgeç anahtarları.
     *
     * @return list<string>
     */
    public function filterKeys(): array
    {
        return ['type', 'locale', 'level', 'search'];
    }

    /**
     * Denetlenmiş içerikler.
     *
     * @param  array<string, mixed> $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginate(int $perPage = 25, array $filters = [], int $page = 1): LengthAwarePaginator
    {
        $rows = $this->rows($filters);

        $slice = array_slice($rows, ($page - 1) * $perPage, $perPage);

        return new Paginator($slice, count($rows), $perPage, $page, [
            'path'  => request()->url(),
            'query' => request()->query(),
        ]);
    }

    /**
     * Seviyeye göre toplamlar — üst şeritteki sayaçlar.
     *
     * @param  array<string, mixed> $filters
     * @return array{total: int, error: int, warning: int, info: int, clean: int}
     */
    public function summary(array $filters = []): array
    {
        // Seviye süzgeci sayımların dışında: sekmedeki rakam, o sekmeye
        // tıklandığında kaç kayıt geleceğini söylemeli.
        unset($filters['level']);

        $rows = $this->rows($filters);

        return [
            'total'   => count($rows),
            'error'   => count(array_filter($rows, static fn (array $r): bool => $r['counts']['error'] > 0)),
            'warning' => count(array_filter($rows, static fn (array $r): bool => $r['counts']['error'] === 0 && $r['counts']['warning'] > 0)),
            'info'    => count(array_filter($rows, static fn (array $r): bool => $r['counts']['error'] === 0 && $r['counts']['warning'] === 0 && $r['counts']['info'] > 0)),
            'clean'   => count(array_filter($rows, static fn (array $r): bool => $r['score'] === 100)),
        ];
    }

    /**
     * Bir içeriğin denetim satırını düşürür.
     *
     * İçerik kaydedildiğinde çağrılıyor: liste bir sonraki açılışta o kaydı
     * yeniden denetliyor, ötekiler önbellekte kalıyor.
     */
    public function forget(Model $model): void
    {
        Cache::forget($this->cacheKey($model));
    }

    /**
     * Süzülmüş ve sıralanmış satırlar.
     *
     * @param  array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    private function rows(array $filters): array
    {
        $rows = [];

        foreach ($this->contents($filters) as $model) {
            $rows[] = $this->row($model);
        }

        // Ayrıştırılmış gövdeler bellekte birikmesin: liste yüzlerce içerik
        // gezebiliyor ve her birinin HTML'i tutulursa bellek şişer.
        BodyDocument::flush();

        $level = is_string($filters['level'] ?? null) ? $filters['level'] : '';

        if ($level !== '') {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => $level === 'clean'
                    ? $row['score'] === 100
                    : ($row['counts'][$level] ?? 0) > 0,
            ));
        }

        // En kötü önce: listenin işi hangi içeriğe önce bakılacağını söylemek.
        usort($rows, static fn (array $a, array $b): int => [$a['score'], $a['title']] <=> [$b['score'], $b['title']]);

        return $rows;
    }

    /**
     * Denetlenecek içerikler.
     *
     * @param  array<string, mixed> $filters
     * @return list<Model>
     */
    private function contents(array $filters): array
    {
        $type = is_string($filters['type'] ?? null) ? $filters['type'] : '';
        $locale = is_string($filters['locale'] ?? null) ? $filters['locale'] : '';
        $search = is_string($filters['search'] ?? null) ? trim($filters['search']) : '';

        $models = [];

        if ($type === '' || $type === 'page') {
            $models = array_merge($models, $this->query(Page::query(), $locale, $search));
        }

        if ($type === '' || $type === 'blog_post') {
            $models = array_merge($models, $this->query(BlogPost::query(), $locale, $search));
        }

        return $models;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<covariant Model> $query
     * @return list<Model>
     */
    private function query($query, string $locale, string $search): array
    {
        if ($locale !== '') {
            $query->where('locale', $locale);
        }

        if ($search !== '') {
            $query->whereRaw(\App\Support\LikeSearch::clause('title'), [\App\Support\LikeSearch::term($search)]);
        }

        return $query->latest('id')->get()->all();
    }

    /**
     * Tek bir içeriğin satırı — önbellekten ya da taze denetimden.
     *
     * @return array<string, mixed>
     */
    private function row(Model $model): array
    {
        $ttl = (int) config('seo.audit_cache_ttl', 900);

        /** @var array<string, mixed> $row */
        $row = $this->cache->rememberWithin(CacheKeys::PREFIX_SEO_AUDIT, $this->cacheKey($model), $ttl, function () use ($model): array {
            $report = $this->auditor->audit($this->subject($model));

            return [
                'type'     => $model instanceof Page ? 'page' : 'blog_post',
                'id'       => $model->getKey(),
                'title'    => (string) $model->getAttribute('title'),
                'slug'     => (string) $model->getAttribute('slug'),
                'locale'   => (string) $model->getAttribute('locale'),
                'score'    => $report->score,
                'grade'    => $report->grade(),
                'counts'   => [
                    'error'   => $report->count(SeoLevel::Error),
                    'warning' => $report->count(SeoLevel::Warning),
                    'info'    => $report->count(SeoLevel::Info),
                ],
                'issues'   => array_map(
                    static fn ($issue): array => $issue->toArray(),
                    $report->issues,
                ),
            ];
        });

        return $row;
    }

    private function subject(Model $model): SeoSubject
    {
        return new SeoSubject(
            locale: (string) $model->getAttribute('locale'),
            title: (string) $model->getAttribute('title'),
            slug: (string) $model->getAttribute('slug'),
            body: (string) ($model->getAttribute('body') ?? $model->getAttribute('content') ?? ''),
            metaTitle: $model->getAttribute('meta_title'),
            metaDescription: $model->getAttribute('meta_description'),
            coverImage: $model->getAttribute('image'),
            type: $model instanceof Page ? 'page' : 'blog_post',
        );
    }

    private function cacheKey(Model $model): string
    {
        // Anahtar güncelleme zamanını taşıyor: içerik değişince kayıt
        // kendiliğinden bayatlıyor, ayrıca silmeye gerek kalmıyor.
        $stamp = $model->getAttribute('updated_at')?->timestamp ?? 0;

        return CacheKeys::PREFIX_SEO_AUDIT . class_basename($model) . '.' . $model->getKey() . '.' . $stamp;
    }
}
