<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Bir push bildiriminin gönderim durumu.
 *
 * Taslak durumu bilerek yok: bildirim kısa bir metin, kampanya gibi üzerinde
 * günlerce çalışılan bir şey değil. Yazılıyor ve gönderiliyor; yarım bir
 * bildirimi saklamanın karşılığı, gönderilmemiş bir satırı listede taşımak
 * olurdu.
 */
enum PushNotificationStatus: string
{
    /** Sıraya alındı; cron bir sonraki turunda gönderecek. */
    case Queued = 'queued';

    /** Gönderim başladı, cihazların bir kısmına ulaşıldı. */
    case Sending = 'sending';

    /** Bütün cihazlar denendi. */
    case Sent = 'sent';

    /** Gönderim başlayamadı — taşıyıcı yapılandırılmamış ya da hedef boş. */
    case Failed = 'failed';

    /** Gönderim başlamadan durduruldu. */
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Queued    => 'Sırada',
            self::Sending   => 'Gönderiliyor',
            self::Sent      => 'Gönderildi',
            self::Failed    => 'Başarısız',
            self::Cancelled => 'İptal edildi',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Queued    => 'info',
            self::Sending   => 'warning',
            self::Sent      => 'success',
            self::Failed    => 'danger',
            self::Cancelled => 'muted',
        };
    }

    /** Gönderim henüz başlamadıysa iptal edilebilir. */
    public function isCancellable(): bool
    {
        return $this === self::Queued;
    }

    /** Cron'un ele alacağı durumlar. */
    public function isPending(): bool
    {
        return in_array($this, [self::Queued, self::Sending], true);
    }
}
