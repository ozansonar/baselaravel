<?php

declare(strict_types=1);

namespace App\Services\Seo\Checks;

use App\Services\Seo\SeoCheck;
use App\Support\Seo\SeoIssue;
use App\Support\Seo\SeoSubject;
use Illuminate\Support\Str;

/**
 * Arama sonucunda başlığın altında görünen iki satır.
 *
 * Boş bırakıldığında motor gövdeden rastgele bir parça seçiyor — genelde ilk
 * cümle, ve o cümle çoğu zaman sayfanın ne olduğunu söylemiyor.
 */
final class MetaDescriptionCheck implements SeoCheck
{
    /** @return list<SeoIssue> */
    public function run(SeoSubject $subject): array
    {
        $description = $subject->metaDescription;

        if ($description === null || $description === '') {
            return [SeoIssue::warning(
                'meta.desc.missing',
                __('seo.checks.meta_description.missing'),
                'meta_description',
                __('seo.checks.meta_description.missing_hint'),
            )];
        }

        $length = Str::length($description);
        $min = (int) config('seo.description.min', 70);
        $max = (int) config('seo.description.max', 160);

        if ($length > $max) {
            return [SeoIssue::warning(
                'meta.desc.length',
                __('seo.checks.meta_description.too_long', ['length' => $length, 'max' => $max]),
                'meta_description',
                __('seo.checks.meta_description.too_long_hint', ['max' => $max]),
            )];
        }

        if ($length < $min) {
            return [SeoIssue::warning(
                'meta.desc.length',
                __('seo.checks.meta_description.too_short', ['length' => $length, 'min' => $min]),
                'meta_description',
                __('seo.checks.meta_description.too_short_hint', ['min' => $min]),
            )];
        }

        return [];
    }
}
