<?php

declare(strict_types=1);

namespace App\Services\Seo\Checks;

use App\Services\Seo\SeoCheck;
use App\Support\Seo\SeoIssue;
use App\Support\Seo\SeoSubject;

/**
 * Gövdenin uzunluğu.
 *
 * Kısa içerik kötü içerik değil — bir "teşekkürler" sayfası kısa olmalı. Ama
 * yazı olması beklenen bir sayfanın iki paragrafta bitmesi çoğu zaman
 * tamamlanmamış olduğunun işareti. Bu yüzden seviye bilgi: söylenen şey "burada
 * bir eksik var" değil, "buna bir daha bak".
 */
final class ContentLengthCheck implements SeoCheck
{
    /** @return list<SeoIssue> */
    public function run(SeoSubject $subject): array
    {
        $text = $subject->plainBody();

        if ($text === '') {
            return [SeoIssue::warning(
                'content.empty',
                __('seo.checks.content.empty'),
                'body',
                __('seo.checks.content.empty_hint'),
            )];
        }

        $words = count(preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: []);
        $threshold = (int) config('seo.thin_content_words', 150);

        if ($words >= $threshold) {
            return [];
        }

        return [SeoIssue::info(
            'content.thin',
            __('seo.checks.content.thin', ['words' => $words, 'threshold' => $threshold]),
            'body',
            __('seo.checks.content.thin_hint'),
        )];
    }
}
