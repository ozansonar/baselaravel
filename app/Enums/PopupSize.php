<?php

declare(strict_types=1);

namespace App\Enums;

enum PopupSize: string
{
    case Sm = 'sm';
    case Md = 'md';
    case Lg = 'lg';
    case Xl = 'xl';

    public function label(): string
    {
        return match ($this) {
            self::Sm => 'Küçük',
            self::Md => 'Orta',
            self::Lg => 'Büyük',
            self::Xl => 'Çok Büyük',
        };
    }

    public function modalClass(): string
    {
        return match ($this) {
            self::Sm => 'modal-sm',
            self::Md => '',
            self::Lg => 'modal-lg',
            self::Xl => 'modal-xl',
        };
    }
}
