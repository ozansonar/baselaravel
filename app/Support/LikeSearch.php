<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Serbest metin aramasının LIKE kalıbı.
 *
 * Kullanıcının yazdığı `%` ve `_` joker değil, harf sayılmalı: "%" yazan biri
 * süzgeç yaptığını sanarak tüm listeye bakmamalı. Bunun için kaçış karakteri
 * gerekiyor ve seçim veritabanına göre değişiyor:
 *
 *   - **MySQL** ters bölüyü hem dizge hem LIKE düzeyinde kaçış sayar. Bu
 *     yüzden `ESCAPE '\'` yazmak MySQL'de **sözdizimi hatası** verir — kapanış
 *     tırnağı kaçırılmış olur ve sorgu patlar.
 *   - **SQLite** ters bölüye hiçbir özel anlam vermez, yani `ESCAPE`
 *     bildirilmezse kaçırılmış bir `%` hiçbir şey bulmaz.
 *
 * Ünlem ikisinde de düz karakter, o yüzden kaçış karakteri o ve `ESCAPE` her
 * zaman açıkça bildiriliyor.
 *
 * Bu sınıf var çünkü aynı mantık altı ayrı serviste tekrarlanıyordu ve
 * ikisinde yanlış yazılmıştı: MySQL'de arama yapan her ekran 500 veriyordu,
 * suite SQLite üzerinde koştuğu için bu hiç görünmüyordu.
 * `LikeSearchIsPortableTest` yanlış biçimin geri gelmesini engelliyor.
 */
final class LikeSearch
{
    public const ESCAPE_CHAR = '!';

    /**
     * Kullanıcının yazdığını, jokerleri kaçırılmış bir LIKE kalıbına çevirir.
     */
    public static function term(string $value): string
    {
        $escaped = str_replace(
            [self::ESCAPE_CHAR, '%', '_'],
            [self::ESCAPE_CHAR . self::ESCAPE_CHAR, self::ESCAPE_CHAR . '%', self::ESCAPE_CHAR . '_'],
            $value,
        );

        return '%' . $escaped . '%';
    }

    /**
     * Ham LIKE koşulu — kaçış karakteri her iki veritabanında da aynı.
     *
     * Kolon adı çağıranın sabiti; hiçbir çağrı bu değeri istekten almıyor.
     */
    public static function clause(string $column): string
    {
        return $column . " LIKE ? ESCAPE '" . self::ESCAPE_CHAR . "'";
    }
}
