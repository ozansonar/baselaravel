<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Mail gövdesindeki HTML'i posta kutusunda taşmayacak hâle getirir.
 *
 * Editör görseli özgün ölçüsüyle ekliyor: 2000 piksellik bir fotoğraf
 * <img width="2000" height="1333"> olarak kaydediliyor. Mail şablonunun
 * kapsayıcısı 600 piksel olduğu için görsel dışarı taşıyor; hem önizlemede hem
 * gelen mailde tasarım bozuluyordu.
 *
 * Yalnızca <style> bloğuna kural yazmak yetmez: birçok posta istemcisi
 * (özellikle Outlook) belge başındaki stilleri kırpıyor. Bu yüzden düzeltme
 * doğrudan etiketin üstüne, satır içi olarak yazılıyor — mail HTML'inde satır
 * içi stil kuraldır, sayfa şablonlarındaki yasak buraya işlemez.
 */
final class EmailHtml
{
    /**
     * Mail şablonundaki kapsayıcının genişliği (emails/layout.blade.php içindeki
     * .em-container ile aynı).
     */
    public const CONTAINER_WIDTH = 600;

    /**
     * Görselleri kapsayıcı genişliğine sığdırır.
     *
     * - width niteliği tavanı aşıyorsa tavana çekilir; küçük bir değere elle
     *   ayarlanmış görsel olduğu gibi bırakılır, kullanıcının tercihi korunur.
     * - width kısıldığında height atılır: eski yükseklik yeni genişlikle
     *   eşleşmez ve görsel ezilir.
     * - Her görsele satır içi max-width/height eklenir; dar ekranda 600 piksel
     *   bile taşabiliyor.
     */
    public static function constrainImages(string $html, int $maxWidth = self::CONTAINER_WIDTH): string
    {
        return (string) preg_replace_callback(
            '#<img\b[^>]*>#i',
            static fn (array $m): string => self::constrainTag($m[0], $maxWidth),
            $html,
        );
    }

    /**
     * Önizleme penceresine konacak belge.
     *
     * Gövde ham hâliyle gösterilirse tarayıcı sınırsız genişlikte çizer ve
     * görseller taşar; oysa mail 600 pikselik bir sütunda okunuyor. Burada aynı
     * sütun kuruluyor, böylece ekranda görünen ile posta kutusuna düşen
     * birbirini tutuyor.
     */
    public static function previewDocument(?string $body): string
    {
        $icerik = self::constrainImages((string) $body);

        return '<!doctype html><html lang="tr"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<style>'
            . 'html,body{margin:0;padding:0;background:#f4f5f7;}'
            . 'body{font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.7;color:#1f2937;}'
            . '.em-preview{max-width:' . self::CONTAINER_WIDTH . 'px;margin:0 auto;padding:20px;background:#ffffff;}'
            . 'img{max-width:100%;height:auto;}'
            . 'table{max-width:100%;border-collapse:collapse;}'
            . '</style></head><body><div class="em-preview">'
            . $icerik
            . '</div></body></html>';
    }

    private static function constrainTag(string $tag, int $maxWidth): string
    {
        $width = null;

        if (preg_match('#\bwidth\s*=\s*"(\d+)"#i', $tag, $m) === 1) {
            $width = (int) $m[1];
        }

        $tooWide = $width !== null && $width > $maxWidth;

        if ($tooWide) {
            $tag = (string) preg_replace('#\bwidth\s*=\s*"\d+"#i', 'width="' . $maxWidth . '"', $tag);
            // Yükseklik artık orantısız; bırakılırsa görsel eziliyor.
            $tag = (string) preg_replace('#\s+height\s*=\s*"\d+"#i', '', $tag);
        }

        return self::withStyle($tag);
    }

    /**
     * Satır içi stile taşma korumasını ekler; var olan stil korunur.
     */
    private static function withStyle(string $tag): string
    {
        $eklenecek = 'max-width:100%;height:auto;';

        if (preg_match('#\bstyle\s*=\s*"([^"]*)"#i', $tag, $m) === 1) {
            $mevcut = trim($m[1]);

            // Zaten sınırlanmışsa ikinci kez yazılmıyor.
            if (stripos($mevcut, 'max-width') !== false) {
                return $tag;
            }

            $birlesik = $eklenecek . ($mevcut === '' ? '' : ' ' . $mevcut);

            return (string) preg_replace(
                '#\bstyle\s*=\s*"[^"]*"#i',
                'style="' . $birlesik . '"',
                $tag,
                1,
            );
        }

        return (string) preg_replace('#^<img\b#i', '<img style="' . $eklenecek . '"', $tag, 1);
    }
}
