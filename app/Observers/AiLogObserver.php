<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\AiLogStatus;
use App\Models\AdminNotification;
use App\Models\AiLog;
use App\Services\NotificationCenter;
use App\Services\TelegramNotifier;
use Illuminate\Support\Facades\Log;

/**
 * AI üretim hatalarında merkezi bildirim noktası.
 *
 * Daha önce sadece blog akışında Telegram bildirimi vardı; topic cluster,
 * ürün/sayfa/şehir AI servisleri sessizce fail oluyordu. Bu observer log'un
 * status'ü bir fail durumuna geçtiğinde TÜM AI servisleri için
 * Telegram + in-app bell çift bildirim atar.
 *
 * Throttle: TelegramNotifier::notifyAdminError 60sn throttle yapar (aynı
 * cacheKey için). NotificationCenter::send 5dk throttle yapar. İkisi
 * birlikte bildirim spam'ını önler.
 */
final class AiLogObserver
{
    private const FAIL_STATUSES = [
        AiLogStatus::ApiFailed,
        AiLogStatus::ParseError,
        AiLogStatus::CategoryMissing,
        AiLogStatus::PostFailed,
        AiLogStatus::Failed,
    ];

    /**
     * Status fail durumuna geçtiğinde bildirim at. Created hook YOK çünkü
     * yeni log'lar Started durumunda oluşur — fail tek-yönlü geçiştir.
     */
    public function updated(AiLog $log): void
    {
        if (! $log->wasChanged('status')) {
            return;
        }
        if (! in_array($log->status, self::FAIL_STATUSES, true)) {
            return;
        }

        try {
            $sourceLabel = $log->source?->label() ?? 'AI';
            $topic       = $log->product_name ?: '—';
            $errorMsg    = $log->error_message ?: 'Sebep belirtilmemiş';
            $logUrl      = rescue(static fn (): ?string => route('admin.ai-logs.show', $log), null, false);

            // Telegram — 60sn throttle (TelegramNotifier içinde)
            TelegramNotifier::notifyAdminError(
                title: "AI üretim hatası: {$sourceLabel}",
                context: [
                    'durum'  => $log->status->label(),
                    'konu'   => $topic,
                    'log_id' => "#{$log->id}",
                    'hata'   => $errorMsg,
                ],
                url: $logUrl,
                cacheKey: 'ai_log_fail:' . $log->source?->value . ':' . $log->status->value . ':' . md5($topic . '|' . $errorMsg),
            );

            // In-app bell — Telegram kapalı/throttle olsa bile görsün
            NotificationCenter::send(
                type: 'ai_generation_failed',
                title: "AI hatası ({$sourceLabel}): {$log->status->label()}",
                message: $topic . ' — ' . mb_strimwidth($errorMsg, 0, 200, '…', 'UTF-8'),
                level: AdminNotification::LEVEL_ERROR,
                icon: 'bi-robot',
                actionUrl: $logUrl,
            );
        } catch (\Throwable $e) {
            // Observer asla update işlemini bloke etmemeli — sadece warning log.
            Log::warning('AiLogObserver bildirim gönderiminde hata', [
                'log_id' => $log->id,
                'error'  => $e->getMessage(),
            ]);
        }
    }
}
