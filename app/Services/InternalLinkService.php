<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CityLandingPage;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Modül 8 — Internal linking güçlendirme.
 *
 * HTML içeriği alır, içinde geçen aktif ürün/şehir isimlerini ilgili
 * sayfalara link enjekte eder. Pattern:
 *   • Sadece <p>...</p> içine dokunur (başlıklara değil).
 *   • Aynı kelime içeriğin tekrarında 1 kez linklenir.
 *   • Zaten <a> içindeki metni tekrar sarmalamaz.
 *   • Maks link sayısı tipine göre (ürün 3, şehir 2) — spam görünmesin.
 *
 * Kullanım:
 *   - BlogGenerationService → AI ürettiği yazıya link enjekte eder
 *   - CityLandingPage frontend → admin/AI yazdığı içeriğe link enjekte eder
 *   - İleride Product description, Page content gibi alanlarda da kullanılabilir
 */
final class InternalLinkService
{
    public const int DEFAULT_MAX_PRODUCT_LINKS = 3;
    public const int DEFAULT_MAX_CITY_LINKS    = 2;

    /**
     * HTML içeriğindeki ürün isimlerini ürün sayfasına linkler.
     *
     * @param  Collection<int, Product>|null  $products  Performans için zaten yüklü liste; null ise DB'den çekilir.
     */
    public function injectProductLinks(string $html, ?Collection $products = null, int $maxLinks = self::DEFAULT_MAX_PRODUCT_LINKS): string
    {
        $products ??= Product::active()
            ->select(['id', 'name', 'slug'])
            ->orderByRaw('LENGTH(name) DESC')
            ->get();

        if ($products->isEmpty() || $maxLinks <= 0 || trim($html) === '') {
            return $html;
        }

        return $this->injectByList(
            $html,
            $products->map(fn (Product $p) => [
                'key'   => 'product-' . $p->slug,
                'name'  => $p->name,
                'url'   => route('products.show', $p->slug),
                'title' => $p->name,
            ])->all(),
            $maxLinks,
        );
    }

    /**
     * HTML içeriğindeki şehir isimlerini ilgili landing page'e linkler.
     *
     * @param  Collection<int, CityLandingPage>|null  $cities
     */
    public function injectCityLinks(string $html, ?Collection $cities = null, int $maxLinks = self::DEFAULT_MAX_CITY_LINKS): string
    {
        $cities ??= CityLandingPage::active()
            ->select(['id', 'city_name', 'city_slug'])
            ->orderByRaw('LENGTH(city_name) DESC')
            ->get();

        if ($cities->isEmpty() || $maxLinks <= 0 || trim($html) === '') {
            return $html;
        }

        return $this->injectByList(
            $html,
            $cities->map(fn (CityLandingPage $c) => [
                'key'   => 'city-' . $c->city_slug,
                'name'  => $c->city_name,
                'url'   => url('/' . $c->city_slug . '-koy-urunleri'),
                'title' => $c->city_name . ' Köy Ürünleri',
            ])->all(),
            $maxLinks,
        );
    }

    /**
     * Generic injector — ortak <p> ve <li> içi tekil eşleşme + max link mantığı.
     *
     * Hangi blok elementlerinin işleneceği `$blockTags` ile belirlenir;
     * <h1>-<h6> başlıklara KASTEN dokunulmaz (anchor text overload + UX).
     *
     * Çift sarmalamayı önlemek için ÖNCE mevcut <a>...</a> bloklarını
     * placeholder'a çıkarır (lookbehind ile sonsuz uzunluk PHP regex
     * sınırlaması yüzünden), inject eder, sonra geri koyar.
     *
     * @param  list<array{key: string, name: string, url: string, title: string}>  $items
     */
    private function injectByList(string $html, array $items, int $maxLinks): string
    {
        // 1) <a>...</a> bloklarını çıkar — placeholder ile değiştir.
        //    Aynı zamanda mevcut anchor'ın içinde geçen item adını
        //    linkedKeys'e ekle ki aynı paragrafta aynı ürün/şehir
        //    için ikinci kez link enjekte etmeyelim (admin elle eklemiş
        //    olabilir).
        $existingAnchors = [];
        $linkedKeys      = [];

        $html = preg_replace_callback('/<a\b[^>]*>(.*?)<\/a>/si', function (array $m) use ($items, &$existingAnchors, &$linkedKeys): string {
            $token             = '__INTLINK_PLACEHOLDER_' . count($existingAnchors) . '__';
            $existingAnchors[] = $m[0];

            $anchorText = strip_tags($m[1]);
            foreach ($items as $item) {
                if (in_array($item['key'], $linkedKeys, true)) {
                    continue;
                }
                if (mb_stripos($anchorText, $item['name'], 0, 'UTF-8') !== false) {
                    $linkedKeys[] = $item['key'];
                }
            }

            return $token;
        }, $html) ?? $html;

        $linkCount = 0;
        // <p> ve <li> her ikisini de işleme — başlıklar (<h1>-<h6>) kasten
        // dışarda. Tek regex'te alternation ile capture group sayısını
        // artırırsak callback indeksleri karışır; ayrı pattern + foreach
        // daha okunabilir.
        $patterns = [
            '/(<p[^>]*>)(.*?)(<\/p>)/su',
            '/(<li[^>]*>)(.*?)(<\/li>)/su',
        ];

        foreach ($items as $item) {
            if ($linkCount >= $maxLinks) {
                break;
            }
            if (in_array($item['key'], $linkedKeys, true)) {
                continue;
            }

            $name  = preg_quote($item['name'], '/');
            $url   = $item['url'];
            $title = htmlspecialchars($item['title'], ENT_QUOTES);

            foreach ($patterns as $pattern) {
                if ($linkCount >= $maxLinks || in_array($item['key'], $linkedKeys, true)) {
                    break;
                }

                $html = preg_replace_callback($pattern, function (array $matches) use ($name, $url, $title, $item, &$linkCount, &$linkedKeys, $maxLinks): string {
                    if ($linkCount >= $maxLinks || in_array($item['key'], $linkedKeys, true)) {
                        return $matches[0];
                    }

                    $inner = $matches[2];

                    // (?<![<\/\w]) — başında HTML tag/kelime karakteri yok
                    // (?![^<]*>)   — sonrasında bir tag açılışı yok (zaten <a>
                    //                içindeki metne dokunmaz, attribute içinde değil)
                    $replaced = preg_replace(
                        '/(?<![<\/\w])(' . $name . ')(?![^<]*>)/iu',
                        '<a href="' . $url . '" title="' . $title . '">$1</a>',
                        $inner,
                        1,
                        $count,
                    );

                    if ($count > 0) {
                        $linkCount++;
                        $linkedKeys[] = $item['key'];

                        return $matches[1] . $replaced . $matches[3];
                    }

                    return $matches[0];
                }, $html) ?? $html;
            }
        }

        // 2) Placeholder'ları orijinal <a>...</a> blokları ile geri yerleştir
        foreach ($existingAnchors as $i => $anchor) {
            $html = str_replace('__INTLINK_PLACEHOLDER_' . $i . '__', $anchor, $html);
        }

        return $html;
    }
}
