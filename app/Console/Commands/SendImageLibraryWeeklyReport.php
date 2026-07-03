<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MediaLibraryImage;
use App\Services\TelegramNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Görsel kütüphanesi haftalık özet raporu — Telegram'a Pazartesi sabah gönderilir.
 *
 * İçerik:
 *  - Bu hafta kullanılan görsel sayısı + tip dağılımı
 *  - En çok kullanılan 3 görsel (description ile)
 *  - Kütüphane stok durumu (toplam, hiç kullanılmamış)
 *  - Tüm-zamanlar en popüler tema
 */
final class SendImageLibraryWeeklyReport extends Command
{
    protected $signature = 'image-library:weekly-report';

    protected $description = 'Görsel kütüphanesi haftalık özet — Telegram bildirimi';

    public function handle(): int
    {
        if (! TelegramNotifier::isEnabled()) {
            $this->warn('Telegram pasif — özet gönderilmedi.');
            return self::SUCCESS;
        }

        $weekAgo = now()->subWeek();

        // Bu hafta kullanılan görseller
        $usedThisWeek = MediaLibraryImage::query()
            ->where('last_used_at', '>=', $weekAgo)
            ->count();

        $usedByType = MediaLibraryImage::query()
            ->where('last_used_at', '>=', $weekAgo)
            ->select('media_type', DB::raw('COUNT(*) as cnt'))
            ->groupBy('media_type')
            ->pluck('cnt', 'media_type')
            ->all();

        // En çok kullanılan 3 görsel (tüm zaman)
        $topUsed = MediaLibraryImage::query()
            ->where('usage_count', '>', 0)
            ->orderByDesc('usage_count')
            ->limit(3)
            ->get(['id', 'description', 'media_type', 'usage_count', 'theme']);

        // Genel stok
        $totalCount = MediaLibraryImage::count();
        $neverUsed  = MediaLibraryImage::where('usage_count', 0)->count();

        // En popüler tema
        $topTheme = MediaLibraryImage::query()
            ->whereNotNull('theme')
            ->select('theme', DB::raw('SUM(usage_count) as total_usage'))
            ->groupBy('theme')
            ->orderByDesc('total_usage')
            ->first();

        $topTopUsedLines = '';
        if ($topUsed->isNotEmpty()) {
            foreach ($topUsed as $i => $img) {
                $desc = $img->description ? mb_strimwidth($img->description, 0, 60, '…', 'UTF-8') : "#{$img->id}";
                $topTopUsedLines .= ($i + 1) . ". {$desc} ({$img->usage_count} kez, {$img->media_type})\n";
            }
        } else {
            $topTopUsedLines = '— Henüz kullanılan görsel yok';
        }

        $detailUrl = rescue(static fn (): ?string => route('admin.image-library.analyze'), null, false);

        TelegramNotifier::notifyAdminError(
            'Haftalık Görsel Kütüphanesi Özeti',
            [
                'kullanilan'       => "{$usedThisWeek} görsel (Feed: " . ($usedByType['feed'] ?? 0) . ', Story: ' . ($usedByType['story'] ?? 0) . ', Reels: ' . ($usedByType['reels'] ?? 0) . ')',
                'toplam_stok'      => $totalCount . ' görsel',
                'hic_kullanilmayan' => $neverUsed . ' görsel',
                'pop_tema'         => $topTheme ? $topTheme->theme . ' (' . $topTheme->total_usage . ' kullanım)' : '—',
                'top_kullanilan'   => "\n" . $topTopUsedLines,
            ],
            $detailUrl,
            emoji: '📊',
            cacheKey: 'weekly_il_report:' . now()->format('Y-W'),
        );

        $this->info("Haftalık özet gönderildi: {$usedThisWeek} kullanım, {$totalCount} toplam görsel.");

        return self::SUCCESS;
    }
}
