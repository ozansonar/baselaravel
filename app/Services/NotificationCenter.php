<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationLevel;
use App\Models\AdminNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Admin paneli içi bildirim merkezi.
 *
 * Kullanım:
 *  NotificationCenter::send('backup_failed', 'Yedek alınamadı', 'Disk dolu', level: NotificationLevel::Error, actionUrl: ...);
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
        NotificationLevel $level = NotificationLevel::Info,
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
                'level'      => $level,
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

        return self::send('critical', $title, $message, NotificationLevel::Critical, null, null, $actionUrl);
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
     * Bildirim merkezindeki özet kutuları.
     *
     * @return array{total: int, unread: int, today: int, critical: int}
     */
    public static function stats(?int $userId = null): array
    {
        $base = static fn (): Builder => AdminNotification::query()->forUser($userId);

        return [
            'total'    => $base()->count(),
            'unread'   => $base()->whereNull('read_at')->count(),
            'today'    => $base()->whereDate('created_at', now()->toDateString())->count(),
            'critical' => $base()->whereIn('level', [NotificationLevel::Critical->value, NotificationLevel::Error->value])->count(),
        ];
    }

    /**
     * Sekme rozetleri için seviye başına adet.
     *
     * @return array<string, int>
     */
    public static function levelCounts(?int $userId = null): array
    {
        /** @var array<string, int> $counts */
        $counts = AdminNotification::query()
            ->forUser($userId)
            ->selectRaw('level, count(*) as total')
            ->groupBy('level')
            ->pluck('total', 'level')
            ->all();

        return $counts;
    }

    /**
     * "Bildirim Özeti" kartındaki dağılım — hangi olay ne sıklıkta bildirim
     * üretiyor.
     *
     * Aynı işin başarılı ve başarısız hâli ayrı type değerleri taşıyor
     * (backup_completed / backup_failed); okuyan için ikisi de "Yedekleme"
     * olduğundan satırlar etikete göre birleştirilir.
     *
     * @return list<array{label: string, count: int, percent: int, color: string}>
     */
    public static function typeSummary(?int $userId = null, int $limit = 6): array
    {
        $rows = AdminNotification::query()
            ->forUser($userId)
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->get();

        /** @var array<string, array{label: string, count: int, color: string}> $merged */
        $merged = [];

        foreach ($rows as $row) {
            $sample = new AdminNotification(['type' => $row->type]);
            $label = $sample->typeLabel();

            $merged[$label] ??= ['label' => $label, 'count' => 0, 'color' => self::summaryColor($sample->typeTagVariant())];
            $merged[$label]['count'] += (int) $row->total;
        }

        $summary = array_values($merged);

        usort($summary, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        $summary = array_slice($summary, 0, $limit);
        $highest = $summary === [] ? 0 : (int) max(array_column($summary, 'count'));

        return array_map(static function (array $row) use ($highest): array {
            // Çubuklar en yoğun türe göre ölçekleniyor; toplam içindeki pay
            // değil, birbirine göre büyüklük okunuyor. Genişlik satır içi style
            // yerine sınıfla verildiği için beşin katına yuvarlanıyor.
            $row['percent'] = $highest > 0 ? (int) (round($row['count'] / $highest * 100 / 5) * 5) : 0;

            return $row;
        }, $summary);
    }

    /**
     * Tema etiket sınıfını özet çubuğunun renk sınıfına çevirir.
     */
    private static function summaryColor(string $variant): string
    {
        return match ($variant) {
            'security' => 'c-red',
            'content'  => 'c-purple',
            'update'   => 'c-orange',
            'user'     => 'c-green',
            default    => 'c-blue',
        };
    }

    /**
     * Okundu işaretini geri al — yanlışlıkla okunan bildirim listede kalsın.
     */
    public static function markUnread(int $notificationId, ?int $userId = null): bool
    {
        return AdminNotification::query()
            ->where('id', $notificationId)
            ->forUser($userId)
            ->update(['read_at' => null]) > 0;
    }

    /**
     * @param list<int> $ids
     */
    public static function markManyRead(array $ids, ?int $userId = null): int
    {
        if ($ids === []) {
            return 0;
        }

        return AdminNotification::query()
            ->whereIn('id', $ids)
            ->forUser($userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Seçilen bildirimleri sil.
     *
     * Başka bir yöneticiye özel bildirim silinemez: forUser zaten yalnızca
     * kendi bildirimlerini ve herkese açık olanları kapsar.
     *
     * @param list<int> $ids
     */
    public static function deleteMany(array $ids, ?int $userId = null): int
    {
        if ($ids === []) {
            return 0;
        }

        return AdminNotification::query()
            ->whereIn('id', $ids)
            ->forUser($userId)
            ->delete();
    }

    /**
     * Listeyi tamamen boşalt.
     */
    public static function deleteAll(?int $userId = null): int
    {
        return AdminNotification::query()->forUser($userId)->delete();
    }

    /**
     * Eski bildirimleri temizle (cron).
     *
     * Saklama süresi temizliği olduğu için forceDelete kullanılır; normal
     * delete yalnızca deleted_at doldurur ve tablo süresiz büyümeye devam
     * ederdi.
     */
    public static function pruneOlderThan(int $days = 60): int
    {
        return AdminNotification::withTrashed()
            ->where('created_at', '<', now()->subDays($days))
            ->forceDelete();
    }
}
