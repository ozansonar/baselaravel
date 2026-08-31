<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Concerns;

/**
 * Görsel adresleri — göreli değil, mutlak.
 *
 * `upload_url()` ön yüz için "/uploads/..." döndürüyor; sayfanın kendisi zaten
 * o alan adında olduğu için orada doğrusu bu. Mobil uygulamada sayfa yok:
 * göreli bir adres hiçbir şeye çözülmez, o yüzden burada APP_URL ile
 * tamamlanıyor.
 *
 * Boş yol için yer tutucu değil `null` dönüyor: yer tutucu görselin nasıl
 * görüneceği istemcinin tasarım kararı, API'nin değil.
 */
trait ResolvesMediaUrls
{
    /**
     * Tek bir boyut.
     */
    protected function mediaUrl(?string $path, ?string $size = null): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return url(upload_url($path, $size));
    }

    /**
     * Duyarlı görselin bütün boyutları.
     *
     * İstemci ekranına göre seçsin diye hepsi birden veriliyor: liste kartında
     * `thumb`, detayda `lg`. Ayrı ayrı istekle sorulsaydı her kart için bir
     * gidiş dönüş daha olurdu.
     *
     * @return array<string, string>|null
     */
    protected function imageUrls(?string $path): ?array
    {
        if ($path === null || $path === '') {
            return null;
        }

        return [
            'original' => (string) $this->mediaUrl($path),
            'thumb'    => (string) $this->mediaUrl($path, 'thumb'),
            'sm'       => (string) $this->mediaUrl($path, 'sm'),
            'md'       => (string) $this->mediaUrl($path, 'md'),
            'lg'       => (string) $this->mediaUrl($path, 'lg'),
        ];
    }
}
