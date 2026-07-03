<?php

declare(strict_types=1);

namespace App\Enums;

enum Department: string
{
    case Engineering = 'engineering';
    case Design = 'design';
    case Marketing = 'marketing';
    case Sales = 'sales';
    case Hr = 'hr';
    case Finance = 'finance';
    case Support = 'support';
    case Management = 'management';

    public function label(): string
    {
        return match ($this) {
            self::Engineering => 'Yazılım Geliştirme',
            self::Design      => 'Tasarım',
            self::Marketing   => 'Pazarlama',
            self::Sales       => 'Satış',
            self::Hr          => 'İnsan Kaynakları',
            self::Finance     => 'Finans',
            self::Support     => 'Müşteri Destek',
            self::Management  => 'Yönetim',
        };
    }
}
