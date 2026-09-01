<?php

declare(strict_types=1);

namespace App\Support\Seo;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Gövde HTML'ini bir kez ayrıştırıp kuralların sorgulayabileceği hâle getirir.
 *
 * Dört kural aynı gövdeye bakıyor (başlık sırası, görsel alt metni, bağlantı
 * metni, kırık iç bağlantı). Her biri kendi ayrıştırmasını yapsaydı aynı metin
 * dört kez okunurdu; toplu denetim ekranında bu, yüz içerik için dört yüz
 * ayrıştırma demek.
 *
 * Aynı gövde için aynı örnek dönüyor: hash'e göre istek içi bir bellek
 * tutuluyor. Bellek sınırsız büyümesin diye tavan var — toplu denetimde yüzlerce
 * farklı gövde geçiyor ve hepsini tutmanın bir faydası yok, arka arkaya gelen
 * kurallar zaten aynı gövdeye bakıyor.
 */
final class BodyDocument
{
    /** @var array<string, self> */
    private static array $memo = [];

    private const MEMO_LIMIT = 8;

    private ?DOMXPath $xpath = null;

    private function __construct(
        private readonly string $html,
    ) {}

    public static function for(string $html): self
    {
        $key = md5($html);

        if (! isset(self::$memo[$key])) {
            if (count(self::$memo) >= self::MEMO_LIMIT) {
                self::$memo = [];
            }

            self::$memo[$key] = new self($html);
        }

        return self::$memo[$key];
    }

    /** Testlerin ve uzun süreçlerin belleği boşaltması için. */
    public static function flush(): void
    {
        self::$memo = [];
    }

    /**
     * Başlık etiketleri, belgedeki sırasıyla.
     *
     * @return list<array{level: int, text: string}>
     */
    public function headings(): array
    {
        $headings = [];

        foreach ($this->query('//h1|//h2|//h3|//h4|//h5|//h6') as $node) {
            $headings[] = [
                'level' => (int) substr($node->nodeName, 1),
                'text'  => trim($node->textContent),
            ];
        }

        return $headings;
    }

    /**
     * Görseller.
     *
     * @return list<array{src: string, alt: string|null}>
     */
    public function images(): array
    {
        $images = [];

        foreach ($this->query('//img') as $node) {
            $images[] = [
                'src' => trim($node->getAttribute('src')),
                // Niteliğin hiç olmaması ile boş olması farklı şeyler: ikincisi
                // "bu görsel süs" demenin geçerli yolu.
                'alt' => $node->hasAttribute('alt') ? trim($node->getAttribute('alt')) : null,
            ];
        }

        return $images;
    }

    /**
     * Bağlantılar.
     *
     * @return list<array{href: string, text: string}>
     */
    public function links(): array
    {
        $links = [];

        foreach ($this->query('//a[@href]') as $node) {
            $links[] = [
                'href' => trim($node->getAttribute('href')),
                // Metin yerine görsel taşıyan bağlantıda alt metni bağlantı
                // metni sayılıyor — ekran okuyucu da öyle okuyor.
                'text' => trim($node->textContent) !== ''
                    ? trim($node->textContent)
                    : $this->imageAltInside($node),
            ];
        }

        return $links;
    }

    private function imageAltInside(DOMElement $node): string
    {
        foreach ($node->getElementsByTagName('img') as $image) {
            $alt = trim($image->getAttribute('alt'));

            if ($alt !== '') {
                return $alt;
            }
        }

        return '';
    }

    /**
     * @return list<DOMElement>
     */
    private function query(string $expression): array
    {
        $xpath = $this->xpath();

        if ($xpath === null) {
            return [];
        }

        $nodes = $xpath->query($expression);

        if ($nodes === false) {
            return [];
        }

        $found = [];

        foreach ($nodes as $node) {
            if ($node instanceof DOMElement) {
                $found[] = $node;
            }
        }

        return $found;
    }

    private function xpath(): ?DOMXPath
    {
        if ($this->xpath !== null) {
            return $this->xpath;
        }

        if (trim($this->html) === '') {
            return null;
        }

        $document = new DOMDocument();

        // Editörden gelen HTML parça hâlinde ve çoğu zaman geçersiz; ayrıştırıcı
        // uyarılarını yutmak zorundayız, yoksa her kayıtta log dolar. Hatalı
        // işaretleme denetimin konusu değil — tarayıcı da onu toparlıyor.
        $previous = libxml_use_internal_errors(true);

        // UTF-8 bildirimi olmadan DOMDocument içeriği Latin-1 sanıyor ve Türkçe
        // harfler bozuluyor; bozulan metin de uzunluk ve alt metin denetimini
        // yanıltıyor.
        $document->loadHTML(
            '<?xml encoding="UTF-8"?><div>' . $this->html . '</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $this->xpath = new DOMXPath($document);
    }
}
