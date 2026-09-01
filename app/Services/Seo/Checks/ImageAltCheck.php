<?php

declare(strict_types=1);

namespace App\Services\Seo\Checks;

use App\Services\Seo\SeoCheck;
use App\Support\Seo\BodyDocument;
use App\Support\Seo\SeoIssue;
use App\Support\Seo\SeoSubject;

/**
 * Gövdedeki görsellerin alt metni.
 *
 * Alt metin iki işi birden yapıyor: ekran okuyucu onu okuyor ve görsel araması
 * onu okuyor. İkisi de yoksa görsel, içeriğe hiçbir şey katmayan bir kutu.
 *
 * `alt=""` bir eksik değil, bir **karar**: "bu görsel süs, atla" demenin geçerli
 * yolu. Bu yüzden niteliğin hiç olmaması ile boş olması ayrı ele alınıyor —
 * ikincisi uyarı üretmiyor.
 */
final class ImageAltCheck implements SeoCheck
{
    /** @return list<SeoIssue> */
    public function run(SeoSubject $subject): array
    {
        $missing = array_filter(
            BodyDocument::for($subject->body)->images(),
            static fn (array $image): bool => $image['alt'] === null,
        );

        if ($missing === []) {
            return [];
        }

        // Üç görsel üç ayrı bulgu değil: yazarın göreceği şey liste değil,
        // yapılacak tek bir iş.
        return [SeoIssue::error(
            'image.alt.missing',
            __('seo.checks.image_alt.missing', ['count' => count($missing)]),
            'body',
            __('seo.checks.image_alt.missing_hint'),
        )];
    }
}
