<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\BackupService;
use App\Services\NotificationCenter;
use App\Services\TelegramNotifier;
use Illuminate\Console\Command;

final class RunBackup extends Command
{
    protected $signature = 'backup:run';

    protected $description = 'Tam yedek al (DB + uploads) ve eski yedekleri temizle';

    public function handle(BackupService $service): int
    {
        $this->info('Yedek alınıyor...');

        $result = $service->create();

        if (! $result['success']) {
            $this->error('HATA: ' . $result['message']);

            $url = rescue(static fn (): ?string => route('admin.backups.index'), null, false);

            TelegramNotifier::notifyAdminError(
                'Otomatik yedek HATALI',
                ['hata' => $result['message']],
                $url,
                emoji: '🚨',
            );

            NotificationCenter::send(
                type: 'backup_failed',
                title: 'Yedek alma başarısız',
                message: $result['message'],
                level: 'error',
                icon: 'bi-exclamation-octagon-fill',
                actionUrl: $url,
            );

            return self::FAILURE;
        }

        $this->info('✓ ' . $result['message']);
        $this->line('  Boyut: ' . $result['size_human']);
        $this->line('  DB: ' . round($result['db_size'] / 1_048_576, 2) . ' MB');
        $this->line('  Dosyalar: ' . round($result['files_size'] / 1_048_576, 2) . ' MB');

        $url = rescue(static fn (): ?string => route('admin.backups.index'), null, false);

        // Telegram özeti
        TelegramNotifier::notifyAdminError(
            'Otomatik yedek alındı',
            [
                'dosya'    => $result['file'],
                'toplam'   => $result['size_human'],
                'db'       => round($result['db_size'] / 1_048_576, 2) . ' MB',
                'dosyalar' => round($result['files_size'] / 1_048_576, 2) . ' MB',
            ],
            $url,
            emoji: '✅',
            cacheKey: 'backup:' . now()->format('Y-m-d'),
        );

        // In-app bildirim
        NotificationCenter::send(
            type: 'backup_completed',
            title: 'Yedek alındı: ' . $result['file'],
            message: 'Toplam ' . $result['size_human'] . ' (DB ' . round($result['db_size'] / 1_048_576, 2) . ' MB + dosyalar ' . round($result['files_size'] / 1_048_576, 2) . ' MB)',
            level: 'success',
            icon: 'bi-cloud-check-fill',
            actionUrl: $url,
        );

        return self::SUCCESS;
    }
}
