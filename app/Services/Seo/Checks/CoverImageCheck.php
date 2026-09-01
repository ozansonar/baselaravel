<?php

declare(strict_types=1);

namespace App\Services\Seo\Checks;

use App\Services\Seo\SeoCheck;
use App\Support\Seo\SeoIssue;
use App\Support\Seo\SeoSubject;

/**
 * Kapak görseli.
 *
 * Sayfa sosyal ağda paylaşıldığında önizleme görseli buradan geliyor
 * (`og:image`). Yoksa bağlantı, başlıktan ibaret gri bir kutu olarak çıkıyor —
 * ve o kutuya tıklanma oranı belirgin düşük.
 *
 * Kapağı olmayan içerik türleri var (bir "iletişim" sayfası gibi), o yüzden
 * seviye uyarı: eksikliği bir şeyi bozmuyor, fırsat kaçırıyor.
 */
final class CoverImageCheck implements SeoCheck
{
    /** @return list<SeoIssue> */
    public function run(SeoSubject $subject): array
    {
        if ($subject->coverImage !== null && $subject->coverImage !== '') {
            return [];
        }

        return [SeoIssue::warning(
            'image.cover.missing',
            __('seo.checks.cover_image.missing'),
            'image',
            __('seo.checks.cover_image.missing_hint'),
        )];
    }
}
