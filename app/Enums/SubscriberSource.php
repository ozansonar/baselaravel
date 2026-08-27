<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Abonenin listeye nasıl girdiği.
 *
 * Segment değil köken bilgisi: "kim tedarikçi" sorusunun cevabı listelerde,
 * "bu adresi ben mi ekledim yoksa siteden mi geldi" sorusununki burada.
 */
enum SubscriberSource: string
{
    case Form     = 'form';
    case Panel    = 'panel';
    case Import   = 'import';
    case Campaign = 'campaign';

    public function label(): string
    {
        return match ($this) {
            self::Form     => 'Site formu',
            self::Panel    => 'Panelden eklendi',
            self::Import   => 'Excel ile yüklendi',
            self::Campaign => 'Kampanya çıkışı',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Form     => 'bi-globe2',
            self::Panel    => 'bi-person-plus',
            self::Import   => 'bi-file-earmark-spreadsheet',
            self::Campaign => 'bi-envelope-slash',
        };
    }

    /**
     * Kampanya çıkışı olumsuz bir olay: adres yalnızca "bir daha gönderme"
     * diye kaydedilmiş, listeye katılmış değil.
     */
    public function color(): string
    {
        return match ($this) {
            self::Form     => 'teal',
            self::Panel    => 'blue',
            self::Import   => 'green',
            self::Campaign => 'muted',
        };
    }
}
