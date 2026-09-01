<?php

declare(strict_types=1);

namespace App\Services\Seo\Checks;

use App\Services\Seo\LinkTargetResolver;
use App\Services\Seo\SeoCheck;
use App\Support\Seo\BodyDocument;
use App\Support\Seo\SeoIssue;
use App\Support\Seo\SeoSubject;
use Illuminate\Support\Str;

/**
 * Site içi bağlantılar bir yere çıkıyor mu?
 *
 * Kırık iç bağlantı iki şeyi birden kaybettiriyor: ziyaretçi 404 görüyor ve
 * arama motoru o bağın değerini boşa harcıyor. Ve fark edilmesi zor — yazının
 * içindeki bir bağlantıya kimse tıklamadan aylar geçebiliyor.
 *
 * En sık sebebi bir sayfanın adresinin değişmesi: yazı yazıldığında sağlam olan
 * bağlantı, hedef sayfanın slug'ı düzeltildiğinde sessizce kırılıyor.
 */
final class InternalLinkCheck implements SeoCheck
{
    public function __construct(
        private readonly LinkTargetResolver $targets,
    ) {}

    /** @return list<SeoIssue> */
    public function run(SeoSubject $subject): array
    {
        $hrefs = array_map(
            static fn (array $link): string => $link['href'],
            BodyDocument::for($subject->body)->links(),
        );

        if ($hrefs === []) {
            return [];
        }

        $broken = $this->targets->broken($hrefs);

        if ($broken === []) {
            return [];
        }

        // İlk üçü örnek olarak gösteriliyor: yazarın aradığı şey listenin
        // tamamı değil, hangi bağlantıya bakacağı.
        $sample = implode(', ', array_map(
            static fn (string $href): string => Str::limit($href, 60),
            array_slice($broken, 0, 3),
        ));

        return [SeoIssue::error(
            'link.internal.broken',
            __('seo.checks.internal_link.broken', [
                'count'  => count($broken),
                'sample' => $sample,
            ]),
            'body',
            __('seo.checks.internal_link.broken_hint'),
        )];
    }
}
