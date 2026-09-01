<?php

declare(strict_types=1);

namespace App\Services\Seo\Checks;

use App\Services\Seo\SeoCheck;
use App\Support\Seo\BodyDocument;
use App\Support\Seo\SeoIssue;
use App\Support\Seo\SeoSubject;

/**
 * Gövdedeki başlık yapısı.
 *
 * İki şeye bakıyor:
 *
 *  - **İkinci bir H1.** Sayfanın H1'i başlığın kendisi; gövdeye bir tane daha
 *    konduğunda sayfanın konusu belirsizleşiyor. Yazar çoğu zaman bunu bilerek
 *    yapmıyor — editörde "Başlık 1" seçeneği listenin en üstünde duruyor.
 *  - **Atlanan seviye.** H2'den sonra H4 gelmesi, ekran okuyucunun ve arama
 *    motorunun yapıyı yanlış çıkarmasına yol açıyor: araya giren H3 yokken
 *    H4, H2'nin altı değil kardeşi sanılıyor.
 */
final class HeadingStructureCheck implements SeoCheck
{
    /** @return list<SeoIssue> */
    public function run(SeoSubject $subject): array
    {
        $headings = BodyDocument::for($subject->body)->headings();

        if ($headings === []) {
            return [];
        }

        $issues = [];

        $h1Count = count(array_filter(
            $headings,
            static fn (array $heading): bool => $heading['level'] === 1,
        ));

        if ($h1Count > 0) {
            $issues[] = SeoIssue::error(
                'heading.h1.multiple',
                __('seo.checks.heading.extra_h1', ['count' => $h1Count]),
                'body',
                __('seo.checks.heading.extra_h1_hint'),
            );
        }

        // Sıçrama aranırken sayfanın kendi H1'i başlangıç kabul ediliyor:
        // gövdenin H2 ile başlaması doğru olan.
        $previous = 1;

        foreach ($headings as $heading) {
            $level = $heading['level'];

            if ($level > $previous + 1) {
                $issues[] = SeoIssue::warning(
                    'heading.skipped',
                    __('seo.checks.heading.skipped', [
                        'from' => 'H' . $previous,
                        'to'   => 'H' . $level,
                    ]),
                    'body',
                    __('seo.checks.heading.skipped_hint'),
                );

                break;
            }

            $previous = $level;
        }

        return $issues;
    }
}
