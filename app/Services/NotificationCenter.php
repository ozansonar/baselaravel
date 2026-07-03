<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AdminNotification;
use Illuminate\Support\Facades\Cache;

/**
 * Admin paneli içi bildirim merkezi.
 *
 * Kullanım:
 *  NotificationCenter::send('post_failed', 'IG paylaşım başarısız', 'Post #123 fail oldu', level: 'error', actionUrl: ...);
 *  NotificationCenter::sendCritical('Backup hatası', 'Disk dolu', actionUrl: ...);
 *
 * Throttle: aynı (type + title hash) için 5 dakika tekrar gönderilmez.
 */
final class NotificationCenter
{
    /**
     * Bildirim oluştur. user_id=null verirse tüm admin'lere broadcast.
     */
    public static function send(
        string $type,
        string $title,
        ?string $message = null,
        string $level = AdminNotification::LEVEL_INFO,
        ?int $userId = null,
        ?string $icon = null,
        ?string $actionUrl = null,
    ): ?AdminNotification {
        // Throttle (5dk) — message dahil; farklı detaylı bildirimlerin yutulmaması için
        $cacheKey = 'admin_notif:' . md5($type . '|' . $title . '|' . ($message ?? '') . '|' . ($userId ?? 'all'));
        if (Cache::has($cacheKey)) {
            return null;
        }
        Cache::put($cacheKey, 1, now()->addMinutes(5));

        try {
            return AdminNotification::create([
                'user_id'    => $userId,
                'type'       => $type,
                'level'      => in_array($level, AdminNotification::LEVELS, true) ? $level : 'info',
                'title'      => mb_strimwidth($title, 0, 200, '…'),
                'message'    => $message ? mb_strimwidth($message, 0, 1000, '…') : null,
                'icon'       => $icon,
                'action_url' => $actionUrl ? mb_strimwidth($actionUrl, 0, 500, '') : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Kritik seviye kısayol — Telegram + in-app çift bildirim */
    public static function sendCritical(string $title, ?string $message = null, ?string $actionUrl = null): ?AdminNotification
    {
        // Telegram'a da düşür (önemli)
        if (TelegramNotifier::isEnabled()) {
            TelegramNotifier::notifyAdminError($title, $message ? ['detay' => $message] : [], $actionUrl, emoji: '🚨');
        }

        return self::send('critical', $title, $message, AdminNotification::LEVEL_CRITICAL, null, null, $actionUrl);
    }

    /** Belirli kullanıcı için okundu işaretle */
    public static function markRead(int $notificationId, ?int $userId = null): bool
    {
        $query = AdminNotification::where('id', $notificationId);
        if ($userId !== null) {
            $query->forUser($userId);
        }

        return $query->update(['read_at' => now()]) > 0;
    }

    public static function markAllRead(?int $userId = null): int
    {
        return AdminNotification::query()
            ->forUser($userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public static function unreadCount(?int $userId = null): int
    {
        return AdminNotification::query()
            ->forUser($userId)
            ->unread()
            ->count();
    }

    /**
     * Eski bildirimleri temizle (cron).
     */
    public static function pruneOlderThan(int $days = 60): int
    {
        return AdminNotification::where('created_at', '<', now()->subDays($days))->delete();
    }
}
