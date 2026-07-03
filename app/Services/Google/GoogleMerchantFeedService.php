<?php

declare(strict_types=1);

namespace App\Services\Google;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Google Merchant Center XML feed üretici.
 *
 * Spec: https://support.google.com/merchants/answer/7052112
 * Format: RSS 2.0 + g: namespace (Google Shopping)
 *
 * Çıktı: /feeds/google-merchant.xml endpoint'inde 6 saat cache'lenir.
 * Google Merchant Center "Scheduled Fetch" ile günde 1-2 kez çeker.
 */
final class GoogleMerchantFeedService
{
    public const string CACHE_KEY  = 'google_merchant.feed.xml';
    public const int    CACHE_TTL  = 21600; // 6 saat
    private const string CACHE_META = 'google_merchant.feed.meta';

    public function siteName(): string
    {
        return (string) Setting::getValue('site_name', config('app.name', 'Orhan Babanın Çiftliği'));
    }

    public function siteUrl(): string
    {
        return rtrim((string) config('app.url', 'https://orhanbabaninciftligi.com'), '/');
    }

    public function feedUrl(): string
    {
        return $this->siteUrl() . '/feeds/google-merchant.xml';
    }

    /**
     * Cache'li XML döndür. Cache yoksa yeniden üret.
     */
    public function getOrBuildXml(): string
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function (): string {
            return $this->buildXml();
        });
    }

    /**
     * Cache'i temizle ve yeniden üret (admin "Cache Temizle" butonu).
     */
    public function rebuild(): string
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::CACHE_META);

        return $this->getOrBuildXml();
    }

    /**
     * Feed'de yer alacak ürünleri çek (eager-loaded, aktif, görseli olan).
     *
     * @return Collection<int, Product>
     */
    public function eligibleProducts(): Collection
    {
        return Product::query()
            ->with(['category', 'images'])
            ->where('status', ProductStatus::Active)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Feed'i üret + meta bilgi cache'le (admin sayfası için).
     */
    public function buildXml(): string
    {
        $products = $this->eligibleProducts();
        $now      = now();

        $valid    = 0;
        $invalid  = 0;
        $issues   = [];

        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->setIndentString('  ');
        $xml->startDocument('1.0', 'UTF-8');

        $xml->startElementNs(null, 'rss', null);
        $xml->writeAttribute('version', '2.0');
        $xml->writeAttribute('xmlns:g', 'http://base.google.com/ns/1.0');

        $xml->startElement('channel');
        $xml->writeElement('title', $this->siteName());
        $xml->writeElement('link', $this->siteUrl());
        $xml->writeElement('description', "Orhan Babanın Çiftliği — Çorum'dan doğal köy ürünleri. Süt, peynir, tereyağı, yumurta.");

        foreach ($products as $product) {
            $itemIssues = $this->validateProduct($product);
            if ($itemIssues !== []) {
                $invalid++;
                $issues[$product->id] = [
                    'name'   => $product->name,
                    'errors' => $itemIssues,
                ];
                continue;
            }

            $this->writeItem($xml, $product);
            $valid++;
        }

        $xml->endElement(); // channel
        $xml->endElement(); // rss

        Cache::put(self::CACHE_META, [
            'generated_at'  => $now,
            'total'         => $products->count(),
            'valid'         => $valid,
            'invalid'       => $invalid,
            'issues'        => $issues,
        ], self::CACHE_TTL);

        return $xml->outputMemory();
    }

    /**
     * Tek bir ürün için <item> bloğu yaz.
     */
    private function writeItem(\XMLWriter $xml, Product $product): void
    {
        $xml->startElement('item');

        // Zorunlu alanlar
        $xml->writeElement('g:id', (string) $product->id);
        $xml->writeElement('g:title', $this->cleanText($product->name, 150));
        $xml->writeElement('g:description', $this->cleanText($product->description ?? $product->short_description ?? '', 5000));
        $xml->writeElement('g:link', $this->productUrl($product));
        $xml->writeElement('g:image_link', $this->absoluteImageUrl($product->image ?? $product->images->first()?->image));

        // Stok
        $xml->writeElement('g:availability', $product->inStock() ? 'in_stock' : 'out_of_stock');

        // Fiyat — discounted_price = orijinal (üstü çizili), price = satış fiyatı
        if ($product->hasDiscount()) {
            $xml->writeElement('g:price', $this->formatPrice((float) $product->discounted_price));
            $xml->writeElement('g:sale_price', $this->formatPrice((float) $product->price));
        } else {
            $xml->writeElement('g:price', $this->formatPrice((float) $product->price));
        }

        // Durum, marka
        $xml->writeElement('g:condition', 'new');
        $xml->writeElement('g:brand', $this->siteName());
        $xml->writeElement('g:identifier_exists', 'no'); // GTIN/MPN/UPC yok

        // Kategori
        $xml->writeElement('g:google_product_category', $this->googleCategory($product));

        if ($product->category !== null) {
            $xml->writeElement('g:product_type', $this->cleanText($product->category->name, 750));
        }

        // Ek görseller (max 10)
        $additional = $product->images
            ->where('image', '!=', $product->image)
            ->take(10);

        foreach ($additional as $img) {
            $url = $this->absoluteImageUrl($img->image);
            if ($url !== '') {
                $xml->writeElement('g:additional_image_link', $url);
            }
        }

        // Kargo
        $xml->startElement('g:shipping');
        $xml->writeElement('g:country', 'TR');
        $xml->writeElement('g:service', 'Standart Kargo');
        $xml->writeElement('g:price', $this->formatPrice($this->shippingFee()));
        $xml->endElement();

        $xml->endElement(); // item
    }

    /**
     * Bir ürün Google'a uygun mu kontrol et — eksiklik listesi döner.
     *
     * @return list<string>
     */
    public function validateProduct(Product $product): array
    {
        $errors = [];

        if (trim((string) $product->name) === '') {
            $errors[] = 'Başlık eksik';
        } elseif (mb_strlen((string) $product->name) < 3) {
            $errors[] = 'Başlık çok kısa (min 3 karakter)';
        }

        $desc = trim((string) ($product->description ?? $product->short_description ?? ''));
        if ($desc === '') {
            $errors[] = 'Açıklama eksik';
        } elseif (mb_strlen($desc) < 30) {
            $errors[] = 'Açıklama çok kısa (min 30 karakter)';
        }

        if ($product->price === null || (float) $product->price <= 0) {
            $errors[] = 'Fiyat eksik veya 0';
        }

        $image = $product->image ?? $product->images->first()?->image;
        if (! is_string($image) || $image === '') {
            $errors[] = 'Görsel eksik';
        }

        if (trim((string) $product->slug) === '') {
            $errors[] = 'URL slug eksik';
        }

        return $errors;
    }

    /**
     * Cache'lenen meta bilgi (admin sayfası için).
     *
     * @return array{generated_at: \Carbon\Carbon, total: int, valid: int, invalid: int, issues: array<int, array{name: string, errors: list<string>}>}|null
     */
    public function getMeta(): ?array
    {
        /** @var array{generated_at: \Carbon\Carbon, total: int, valid: int, invalid: int, issues: array<int, array{name: string, errors: list<string>}>}|null $meta */
        $meta = Cache::get(self::CACHE_META);

        return is_array($meta) ? $meta : null;
    }

    private function productUrl(Product $product): string
    {
        return $this->siteUrl() . '/urun/' . $product->slug;
    }

    private function absoluteImageUrl(?string $path): string
    {
        if ($path === null || $path === '') {
            return '';
        }

        $url = upload_url($path, 'lg');

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return $this->siteUrl() . '/' . ltrim($url, '/');
    }

    private function formatPrice(float $amount): string
    {
        return number_format($amount, 2, '.', '') . ' TRY';
    }

    private function shippingFee(): float
    {
        $configured = Setting::getValue('shipping_default_fee');
        if ($configured !== null && is_numeric($configured)) {
            return (float) $configured;
        }

        return 50.00;
    }

    private function cleanText(string $text, int $max): string
    {
        // HTML temizle, çoklu boşlukları sıkıştır
        $clean = strip_tags($text);
        $clean = (string) preg_replace('/\s+/u', ' ', $clean);
        $clean = trim($clean);

        if (mb_strlen($clean) > $max) {
            $clean = mb_substr($clean, 0, $max - 3) . '...';
        }

        return $clean;
    }

    /**
     * Google Product Taxonomy mapping. Her ürün kategorisi için Google'ın
     * tanıdığı tam yolu döner. Default: süt ürünleri.
     */
    private function googleCategory(Product $product): string
    {
        $categoryName = mb_strtolower((string) ($product->category?->name ?? ''));

        // Türkçe taxonomy: https://www.google.com/basepages/producttype/taxonomy.tr-TR.txt
        $map = [
            'süt'       => 'Yiyecek, İçecek ve Tütün > Yiyecek > Süt Ürünleri ve Yumurta > Süt',
            'peynir'    => 'Yiyecek, İçecek ve Tütün > Yiyecek > Süt Ürünleri ve Yumurta > Peynir',
            'tereyağ'   => 'Yiyecek, İçecek ve Tütün > Yiyecek > Süt Ürünleri ve Yumurta > Tereyağı ve Margarinler',
            'yoğurt'    => 'Yiyecek, İçecek ve Tütün > Yiyecek > Süt Ürünleri ve Yumurta > Yoğurt',
            'yumurta'   => 'Yiyecek, İçecek ve Tütün > Yiyecek > Süt Ürünleri ve Yumurta > Yumurta',
            'bal'       => 'Yiyecek, İçecek ve Tütün > Yiyecek > Sosları, Bağırlar ve Çeşniler > Tatlı Bağırlar > Bal',
            'reçel'     => 'Yiyecek, İçecek ve Tütün > Yiyecek > Sosları, Bağırlar ve Çeşniler > Reçeller ve Marmelatlar',
            'kahvaltı'  => 'Yiyecek, İçecek ve Tütün > Yiyecek',
        ];

        foreach ($map as $key => $taxonomy) {
            if (str_contains($categoryName, $key)) {
                return $taxonomy;
            }
        }

        // Default — gıda
        return 'Yiyecek, İçecek ve Tütün > Yiyecek > Süt Ürünleri ve Yumurta';
    }
}
