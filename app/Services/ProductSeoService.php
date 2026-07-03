<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\Setting;

/**
 * Meta title/description generator for product pages.
 *
 * Priority:
 *   1. Admin-entered values (`product.meta_title`, `product.meta_description`)
 *   2. Pattern-based auto-generation per SEO spec (Modül 3)
 *
 * Patterns:
 *   - Title:       "Doğal {name} — Çorum Köy Ürünleri | {site_name}"   (max 60)
 *   - Description: "Çorum Büyük Palabıyık Köyü'nden {name}. {short_description}.
 *                   Doğal, katkısız. Hızlı kargo ile kapınıza."          (max 160)
 *
 * Both outputs are hard-clamped to their max length using `mb_substr` so
 * Turkish characters (ş, ı, ğ etc.) are counted correctly.
 */
final class ProductSeoService
{
    public const int TITLE_MAX_LENGTH       = 60;
    public const int DESCRIPTION_MAX_LENGTH = 160;

    /**
     * Resolve the SEO `<title>` for a product page.
     */
    public function metaTitle(Product $product): string
    {
        $custom = trim((string) ($product->meta_title ?? ''));
        if ($custom !== '') {
            return $this->clamp($custom, self::TITLE_MAX_LENGTH);
        }

        $siteName  = (string) Setting::getValue('site_name', config('app.name'));
        $namePart  = $this->withDogalPrefix(trim((string) $product->name));

        $title = "{$namePart} — Çorum Köy Ürünleri | {$siteName}";

        // If the natural pattern is too long, drop the site suffix first;
        // the product name itself is the most important keyword and must
        // not be cut.
        if (mb_strlen($title, 'UTF-8') > self::TITLE_MAX_LENGTH) {
            $title = "{$namePart} — Çorum Köy Ürünleri";
        }

        return $this->clamp($title, self::TITLE_MAX_LENGTH);
    }

    /**
     * Resolve the SEO `<meta name="description">` for a product page.
     */
    public function metaDescription(Product $product): string
    {
        $custom = trim((string) ($product->meta_description ?? ''));
        if ($custom !== '') {
            return $this->clamp($custom, self::DESCRIPTION_MAX_LENGTH);
        }

        $name  = trim((string) $product->name);
        $short = $this->endWithPeriod(trim((string) ($product->short_description ?? '')));

        $description = $short !== ''
            ? "Çorum Büyük Palabıyık Köyü'nden {$name}. {$short} Doğal, katkısız. Hızlı kargo ile kapınıza."
            : "Çorum Büyük Palabıyık Köyü'nden {$name}. Doğal, katkısız köy üretimi. Hızlı kargo ile kapınıza.";

        return $this->clamp($description, self::DESCRIPTION_MAX_LENGTH);
    }

    /**
     * Prefix the product name with "Doğal" — but skip if the name already
     * starts with that adjective (avoids "Doğal Doğal Köy Tereyağı" output
     * on default products like "Doğal Köy Tereyağı").
     */
    private function withDogalPrefix(string $name): string
    {
        if ($name === '') {
            return 'Doğal';
        }

        // Case-insensitive prefix check that respects Turkish locale (mb_*).
        $first = mb_convert_case(mb_substr($name, 0, 5, 'UTF-8'), MB_CASE_LOWER, 'UTF-8');
        if (str_starts_with($first, 'doğal')) {
            return $name;
        }

        return "Doğal {$name}";
    }

    /**
     * Ensure a sentence-like string ends with a sentence terminator so the
     * generated meta description reads naturally when concatenated:
     *   "{name}. {short} Doğal, katkısız."
     * If `$short` does not end with `.`, `!`, `?` we append `.`.
     */
    private function endWithPeriod(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $lastChar = mb_substr($value, -1, 1, 'UTF-8');
        if (in_array($lastChar, ['.', '!', '?', '…'], true)) {
            return $value;
        }

        return $value . '.';
    }

    /**
     * Hard-clamp a string to a max length using UTF-8 multibyte semantics.
     * If the value is already short enough it is returned as-is.
     */
    private function clamp(string $value, int $max): string
    {
        $value = trim($value);
        if (mb_strlen($value, 'UTF-8') <= $max) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $max, 'UTF-8'));
    }
}
