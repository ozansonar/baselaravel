<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Support\Seo\SeoIssue;
use App\Support\Seo\SeoSubject;

/**
 * Tek bir SEO kuralı.
 *
 * Her kural bir sınıf ve tek bir şeye bakıyor. Bölmenin sebebi düzen değil,
 * sınanabilirlik: "meta açıklama uzunluğu" kuralının tetiklendiği ve
 * tetiklenmediği durum ayrı ayrı yazılabiliyor, ve yeni bir kural eklemek var
 * olanlardan hiçbirine dokunmuyor.
 *
 * Bir kural hiçbir bulgu döndürmeyebilir (her şey yolunda), bir tane
 * döndürebilir ya da birden çok — gövdedeki üç alt metinsiz görsel üç ayrı
 * bulgu değil, tek bir bulguda toplanıyor; yazarın göreceği şey liste değil,
 * yapılacak iş.
 */
interface SeoCheck
{
    /**
     * @return list<SeoIssue>
     */
    public function run(SeoSubject $subject): array;
}
