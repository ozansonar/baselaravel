<?php

declare(strict_types=1);

namespace App\Services\Seo\Checks;

use App\Services\Seo\SeoCheck;
use App\Support\Seo\BodyDocument;
use App\Support\Seo\SeoIssue;
use App\Support\Seo\SeoSubject;
use Illuminate\Support\Str;

/**
 * Bağlantı metninin nereye gittiğini söyleyip söylemediği.
 *
 * "Buraya tıklayın" bağlantısı iki yerde birden başarısız: ekran okuyucu
 * kullanıcısı sayfadaki bağlantıları liste hâlinde gezerken yalnız metni duyuyor
 * ("buraya tıklayın, buraya tıklayın, buraya tıklayın"), ve arama motoru
 * bağlantı metnini hedef sayfanın konusu hakkında bir ipucu sayıyor — o ipucu
 * boş çıkıyor.
 *
 * Metni hiç olmayan bağlantı da aynı gruba giriyor: içinde alt metinli bir
 * görsel varsa o metin sayılıyor, o da yoksa bağlantı sessiz.
 */
final class LinkTextCheck implements SeoCheck
{
    /** @return list<SeoIssue> */
    public function run(SeoSubject $subject): array
    {
        /** @var list<string> $generic */
        $generic = config('seo.generic_link_texts', []);
        $generic = array_map(
            static fn (string $text): string => Str::lower(trim($text)),
            $generic,
        );

        $offenders = 0;
        $silent = 0;

        foreach (BodyDocument::for($subject->body)->links() as $link) {
            $text = Str::lower(trim($link['text']));

            if ($text === '') {
                ++$silent;

                continue;
            }

            // Noktalama temizleniyor: "Devamı..." ile "devamı" aynı şey.
            $normalised = trim((string) preg_replace('/[\p{P}\p{S}\s]+/u', ' ', $text));

            if (in_array($normalised, $generic, true)) {
                ++$offenders;
            }
        }

        $issues = [];

        if ($offenders > 0) {
            $issues[] = SeoIssue::warning(
                'link.text.generic',
                __('seo.checks.link_text.generic', ['count' => $offenders]),
                'body',
                __('seo.checks.link_text.generic_hint'),
            );
        }

        if ($silent > 0) {
            $issues[] = SeoIssue::warning(
                'link.text.empty',
                __('seo.checks.link_text.empty', ['count' => $silent]),
                'body',
                __('seo.checks.link_text.empty_hint'),
            );
        }

        return $issues;
    }
}
