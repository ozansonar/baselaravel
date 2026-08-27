<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Ad ve soyadın tek parça metinle arasındaki çeviri.
 *
 * Mailing tarafında ad ve soyad ayrı sütunlarda tutuluyor: "Sayın {last_name}"
 * ya da yalnızca adla seslenmek tek bir isim alanıyla yapılamıyor. Ama dışarıdan
 * gelen veri hep ayrı gelmiyor — eski kayıtlar, tek sütunlu Excel dosyaları ve
 * kullanıcı tablosundaki birleşik gösterimler tek parça metin veriyor. Bölme ve
 * birleştirme kuralı bu yüzden tek yerde duruyor.
 */
final class PersonName
{
    /**
     * Tek parça ismi ada ve soyada ayırır.
     *
     * Son kelime soyad sayılıyor: "Ali Can Yılmaz" → "Ali Can" + "Yılmaz".
     * Tek kelime varsa soyad boş kalır, ad kaybolmaz.
     *
     * @return array{first_name: ?string, last_name: ?string}
     */
    public static function split(?string $full): array
    {
        $full = trim(preg_replace('/\s+/u', ' ', (string) $full) ?? '');

        if ($full === '') {
            return ['first_name' => null, 'last_name' => null];
        }

        $lastSpace = mb_strrpos($full, ' ');

        if ($lastSpace === false) {
            return ['first_name' => $full, 'last_name' => null];
        }

        return [
            'first_name' => mb_substr($full, 0, $lastSpace),
            'last_name'  => mb_substr($full, $lastSpace + 1),
        ];
    }

    /**
     * Ad ve soyadı gösterim için birleştirir; ikisi de boşsa null döner.
     */
    public static function full(?string $firstName, ?string $lastName): ?string
    {
        $full = trim(trim((string) $firstName) . ' ' . trim((string) $lastName));

        return $full !== '' ? $full : null;
    }
}
