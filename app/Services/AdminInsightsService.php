<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AdminNotification;
use App\Models\AuditLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Dashboard "Sistem & İçgörü" widget'ı için kompakt özet servisi.
 *
 * Detay sayfalarına link veren mini kartlar: sistem sağlık,
 * okunmamış bildirim sayısı, son yedek tarihi, son aktivite.
 */
final class AdminInsightsService
{
    private const CACHE_TTL = 300; // 5 dakika

    /**
     * @return array{
     *   health: array{status: string, label: string},
     *   notifications: array{unread: int, last_critical: ?string},
     *   backup: array{exists: bool, age_hours: ?int, filename: ?string},
     *   activity: array{count_24h: int, last_event: ?string, last_at: ?Carbon},
     *   hashtag_top: ?array{tag: string, avg_engagement_rate: float},
     *   best_slot: ?array{day_label: string, hour: int}
     * }
     */
    public function summary(): array
    {
        // Cache key user-specific — bildirim sayısı/son kritik kullanıcıya göre değişir
        $userKey = (int) (auth()->id() ?? 0);
        return Cache::remember("admin_insights:summary:u{$userKey}", self::CACHE_TTL, function (): array {
            return [
                'health'        => $this->healthSnapshot(),
                'notifications' => $this->notificationsSnapshot(),
                'backup'        => $this->backupSnapshot(),
                'activity'      => $this->activitySnapshot(),
                'hashtag_top'   => $this->hashtagTopSnapshot(),
                'best_slot'     => $this->bestSlotSnapshot(),
            ];
        });
    }

    /**
     * @return array{status: string, label: string}
     */
    private function healthSnapshot(): array
    {
        try {
            $report = app(HealthCheckService::class)->runAll();
            $overall = (string) ($report['summary']['overall'] ?? 'unknown');
            $label = match ($overall) {
                'ok'       => 'Tüm sistem sağlıklı',
                'warning'  => 'Uyarılar var',
                'critical' => 'Kritik sorun',
                default    => 'Bilinmiyor',
            };
            return ['status' => $overall, 'label' => $label];
        } catch (\Throwable) {
            return ['status' => 'unknown', 'label' => 'Kontrol edilemedi'];
        }
    }

    /**
     * @return array{unread: int, last_critical: ?string}
     */
    private function notificationsSnapshot(): array
    {
        $userId = auth()->id();
        // forUser scope: user_id NULL (broadcast) + user_id == $userId — tek noktada tutarlı
        $unread = AdminNotification::query()
            ->forUser($userId)
            ->unread()
            ->count();

        $lastCritical = AdminNotification::query()
            ->forUser($userId)
            ->whereIn('level', [
                AdminNotification::LEVEL_CRITICAL,
                AdminNotification::LEVEL_ERROR,
            ])
            ->orderByDesc('created_at')
            ->value('title');

        return [
            'unread'        => $unread,
            'last_critical' => $lastCritical !== null ? (string) $lastCritical : null,
        ];
    }

    /**
     * @return array{exists: bool, age_hours: ?int, filename: ?string}
     */
    private function backupSnapshot(): array
    {
        try {
            $backups = app(BackupService::class)->list();
            if (empty($backups)) {
                return ['exists' => false, 'age_hours' => null, 'filename' => null];
            }
            $latest = $backups[0];
            $created = $latest['created_at'] ?? null;
            if (! $created instanceof Carbon) {
                $created = $created ? Carbon::parse((string) $created) : null;
            }
            return [
                'exists'    => true,
                'age_hours' => $created ? (int) $created->diffInHours(now(), absolute: true) : null,
                'filename'  => (string) ($latest['name'] ?? $latest['filename'] ?? ''),
            ];
        } catch (\Throwable) {
            return ['exists' => false, 'age_hours' => null, 'filename' => null];
        }
    }

    /**
     * @return array{count_24h: int, last_event: ?string, last_at: ?Carbon}
     */
    private function activitySnapshot(): array
    {
        $count = AuditLog::query()->where('created_at', '>=', now()->subDay())->count();
        $latest = AuditLog::query()->orderByDesc('created_at')->first();

        return [
            'count_24h' => $count,
            'last_event' => $latest?->eventLabel(),
            'last_at'    => $latest?->created_at,
        ];
    }

    /**
     * @return ?array{tag: string, avg_engagement_rate: float}
     */
    private function hashtagTopSnapshot(): ?array
    {
        try {
            $top = app(HashtagAnalyticsService::class)->topByEngagement(1, 90);
            if ($top->isEmpty()) return null;
            $row = $top->first();
            return [
                'tag'                 => (string) $row['tag'],
                'avg_engagement_rate' => (float) $row['avg_engagement_rate'],
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return ?array{day_label: string, hour: int}
     */
    private function bestSlotSnapshot(): ?array
    {
        try {
            $slots = app(BestTimeAnalyticsService::class)->topSlots(1, 90);
            if (empty($slots)) return null;
            return [
                'day_label' => (string) $slots[0]['day_label'],
                'hour'      => (int) $slots[0]['hour'],
            ];
        } catch (\Throwable) {
            return null;
        }
    }
}
