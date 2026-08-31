<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Site aramasının kapsadığı içerik türleri.
 *
 * Her tür kendi adresini biliyor: sonuç kartı hangi türden geldiğine bakmadan
 * `url()` çağırıyor. Adres kurma mantığı görünüme dağılsaydı, panelden bir
 * rotanın adresi değiştiğinde dört ayrı yerde düzeltme gerekirdi.
 */
enum SearchType: string
{
    case Blog = 'blog';
    case Page = 'page';
    case Faq = 'faq';
    case Gallery = 'gallery';

    public function label(): string
    {
        return match ($this) {
            self::Blog    => __('site.search.type_blog'),
            self::Page    => __('site.search.type_page'),
            self::Faq     => __('site.search.type_faq'),
            self::Gallery => __('site.search.type_gallery'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Blog    => 'fa-solid fa-newspaper',
            self::Page    => 'fa-solid fa-file-lines',
            self::Faq     => 'fa-solid fa-circle-question',
            self::Gallery => 'fa-solid fa-images',
        };
    }

    /**
     * Sonucun ziyaretçiye açılacağı adres.
     *
     * SSS'nin kendi sayfası yok: akordeon başlıklarının kimlikleri sıra
     * numarasından türüyor, yani karta bağlanabilecek kalıcı bir çapa yok.
     * Sonuç SSS sayfasının kendisine gidiyor — yanlış bir çapaya götürmektense.
     *
     * @param array{slug?: string|null, category_slug?: string|null} $row
     */
    public function url(array $row): string
    {
        return match ($this) {
            self::Blog => localized_route('blog.show', [
                'categorySlug' => (string) ($row['category_slug'] ?? ''),
                'slug'         => (string) ($row['slug'] ?? ''),
            ]),
            self::Page => localized_route('pages.show', ['slug' => (string) ($row['slug'] ?? '')]),
            self::Faq  => localized_route('faq'),
            self::Gallery => localized_route('gallery') . (
                ($row['category_slug'] ?? null) ? '?kategori=' . urlencode((string) $row['category_slug']) : ''
            ),
        };
    }

    /**
     * Yapılandırmada açık olan türler.
     *
     * @return list<self>
     */
    public static function enabled(): array
    {
        /** @var array<int, string> $configured */
        $configured = (array) config('search.types', []);

        return array_values(array_filter(
            array_map(static fn (string $value): ?self => self::tryFrom($value), $configured),
        ));
    }
}
