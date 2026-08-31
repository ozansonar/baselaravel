<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\LikeSearch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;

/**
 * Bütün içerik türlerini tek listede toplar.
 *
 * Blog, sayfa, galeri ve SSS ayrı ekranlarda duruyordu; "geçen ay ne
 * yayınladık" sorusunun cevabı dört ekranı gezmekten geçiyordu.
 *
 * Dört tabloyu tek sorguda birleştiriyor (UNION) — dört ayrı sorgu çekip
 * bellekte birleştirmek de mümkündü ama o zaman sıralama ve sayfalama da
 * bellekte olurdu: bin yazılık bir sitede her sayfa açılışında bin satır
 * okumak demek.
 *
 * Türlerin sütunları farklı; birleşim ortak bir biçime çeviriyor:
 *
 *   type · id · title · locale · status · created_at · updated_at
 *
 * Durum da öyle: blog ve sayfa enum taşıyor ('draft'/'published'), galeri ve
 * SSS ise bir bayrak (is_active). İkisi de aynı iki değere indirgeniyor,
 * yoksa süzgeç dört farklı şeyi soruyor olurdu.
 */
final class ContentListService
{
    /** Listelenen türler ve ekranda görünen adları. */
    public const TYPES = [
        'blog_post'    => 'Blog Yazısı',
        'page'         => 'Sayfa',
        'gallery_item' => 'Galeri Öğesi',
        'faq'          => 'SSS',
    ];

    /** Tür → düzenleme rotası. */
    public const ROUTES = [
        'blog_post'    => 'admin.blog-posts.edit',
        'page'         => 'admin.pages.edit',
        'gallery_item' => 'admin.gallery-items.edit',
        'faq'          => 'admin.faqs.edit',
    ];

    /**
     * @return list<string>
     */
    public function filterKeys(): array
    {
        return ['search', 'type', 'locale', 'status', 'from', 'to', 'sort'];
    }

    /**
     * Sıralanmış, sayfalanmamış satırlar.
     *
     * Ekran da dışa aktarma da buradan okuyor: dosyaya inen liste ile ekranda
     * duran liste aynı sorgunun ürünü, yoksa süzgeç ya da sıralama zamanla
     * ikisinde ayrışırdı.
     *
     * @param array<string, mixed> $filters
     */
    public function rows(array $filters = []): Builder
    {
        return DB::query()
            ->fromSub($this->query($filters), 'icerik')
            ->orderBy('created_at', ($filters['sort'] ?? 'desc') === 'asc' ? 'asc' : 'desc');
    }

    /**
     * Süzgeçlere uyan kayıt sayısı.
     *
     * @param array<string, mixed> $filters
     */
    public function count(array $filters = []): int
    {
        return DB::query()->fromSub($this->query($filters), 'icerik')->count();
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function paginate(int $perPage = 25, array $filters = []): LengthAwarePaginator
    {
        $page = Paginator::resolveCurrentPage();
        $total = $this->count($filters);

        $rows = $this->rows($filters)
            ->forPage($page, $perPage)
            ->get();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $rows,
            $total,
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath(), 'query' => request()->query()],
        );
    }

    /**
     * Tür başına sayılar — durum sekmelerinin rakamları.
     *
     * @param array<string, mixed> $filters
     * @return array<string, int>
     */
    public function counts(array $filters = []): array
    {
        // Tür süzgeci sayımların dışında: sekmedeki rakam, o sekmeye
        // tıklandığında kaç kayıt geleceğini söylemeli.
        unset($filters['type']);

        $rows = DB::query()
            ->fromSub($this->query($filters), 'icerik')
            ->select('type', DB::raw('COUNT(*) as adet'))
            ->groupBy('type')
            ->pluck('adet', 'type');

        $counts = ['all' => 0];

        foreach (array_keys(self::TYPES) as $type) {
            $counts[$type] = (int) ($rows[$type] ?? 0);
            $counts['all'] += $counts[$type];
        }

        return $counts;
    }

    /**
     * Dört tablonun birleşimi.
     *
     * @param array<string, mixed> $filters
     */
    private function query(array $filters = []): Builder
    {
        $types = [
            'blog_post'    => $this->part('blog_posts', 'blog_post', 'title', "status = 'published'"),
            'page'         => $this->part('pages', 'page', 'title', "status = 'published'"),
            'gallery_item' => $this->part('gallery_items', 'gallery_item', 'title', 'is_active = 1'),
            'faq'          => $this->part('faqs', 'faq', 'question', 'is_active = 1'),
        ];

        $wanted = isset($filters['type']) && isset($types[$filters['type']])
            ? [$filters['type'] => $types[$filters['type']]]
            : $types;

        /** @var Builder|null $union */
        $union = null;

        foreach ($wanted as $part) {
            $union = $union === null ? $part : $union->unionAll($part);
        }

        /** @var Builder $union */
        $query = DB::query()->fromSub($union, 'birlesim');

        return $this->applyFilters($query, $filters);
    }

    /**
     * Tek bir tablonun ortak biçime çevrilmiş hâli.
     */
    private function part(string $table, string $type, string $titleColumn, string $publishedCondition): Builder
    {
        return DB::table($table)
            ->selectRaw("'{$type}' as type")
            ->addSelect('id')
            ->selectRaw("{$titleColumn} as title")
            ->addSelect('locale')
            // Durum iki değere indirgeniyor: dört ayrı sözlük, dört ayrı
            // süzgeç demekti.
            ->selectRaw("CASE WHEN {$publishedCondition} THEN 'published' ELSE 'draft' END as status")
            ->addSelect('created_at', 'updated_at')
            ->whereNull('deleted_at');
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['search'])) {
            $query->whereRaw(LikeSearch::clause('title'), [LikeSearch::term((string) $filters['search'])]);
        }

        if (! empty($filters['locale'])) {
            $query->where('locale', $filters['locale']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status'] === 'published' ? 'published' : 'draft');
        }

        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        return $query;
    }
}
