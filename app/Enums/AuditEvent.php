<?php

declare(strict_types=1);

namespace App\Enums;

enum AuditEvent: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Custom  = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Oluşturuldu',
            self::Updated => 'Güncellendi',
            self::Deleted => 'Silindi',
            self::Custom  => 'Özel',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Created => 'success',
            self::Updated => 'info',
            self::Deleted => 'danger',
            self::Custom  => 'secondary',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Created => 'bi-plus-circle-fill',
            self::Updated => 'bi-pencil-fill',
            self::Deleted => 'bi-trash-fill',
            self::Custom  => 'bi-asterisk',
        };
    }
}
