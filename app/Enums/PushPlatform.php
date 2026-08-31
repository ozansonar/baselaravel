<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Bildirim jetonunun geldiği platform.
 *
 * Taşıyıcıya göre değil cihaza göre adlandırılmış: aynı uygulama bugün FCM,
 * yarın başka bir servis kullanabilir ama telefon hep iOS ya da Android
 * kalıyor. Web push da listede — tarayıcıdan kurulan PWA da bildirim alabilir.
 */
enum PushPlatform: string
{
    case Ios     = 'ios';
    case Android = 'android';
    case Web     = 'web';

    public function label(): string
    {
        return match ($this) {
            self::Ios     => 'iOS',
            self::Android => 'Android',
            self::Web     => 'Web',
        };
    }
}
