<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Bir duyurunun ziyaretçiye kaç kez görüneceği.
 *
 * Tek davranış vardı ve sertti: duyuru bir kez görülünce oturum boyunca bir
 * daha çıkmıyordu. Yanlışlıkla kapatan ya da sayfayı yenileyen ziyaretçi
 * duyuruyu bir daha göremiyordu; yöneticinin de bunu değiştirme yolu yoktu.
 */
enum PopupDisplayMode: string
{
    /** Her sayfa açılışında. */
    case Always = 'always';

    /** Tarayıcı sekmesi kapanana kadar bir kez — eski davranış, varsayılan. */
    case Session = 'session';

    /** Kalıcı olarak bir kez: görüldüğü an bitiyor. */
    case Once = 'once';

    /** Kapat düğmesine basılana kadar her açılışta. */
    case UntilClosed = 'until_closed';

    public function label(): string
    {
        return match ($this) {
            self::Always      => 'Her zaman göster',
            self::Session     => 'Oturumda bir kez',
            self::Once        => 'Bir kez göster',
            self::UntilClosed => 'Kapatana kadar göster',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Always      => 'Ziyaretçi hangi sayfayı açarsa açsın her seferinde görünür.',
            self::Session     => 'Sekme kapanana kadar bir kez görünür, sonraki ziyarette yeniden çıkar.',
            self::Once        => 'Bir kez görüldükten sonra o tarayıcıda bir daha çıkmaz.',
            self::UntilClosed => 'Ziyaretçi kapat düğmesine basana kadar her sayfada çıkar.',
        };
    }

    /**
     * Görüldüğü bilgisinin nerede tutulacağı.
     *
     * Oturumluk seçim sekme kapanınca unutuluyor, kalıcı olanlar tarayıcıda
     * duruyor; her zaman gösterilen duyuru hiçbir yere yazılmıyor.
     */
    public function storage(): ?string
    {
        return match ($this) {
            self::Always                 => null,
            self::Session                => 'session',
            self::Once, self::UntilClosed => 'local',
        };
    }

    /** İşaret ne zaman konuyor: görününce mi, kapatılınca mı? */
    public function remembersOnClose(): bool
    {
        return $this !== self::Once;
    }
}
