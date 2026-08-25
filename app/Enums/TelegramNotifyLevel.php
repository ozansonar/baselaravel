<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How noisy Telegram alerting should be about failed jobs.
 */
enum TelegramNotifyLevel: string
{
    case PermanentOnly = 'permanent_only';
    case EveryFailure  = 'every_failure';

    public function label(): string
    {
        return match ($this) {
            self::PermanentOnly => 'Sadece kalıcı hata (3/3 deneme sonunda) — önerilen',
            self::EveryFailure  => 'Her başarısızlıkta (1., 2., 3. denemede ayrı mesaj)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::PermanentOnly => 'Sadece tüm denemeler tükendiğinde tek mesaj gelir (sessiz, idempotent).',
            self::EveryFailure  => 'Sorun başlar başlamaz haberin olur, ama tek iş için 3 mesaj gelebilir.',
        };
    }

    public static function default(): self
    {
        return self::PermanentOnly;
    }
}
