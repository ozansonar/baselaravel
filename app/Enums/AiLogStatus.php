<?php

declare(strict_types=1);

namespace App\Enums;

enum AiLogStatus: string
{
    case Started = 'started';
    case ApiCalled = 'api_called';
    case Success = 'success';
    case ApiFailed = 'api_failed';
    case ParseError = 'parse_error';
    case CategoryMissing = 'category_missing';
    case PostFailed = 'post_failed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Started          => 'Başladı',
            self::ApiCalled        => 'API Çağrıldı',
            self::Success          => 'Başarılı',
            self::ApiFailed        => 'API Hatası',
            self::ParseError       => 'JSON Parse Hatası',
            self::CategoryMissing  => 'Kategori Bulunamadı',
            self::PostFailed       => 'Yazı Oluşturulamadı',
            self::Failed           => 'Başarısız',
        };
    }

    public function cssClass(): string
    {
        return match ($this) {
            self::Success                        => 'ord-status-sent',
            self::Started, self::ApiCalled       => 'ord-status-pending',
            default                              => 'ord-status-failed',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Success          => 'bi-check-circle-fill',
            self::Started          => 'bi-hourglass-split',
            self::ApiCalled        => 'bi-arrow-repeat',
            self::ApiFailed        => 'bi-cloud-slash',
            self::ParseError       => 'bi-braces',
            self::CategoryMissing  => 'bi-folder-x',
            self::PostFailed       => 'bi-journal-x',
            self::Failed           => 'bi-x-circle-fill',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Success                        => 'text-neon-green',
            self::Started, self::ApiCalled       => 'text-neon-orange',
            default                              => 'text-neon-red',
        };
    }
}
