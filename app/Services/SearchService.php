<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SearchType;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Faq;
use App\Models\GalleryCategory;
use App\Models\GalleryItem;
use App\Models\Page;
use App\Support\LikeSearch;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Site geneli arama — tek kutu, bütün içerik.
 *
 * ── Neden birleşik (UNION) sorgu ──
 *
 * Türleri ayrı ayrı sorgulayıp PHP'de birleştirmek daha kolay olurdu ama
 * sayfalama bozulurdu: doğru toplam sayı ve doğru sayfa dilimi için her türden
 * bütün eşleşmeleri belleğe çekmek gerekirdi. UNION ile veritabanı tek bir
 * sonuç kümesi üretiyor; sayfalama, toplam ve sıralama onun üzerinde çalışıyor.
 *
 * ── Alaka sıralaması ──
 *
 * Tam metin dizini yok (gerekçesi config/search.php'de), o yüzden sıralama üç
 * kademeli bir puanla yapılıyor: başlığı terimle BAŞLAYAN 3, başlığında
 * GEÇEN 2, yalnız gövdesinde geçen 1. Eşit puanlılar tarihe göre. Puansız
 * sıralamada "hakkımızda" araması, kelimeyi metninin ortasında geçiren bir
 * yazıyı "Hakkımızda" sayfasının üstüne koyabiliyordu.
 *
 * ── Dil ──
 *
 * Her tür kendi `localeWithFallback` kapsamından geçiyor: ziyaretçinin dilinde
 * olan gelir, o dile çevrilmemiş içerik varsayılan dilden düşer. İki dilin aynı
 * içeriği iki sonuç olarak görünmez.
 */
final class SearchService
{
    public function __construct(
        private readonly LanguageService $languages,
    ) {}

    /**
     * Terim aranabilir mi?
     *
     * Tek harflik terim pratikte bütün siteyi döndürüyor ve ziyaretçiye hiçbir
     * şey anlatmıyor; arama hiç yapılmamış sayılıyor.
     */
    public function isSearchable(?string $term): bool
    {
        return mb_strlen(trim((string) $term)) >= (int) config('search.min_length', 2);
    }

    /**
     * Terimi sınırlar içine çeker.
     *
     * Reddetmiyor kırpıyor: bir sayfa isteğinde ziyaretçiye doğrulama hatası
     * göstermek onu boş ekranla baş başa bırakırdı.
     */
    public function normalize(?string $term): ?string
    {
        $term = trim((string) $term);
        $term = mb_substr($term, 0, (int) config('search.max_length', 100));

        return $term === '' ? null : $term;
    }

    /**
     * Arama sonuçları, sayfalı.
     *
     * @param SearchType|null $only Yalnız bu tür — null ise hepsi.
     * @return LengthAwarePaginator<int, object>
     */
    public function search(string $term, ?SearchType $only = null, ?int $perPage = null): LengthAwarePaginator
    {
        $perPage ??= (int) config('search.per_page', 10);

        return $this->union($term, $only)
            ->orderByDesc('score')
            ->orderByDesc('sort_date')
            // Aynı puan ve aynı tarihteki satırlar için kararlı bir sıra:
            // olmadan sayfa 2, sayfa 1'deki bir kaydı tekrar gösterebiliyor.
            ->orderBy('type')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Tür başına eşleşme sayısı — süzgeç çubuğundaki rozetler için.
     *
     * Tek sorguda toplanıyor: her tür için ayrı sayım, süzgeç çubuğunu dört
     * sorgu pahasına çizmek olurdu.
     *
     * @return array<string, int>
     */
    public function countsByType(string $term): array
    {
        $rows = DB::query()
            ->fromSub($this->union($term, null), 'results')
            ->select('type', DB::raw('count(*) as total'))
            ->groupBy('type')
            ->pluck('total', 'type');

        $counts = [];

        foreach (SearchType::enabled() as $type) {
            $counts[$type->value] = (int) ($rows[$type->value] ?? 0);
        }

        return $counts;
    }

    /**
     * Sonuç satırını görünümün beklediği şekle çevirir.
     *
     * @param object $row
     * @return array<string, mixed>
     */
    public function present(object $row): array
    {
        $type = SearchType::from((string) $row->type);

        $raw = (array) $row;

        return [
            'type'    => $type,
            'id'      => (int) $row->id,
            'title'   => (string) $row->title,
            'snippet' => $this->snippet((string) ($row->snippet ?? '')),
            'image'   => $row->image ?? null,
            'url'     => $type->url($raw),
            'date'    => $row->sort_date ?? null,
        ];
    }

    // ── İç işleyiş ──

    /**
     * Bütün türlerin birleşik sorgusu.
     */
    private function union(string $term, ?SearchType $only): QueryBuilder
    {
        $types = SearchType::enabled();

        if ($only !== null) {
            $types = array_values(array_filter($types, fn (SearchType $t): bool => $t === $only));
        }

        $parts = [];

        foreach ($types as $type) {
            $parts[] = match ($type) {
                SearchType::Blog    => $this->blogQuery($term),
                SearchType::Page    => $this->pageQuery($term),
                SearchType::Faq     => $this->faqQuery($term),
                SearchType::Gallery => $this->galleryQuery($term),
            };
        }

        if ($parts === []) {
            // Hiç tür yoksa boş bir sonuç kümesi: çağıran yine sayfalayabilsin.
            return DB::query()->fromSub($this->emptyQuery(), 'results');
        }

        $union = array_shift($parts);

        foreach ($parts as $part) {
            $union->unionAll($part);
        }

        return DB::query()->fromSub($union, 'results');
    }

    /**
     * Puan ifadesi ve bağlamaları.
     *
     * @return array{0: string, 1: array<int, string>}
     */
    private function score(string $titleColumn, string $term): array
    {
        $sql = 'CASE'
            . ' WHEN ' . LikeSearch::clause($titleColumn) . ' THEN 3'
            . ' WHEN ' . LikeSearch::clause($titleColumn) . ' THEN 2'
            . ' ELSE 1 END';

        return [$sql, [LikeSearch::prefix($term), LikeSearch::term($term)]];
    }

    /**
     * @param array<int, string> $columns Aranacak sütunlar
     */
    private function matches(QueryBuilder $query, array $columns, string $term): void
    {
        $pattern = LikeSearch::term($term);

        $query->where(function (QueryBuilder $inner) use ($columns, $pattern): void {
            foreach ($columns as $column) {
                $inner->orWhereRaw(LikeSearch::clause($column), [$pattern]);
            }
        });
    }

    private function blogQuery(string $term): QueryBuilder
    {
        [$score, $bindings] = $this->score('blog_posts.title', $term);

        // Kategori adresi JOIN ile değil alt sorguyla alınıyor. JOIN, iki
        // tablonun da `locale` sütunu olduğu için dil kapsamını belirsiz
        // bırakıyordu ("ambiguous column name: locale") — ve zaten tek bir
        // sütun için bütün satırları birleştirmeye gerek yok.
        $query = BlogPost::query()
            ->published()
            ->localeWithFallback()
            ->toBase()
            ->selectRaw("'blog' as type")
            ->addSelect([
                'blog_posts.id',
                'blog_posts.title',
                'blog_posts.excerpt as snippet',
                'blog_posts.slug',
            ])
            ->selectSub(
                BlogCategory::query()
                    ->select('slug')
                    ->whereColumn('blog_categories.id', 'blog_posts.blog_category_id')
                    ->limit(1),
                'category_slug',
            )
            ->addSelect([
                'blog_posts.image',
                'blog_posts.published_at as sort_date',
            ])
            ->selectRaw("({$score}) as score", $bindings);

        $this->matches($query, ['blog_posts.title', 'blog_posts.excerpt', 'blog_posts.body'], $term);

        return $query;
    }

    private function pageQuery(string $term): QueryBuilder
    {
        [$score, $bindings] = $this->score('pages.title', $term);

        $query = Page::query()
            ->published()
            ->localeWithFallback()
            ->toBase()
            ->selectRaw("'page' as type")
            ->addSelect([
                'pages.id',
                'pages.title',
                'pages.excerpt as snippet',
                'pages.slug',
            ])
            ->selectRaw('null as category_slug')
            ->addSelect(['pages.image', 'pages.published_at as sort_date'])
            ->selectRaw("({$score}) as score", $bindings);

        $this->matches($query, ['pages.title', 'pages.excerpt', 'pages.content'], $term);

        return $query;
    }

    private function faqQuery(string $term): QueryBuilder
    {
        [$score, $bindings] = $this->score('faqs.question', $term);

        $query = Faq::query()
            ->where('is_active', true)
            ->localeWithFallback()
            ->toBase()
            ->selectRaw("'faq' as type")
            ->addSelect([
                'faqs.id',
                'faqs.question as title',
                'faqs.answer as snippet',
            ])
            ->selectRaw('null as slug')
            ->selectRaw('null as category_slug')
            ->selectRaw('null as image')
            ->addSelect(['faqs.created_at as sort_date'])
            ->selectRaw("({$score}) as score", $bindings);

        $this->matches($query, ['faqs.question', 'faqs.answer'], $term);

        return $query;
    }

    private function galleryQuery(string $term): QueryBuilder
    {
        [$score, $bindings] = $this->score('gallery_items.title', $term);

        $query = GalleryItem::query()
            ->where('gallery_items.is_active', true)
            ->localeWithFallback()
            ->toBase()
            ->selectRaw("'gallery' as type")
            ->addSelect([
                'gallery_items.id',
                'gallery_items.title',
                'gallery_items.description as snippet',
            ])
            ->selectRaw('null as slug')
            ->selectSub(
                GalleryCategory::query()
                    ->select('slug')
                    ->whereColumn('gallery_categories.id', 'gallery_items.gallery_category_id')
                    ->limit(1),
                'category_slug',
            )
            ->addSelect([
                'gallery_items.image',
                'gallery_items.created_at as sort_date',
            ])
            ->selectRaw("({$score}) as score", $bindings);

        $this->matches($query, ['gallery_items.title', 'gallery_items.description'], $term);

        return $query;
    }

    /**
     * Hiç tür açık değilken kullanılan boş küme.
     */
    private function emptyQuery(): QueryBuilder
    {
        return DB::query()
            ->selectRaw("'blog' as type, 0 as id, '' as title, '' as snippet, null as slug, null as category_slug, null as image, null as sort_date, 0 as score")
            ->whereRaw('1 = 0');
    }

    /**
     * Kartta gösterilecek özet: etiketsiz ve kırpılmış.
     *
     * Sayfa içeriği ve blog gövdesi zengin metin editöründen geliyor, yani
     * HTML. Ham basılsaydı özet "<p>" ile başlardı ya da yarım bir etiket
     * sayfanın düzenini bozardı.
     */
    private function snippet(string $value): string
    {
        $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? '');

        return Str::limit($plain, (int) config('search.snippet_length', 180));
    }

    /**
     * Sitenin varsayılan dili — çağıranların tekrar sormaması için.
     */
    public function defaultLocale(): string
    {
        return $this->languages->defaultCode();
    }
}
