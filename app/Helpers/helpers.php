<?php

declare(strict_types=1);

use App\Services\UploadService;

if (!function_exists('versioned_asset')) {
    /**
     * Generate an asset URL with cache-busting version query string.
     *
     * @param  string $path Relative path inside public/ (e.g. "css/app.css")
     * @return string       URL with ?v=<filemtime> appended
     */
    function versioned_asset(string $path): string
    {
        $fullPath = public_path($path);
        $version = file_exists($fullPath) ? filemtime($fullPath) : '0';

        return asset($path) . '?v=' . $version;
    }
}

if (!function_exists('upload_url')) {
    /**
     * Get the public URL for an uploaded file.
     *
     * @param  string|null $path Relative path (e.g. "blog/ornek-gorsel-a1b2c3d4e5.webp")
     * @param  string|null $size Size variant: thumb (150px), sm (300px), md (600px), lg (1200px)
     * @return string            URL path (e.g. "/uploads/blog/ornek-gorsel-a1b2c3d4e5-sm.webp")
     */
    function upload_url(?string $path, ?string $size = null): string
    {
        return UploadService::url($path, $size);
    }
}

if (!function_exists('whatsapp_url')) {
    /**
     * Convert a phone number or wa.me URL to a proper WhatsApp link.
     *
     * @param  string|null $value Phone number (+905059424124) or wa.me URL
     * @return string             https://wa.me/905059424124
     */
    function whatsapp_url(?string $value, ?string $message = null): string
    {
        if (empty($value)) {
            return '#';
        }

        if (str_contains($value, 'wa.me')) {
            $url = str_starts_with($value, 'http') ? $value : 'https://' . $value;
        } else {
            $clean = preg_replace('/[^0-9]/', '', $value);
            $url = 'https://wa.me/' . $clean;
        }

        if ($message !== null && $message !== '') {
            $url .= '?text=' . rawurlencode($message);
        }

        return $url;
    }
}

if (!function_exists('format_phone')) {
    /**
     * Format a phone number for display: +90 505 942 41 24
     *
     * @param  string|null $value Raw phone number
     * @return string             Formatted phone number
     */
    function format_phone(?string $value): string
    {
        if (empty($value)) {
            return '';
        }

        $clean = preg_replace('/[^0-9]/', '', $value);

        if (strlen($clean) === 12 && str_starts_with($clean, '90')) {
            return '+90 ' . substr($clean, 2, 3) . ' ' . substr($clean, 5, 3) . ' ' . substr($clean, 8, 2) . ' ' . substr($clean, 10, 2);
        }

        if (strlen($clean) === 10 && str_starts_with($clean, '5')) {
            return '+90 ' . substr($clean, 0, 3) . ' ' . substr($clean, 3, 3) . ' ' . substr($clean, 6, 2) . ' ' . substr($clean, 8, 2);
        }

        return $value;
    }
}

if (!function_exists('upload_srcset')) {
    /**
     * Generate srcset attribute for responsive images.
     *
     * @param  string|null        $path  Relative path
     * @param  array<string>|null $sizes Which sizes (null = sm, md, lg)
     * @return string                    srcset value
     */
    function upload_srcset(?string $path, ?array $sizes = null): string
    {
        return UploadService::srcset($path, $sizes);
    }
}

if (!function_exists('site_initials')) {
    /**
     * Build a short monogram from the site name, used as a logo placeholder
     * until a real logo is uploaded.
     *
     * @param  string|null $name Site name (e.g. "Acme Yazılım")
     * @return string            Up to two uppercase letters (e.g. "AY")
     */
    function site_initials(?string $name): string
    {
        $words = preg_split('/\s+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($words === []) {
            return '—';
        }

        $letters = array_map(
            static fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)),
            array_slice($words, 0, 2),
        );

        return implode('', $letters);
    }
}

if (!function_exists('page_url')) {
    /**
     * A dynamic page's address in the language being read.
     *
     * Slugs are per language, so a hard-coded one (hakkimizda) would 404 for a
     * visitor reading English once the page is translated. The slug is looked
     * up in its own language group first.
     */
    function page_url(string $slug): string
    {
        return app(\App\Services\LocalizedUrlService::class)->page($slug);
    }
}

if (!function_exists('image_alt')) {
    /**
     * Bir görselin alt metni: ilk dolu aday, hiçbiri yoksa sitenin genel metni.
     *
     * Görünümde `alt="{{ image_alt($item->title) }}"` diye çağrılıyor; boş
     * kalma ihtimali olan her yerde bu zincir devreye giriyor.
     *
     * @see \App\Services\ImageAltResolver
     */
    function image_alt(?string ...$candidates): string
    {
        return app(\App\Services\ImageAltResolver::class)->resolve(...$candidates);
    }
}

if (!function_exists('local_url')) {
    /**
     * Panelden girilen bağlantıyı ziyaretçinin diline taşır.
     *
     * Yönetici "iletisim" yazıyor, ziyaretçi hangi dilde geziniyorsa bağlantı
     * oraya gidiyor. Dış adresler ve çapalar olduğu gibi kalıyor.
     *
     * @see \App\Services\LocalizedUrlService::fromInput()
     */
    function local_url(?string $input): string
    {
        return app(\App\Services\LocalizedUrlService::class)->fromInput($input);
    }
}
