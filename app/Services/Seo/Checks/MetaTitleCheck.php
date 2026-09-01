<?php

declare(strict_types=1);

namespace App\Services\Seo\Checks;

use App\Services\Seo\SeoCheck;
use App\Support\Seo\SeoIssue;
use App\Support\Seo\SeoSubject;
use Illuminate\Support\Str;

/**
 * Arama sonucunda görünecek başlık.
 *
 * Üç ayrı soru: alan dolu mu, uzunluğu tutuyor mu, ve sayfa başlığının kopyası
 * mı. Üçü aynı sınıfta çünkü üçü de aynı metne bakıyor ve yazarın kafasında da
 * tek bir iş: "başlığı düzelt".
 */
final class MetaTitleCheck implements SeoCheck
{
    /** @return list<SeoIssue> */
    public function run(SeoSubject $subject): array
    {
        $issues = [];

        $meta = $subject->metaTitle;
        $effective = $subject->effectiveTitle();

        if ($meta === null || $meta === '') {
            // Sayfa başlığı da boşsa asıl eksik o; iki uyarı vermek gürültü olurdu.
            if ($subject->title !== '') {
                $issues[] = SeoIssue::warning(
                    'meta.title.missing',
                    __('seo.checks.meta_title.missing'),
                    'meta_title',
                    __('seo.checks.meta_title.missing_hint'),
                );
            }
        } elseif (Str::lower(trim($meta)) === Str::lower(trim($subject->title)) && $subject->title !== '') {
            $issues[] = SeoIssue::info(
                'meta.title.duplicate',
                __('seo.checks.meta_title.duplicate'),
                'meta_title',
                __('seo.checks.meta_title.duplicate_hint'),
            );
        }

        // Uzunluk gerçekte görünecek metne bakıyor: meta boşsa motor sayfa
        // başlığını kullanıyor ve kırpılan da o oluyor.
        //
        // Ölçülen metnin hangisi olduğu mesajda söyleniyor. Söylenmezse panel
        // "meta başlık boş" ile "meta başlık 4 karakter" uyarılarını yan yana
        // gösteriyor ve yazar hangisinin doğru olduğunu anlayamıyordu — ikisi
        // de doğruydu, ama ikincisi meta alanını değil sayfa başlığını
        // ölçüyordu.
        if ($effective !== '') {
            $length = Str::length($effective);
            $min = (int) config('seo.title.min', 30);
            $max = (int) config('seo.title.max', 60);
            $usesFallback = $meta === null || $meta === '';
            $suffix = $usesFallback ? '_fallback' : '';

            if ($length > $max) {
                $issues[] = SeoIssue::warning(
                    'meta.title.length',
                    __('seo.checks.meta_title.too_long' . $suffix, ['length' => $length, 'max' => $max]),
                    'meta_title',
                    __('seo.checks.meta_title.too_long_hint', ['max' => $max]),
                );
            } elseif ($length < $min) {
                $issues[] = SeoIssue::warning(
                    'meta.title.length',
                    __('seo.checks.meta_title.too_short' . $suffix, ['length' => $length, 'min' => $min]),
                    'meta_title',
                    __('seo.checks.meta_title.too_short_hint', ['min' => $min]),
                );
            }
        }

        return $issues;
    }
}
