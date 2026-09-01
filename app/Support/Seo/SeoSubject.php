<?php

declare(strict_types=1);

namespace App\Support\Seo;

/**
 * Denetlenen içeriğin, denetimin gördüğü hâli.
 *
 * Denetleyici modelleri tanımıyor — bilerek. İki sebebi var:
 *
 *  1. **Kaydedilmemiş içerik de denetleniyor.** Form ekranı, yazar daha
 *     kaydetmeden denetim istiyor; ortada bir model yok, yalnız form alanları
 *     var. Denetleyici modele bağlı olsaydı o an hiçbir şey söyleyemezdi.
 *  2. **Türler farklı, sorular aynı.** Sayfanın `content`'i, yazının `body`'si
 *     ve ileride eklenecek bir türün başka adlı alanı aynı denetimden geçmeli.
 *     Alan adlarını burada bir kez eşlemek, her kuralı türden bağımsız kılıyor.
 */
final readonly class SeoSubject
{
    /**
     * @param string      $locale   İçeriğin dili — denetim dile duyarlı
     * @param string      $title    Sayfa/yazı başlığı
     * @param string      $slug     Adres parçası
     * @param string      $body     Gövde (HTML)
     * @param string|null $metaTitle
     * @param string|null $metaDescription
     * @param string|null $coverImage Kapak görseli yolu
     * @param string|null $type     İçerik türü etiketi — bulgu mesajlarında geçiyor
     */
    public function __construct(
        public string $locale,
        public string $title = '',
        public string $slug = '',
        public string $body = '',
        public ?string $metaTitle = null,
        public ?string $metaDescription = null,
        public ?string $coverImage = null,
        public ?string $type = null,
    ) {}

    /**
     * İstekten gelen ham form verisinden kurar.
     *
     * Alan adları türe göre değişiyor (`content` / `body`); ikisi de kabul
     * ediliyor, çünkü denetim ucuna hangi formun yazdığı önceden bilinmiyor.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $text = static fn (string $key): string => is_scalar($data[$key] ?? null)
            ? trim((string) $data[$key])
            : '';

        $nullable = static function (string $key) use ($data): ?string {
            $value = is_scalar($data[$key] ?? null) ? trim((string) $data[$key]) : '';

            return $value === '' ? null : $value;
        };

        return new self(
            locale: $text('locale') !== '' ? $text('locale') : app()->getLocale(),
            title: $text('title'),
            slug: $text('slug'),
            // Sayfa `content`, yazı `body` diyor.
            body: $text('body') !== '' ? $text('body') : $text('content'),
            metaTitle: $nullable('meta_title'),
            metaDescription: $nullable('meta_description'),
            coverImage: $nullable('image'),
            type: $nullable('type'),
        );
    }

    /**
     * Arama sonucunda görünecek başlık.
     *
     * Meta başlık boşsa motor sayfa başlığını kullanıyor; uzunluk denetimi de
     * gerçekte görünecek metne bakmalı, boş alana değil.
     */
    public function effectiveTitle(): string
    {
        return $this->metaTitle ?? $this->title;
    }

    /** Gövdenin etiketlerden arınmış hâli — uzunluk ölçümü için. */
    public function plainBody(): string
    {
        $text = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', ' ', $this->body) ?? $this->body;

        return trim((string) preg_replace('/\s+/u', ' ', strip_tags($text)));
    }
}
