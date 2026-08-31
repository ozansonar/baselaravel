<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Panelin üretebildiği raporlar.
 *
 * Tema altı rapor kartıyla geliyordu ve dördü e-ticaret konusuydu (satış,
 * ürün, stok, finans). Bu kit'te o veriler yok; kartların konusu kit'in
 * gerçekten ölçtüğü altı şeyle değiştirildi. Düzen, sıra, ikonlar ve CSS
 * sınıfları tasarımdaki gibi duruyor.
 *
 * Yeni bir rapor eklemek: buraya bir case, ReportService'e bir metot.
 */
enum ReportType: string
{
    case Traffic     = 'traffic';
    case Content     = 'content';
    case Users       = 'users';
    case Mail        = 'mail';
    case Campaigns   = 'campaigns';
    case Subscribers = 'subscribers';

    public function label(): string
    {
        return match ($this) {
            self::Traffic     => 'Trafik Raporu',
            self::Content     => 'İçerik Raporu',
            self::Users       => 'Kullanıcı Raporu',
            self::Mail        => 'E-posta Raporu',
            self::Campaigns   => 'Kampanya Raporu',
            self::Subscribers => 'Abone Raporu',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Traffic     => 'Görüntülenme, tekil ziyaretçi ve en çok okunan sayfalar; cihaz ve tarayıcı dağılımı.',
            self::Content     => 'Dönemde yayınlanan blog yazıları, sayfalar, galeri öğeleri ve sık sorulan sorular.',
            self::Users       => 'Yeni kayıtlar, aktif/pasif dağılımı, roller ve e-posta doğrulama oranı.',
            self::Mail        => 'Gönderilen, bekleyen ve başarısız e-postalar; türlere göre dağılım.',
            self::Campaigns   => 'Toplu gönderimlerin başarımı: alıcı sayısı, ulaşan ve düşen gönderiler.',
            self::Subscribers => 'Bülten aboneliğinin büyümesi, kaynak dağılımı ve abonelikten çıkışlar.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Traffic     => 'bi-graph-up-arrow',
            self::Content     => 'bi-file-earmark-text',
            self::Users       => 'bi-people',
            self::Mail        => 'bi-envelope',
            self::Campaigns   => 'bi-megaphone',
            self::Subscribers => 'bi-person-plus',
        };
    }

    /** Kartın rengi — tasarımdaki altı renk, aynı sırayla. */
    public function color(): string
    {
        return match ($this) {
            self::Traffic     => 'teal',
            self::Content     => 'blue',
            self::Users       => 'purple',
            self::Mail        => 'orange',
            self::Campaigns   => 'green',
            self::Subscribers => 'pink',
        };
    }
}
