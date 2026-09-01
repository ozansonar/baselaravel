<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Bildirimin kime gideceği.
 *
 * Hedef, kullanıcı kümesi olarak tanımlanıyor — jeton kümesi olarak değil.
 * Sebebi, bir kişinin birden çok cihazı olabilmesi: "yöneticilere gönder"
 * dendiğinde her yöneticinin bütün cihazları kastediliyor.
 */
enum PushAudience: string
{
    /** Bildirim izni vermiş herkes. */
    case All = 'all';

    /** Belirli bir roldeki kullanıcılar. */
    case Role = 'role';

    /** Tek bir kullanıcı. */
    case User = 'user';

    public function label(): string
    {
        return match ($this) {
            self::All  => 'Tüm kullanıcılar',
            self::Role => 'Belirli bir rol',
            self::User => 'Tek kullanıcı',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::All  => 'Uygulamada bildirime izin vermiş bütün kullanıcılar',
            self::Role => 'Seçilen roldeki kullanıcılar',
            self::User => 'Yalnızca seçilen kullanıcı',
        };
    }

    /** Liste ve formdaki simge. */
    public function icon(): string
    {
        return match ($this) {
            self::All  => 'bi-broadcast',
            self::Role => 'bi-shield-check',
            self::User => 'bi-person',
        };
    }

    /** Simgenin vurgu rengi — listedeki rozetle aynı ölçek. */
    public function color(): string
    {
        return match ($this) {
            self::All  => 'teal',
            self::Role => 'blue',
            self::User => 'green',
        };
    }

    /** Hedefin ayrıca bir seçim gerektirip gerektirmediği. */
    public function needsTarget(): bool
    {
        return $this !== self::All;
    }
}
