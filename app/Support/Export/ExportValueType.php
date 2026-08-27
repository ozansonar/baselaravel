<?php

declare(strict_types=1);

namespace App\Support\Export;

/**
 * Bir sütunun taşıdığı değerin türü.
 *
 * Tür yalnızca biçim meselesi değil: Excel'de tarih ve sayı hücreleri metin
 * olarak yazılırsa süzme, sıralama ve toplama çalışmaz — dosya "açılıyor" ama
 * işe yaramaz hâle gelir. PDF tarafında ise sayıların sağa yaslanmasını verir.
 */
enum ExportValueType: string
{
    case Text = 'text';
    case Number = 'number';
    case Date = 'date';
    case DateTime = 'datetime';

    /** Excel hücre biçim maskesi. */
    public function excelFormat(): ?string
    {
        return match ($this) {
            self::Date     => 'dd.mm.yyyy',
            self::DateTime => 'dd.mm.yyyy hh:mm',
            default        => null,
        };
    }

    /** PHP tarih biçimi — PDF ve okunabilir metin çıktısı için. */
    public function phpDateFormat(): string
    {
        return match ($this) {
            self::Date => 'd.m.Y',
            default    => 'd.m.Y H:i',
        };
    }

    /** PDF tablosunda hizalama sınıfı. */
    public function alignmentClass(): string
    {
        return match ($this) {
            self::Number => 'num',
            default      => 'txt',
        };
    }
}
