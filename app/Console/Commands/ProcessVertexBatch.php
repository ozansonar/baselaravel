<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\VertexBatch;
use App\Models\VertexGeneration;
use App\Services\TelegramNotifier;
use App\Services\Vertex\VertexImageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class ProcessVertexBatch extends Command
{
    protected $signature = 'vertex:process-batch';
    protected $description = 'Kuyruktaki Vertex batch generation\'larını sırayla işle';

    private const int DEFAULT_DELAY_SECONDS = 20;
    private const int RATE_LIMITED_DELAY_SECONDS = 90;
    private const int COOLDOWN_AFTER_BURST_SECONDS = 300;

    private const int MAX_CONSECUTIVE_HARD_FAILURES = 5;
    private const int MAX_RATE_LIMIT_STRIKES = 10;

    private const int MAX_RETRIES = 3;
    private const array RETRY_BACKOFF_SECONDS = [60, 120, 180];

    private int $currentDelay;
    private int $rateLimitStrikes = 0;

    public function handle(VertexImageService $service): int
    {
        $this->currentDelay = (int) (Setting::getValue('vertex_batch_delay', (string) self::DEFAULT_DELAY_SECONDS) ?: self::DEFAULT_DELAY_SECONDS);
        $this->currentDelay = max(10, $this->currentDelay);

        $this->rescueOrphanedGenerations();

        $batch = VertexBatch::query()
            ->active()
            ->oldest()
            ->first();

        if ($batch === null) {
            $this->info('İşlenecek batch yok.');
            $this->writeCronStatus('idle');
            return self::SUCCESS;
        }

        if (trim((string) Setting::getValue('vertex_api_key', '')) === '') {
            $this->error('Vertex API key eksik — batch iptal edildi.');
            $this->sendTelegram("❌ <b>Vertex API key eksik</b>\nBatch #{$batch->id} iptal edildi. Admin → Ayarlar → AI → Google Vertex AI bölümünden ekleyin.");
            $batch->cancel();
            return self::FAILURE;
        }

        if ($batch->status === VertexBatch::STATUS_PENDING) {
            $batch->markProcessing();
            $this->notifyBatchStarted($batch);
        }

        $this->writeCronStatus('running', $batch->id);

        $consecutiveHardFailures = 0;

        while (true) {
            $generation = $this->claimNextGeneration($batch);

            if ($generation === null) {
                break;
            }

            $batch->refresh();
            $this->info(sprintf(
                'Batch #%d — Sıra %d/%d işleniyor… (aralık: %dsn)',
                $batch->id,
                $generation->queue_position,
                $batch->total_count,
                $this->currentDelay,
            ));

            $result = $this->executeWithRetry($service, $generation, $batch);

            if ($result['success']) {
                $consecutiveHardFailures = 0;
                $this->info('  ✓ Başarılı' . ($result['retried'] ? ' (retry sonrası)' : ''));

                if ($this->rateLimitStrikes > 0 && $this->rateLimitStrikes <= 3) {
                    $this->rateLimitStrikes = max(0, $this->rateLimitStrikes - 1);
                }
            } else {
                if ($result['rate_limited'] ?? false) {
                    $this->handleRateLimitCooldown($batch);
                } else {
                    $consecutiveHardFailures++;
                    $this->error('  ✗ Hata: ' . $result['message']);
                    $this->notifyGenerationFailed($batch, $generation, $result['message']);

                    if ($consecutiveHardFailures >= self::MAX_CONSECUTIVE_HARD_FAILURES) {
                        $this->error(sprintf(
                            'Ardışık %d kalıcı hata! Batch #%d durduruluyor.',
                            self::MAX_CONSECUTIVE_HARD_FAILURES,
                            $batch->id,
                        ));
                        $this->notifyBatchStopped($batch, $result['message']);
                        $batch->cancel();
                        return self::FAILURE;
                    }
                }
            }

            $batch->refresh();
            if (! $batch->isActive()) {
                break;
            }

            if ($this->rateLimitStrikes >= self::MAX_RATE_LIMIT_STRIKES) {
                $this->error('Çok fazla 429 hatası! Batch duraklatılıyor — sonraki cron denemesinde devam edecek.');
                $this->writeCronStatus('rate_limited', $batch->id);
                $this->sendTelegram(
                    "⏸ <b>Vertex Batch #{$batch->id} duraklatıldı</b>\n"
                    . "Çok fazla 429 (rate limit) hatası alındı.\n"
                    . "Batch aktif kalacak, sonraki cron çalışmasında devam edecek."
                );
                return self::SUCCESS;
            }

            if (! ($result['rate_limited'] ?? false)) {
                sleep($this->currentDelay);
            }
        }

        $batch->refresh();
        $this->writeCronStatus('idle');
        $this->notifyBatchFinished($batch);

        $this->info(sprintf(
            'Batch #%d tamamlandı: %d başarılı, %d hatalı, süre: %s',
            $batch->id,
            $batch->completed_count,
            $batch->failed_count,
            $batch->formattedDuration(),
        ));

        return self::SUCCESS;
    }

    private function handleRateLimitCooldown(VertexBatch $batch): void
    {
        $this->rateLimitStrikes++;

        if ($this->rateLimitStrikes >= 3) {
            $this->currentDelay = max($this->currentDelay, self::RATE_LIMITED_DELAY_SECONDS);
        }

        $cooldown = match (true) {
            $this->rateLimitStrikes >= 6 => self::COOLDOWN_AFTER_BURST_SECONDS,
            $this->rateLimitStrikes >= 3 => self::RATE_LIMITED_DELAY_SECONDS * 2,
            default                      => self::RATE_LIMITED_DELAY_SECONDS,
        };

        $this->warn(sprintf(
            '  ⏳ Rate limit #%d — %dsn bekleniyor (sonraki aralık: %dsn)',
            $this->rateLimitStrikes,
            $cooldown,
            $this->currentDelay,
        ));

        if ($this->rateLimitStrikes === 3) {
            $this->sendTelegram(
                "⚠️ <b>Vertex rate limit</b> (Batch #{$batch->id})\n"
                . "3 kez 429 hatası alındı, aralık {$this->currentDelay}sn'ye çıkarıldı."
            );
        }

        sleep($cooldown);
    }

    private function rescueOrphanedGenerations(): void
    {
        $orphans = VertexGeneration::query()
            ->where(function ($q): void {
                $q->where('status', VertexGeneration::STATUS_PENDING)
                  ->orWhere(function ($q2): void {
                      $q2->where('status', VertexGeneration::STATUS_PROCESSING)
                         ->where('started_at', '<', now()->subMinutes(30));
                  });
            })
            ->whereHas('batch', function ($q): void {
                $q->whereNotIn('status', [VertexBatch::STATUS_PENDING, VertexBatch::STATUS_PROCESSING]);
            })
            ->get();

        if ($orphans->isEmpty()) {
            return;
        }

        $grouped = $orphans->groupBy('prompt_id');

        $totalRescued = 0;

        foreach ($grouped as $promptId => $gens) {
            $count = $gens->count();

            $rescueBatch = VertexBatch::create([
                'prompt_id'   => $promptId,
                'total_count' => $count,
                'status'      => VertexBatch::STATUS_PENDING,
                'created_by'  => $gens->first()->created_by,
            ]);

            $position = 0;
            foreach ($gens as $gen) {
                $position++;
                $gen->update([
                    'batch_id'       => $rescueBatch->id,
                    'queue_position' => $position,
                    'status'         => VertexGeneration::STATUS_PENDING,
                    'started_at'     => null,
                    'error_message'  => null,
                ]);
            }

            $totalRescued += $count;
            $this->info("Yetim kurtarma: {$count} generation → yeni Batch #{$rescueBatch->id} (prompt #{$promptId})");
        }

        $this->sendTelegram(
            "🔄 <b>Yetim kurtarma:</b> {$totalRescued} generation yeni batch'lere taşındı.\n"
            . "Tamamlanmış batch'lerde pending kalmış üretimler kuyruğa alındı."
        );
    }

    private function claimNextGeneration(VertexBatch $batch): ?VertexGeneration
    {
        return DB::transaction(function () use ($batch): ?VertexGeneration {
            $generation = VertexGeneration::query()
                ->where('batch_id', $batch->id)
                ->where('status', VertexGeneration::STATUS_PENDING)
                ->orderBy('queue_position')
                ->lockForUpdate()
                ->first();

            if ($generation === null) {
                return null;
            }

            $generation->update([
                'status'     => VertexGeneration::STATUS_PROCESSING,
                'started_at' => now(),
            ]);

            return $generation;
        });
    }

    /**
     * @return array{success: bool, message: string, retried: bool, rate_limited?: bool}
     */
    private function executeWithRetry(
        VertexImageService $service,
        VertexGeneration $generation,
        VertexBatch $batch,
    ): array {
        $result = $service->execute($generation);

        if ($result['success'] || empty($result['retryable'])) {
            return [...$result, 'retried' => false];
        }

        for ($attempt = 0; $attempt < self::MAX_RETRIES; $attempt++) {
            $wait = self::RETRY_BACKOFF_SECONDS[$attempt] ?? 180;
            $this->warn(sprintf(
                '  ⏳ Retry bekleniyor — %dsn (deneme %d/%d)',
                $wait,
                $attempt + 2,
                self::MAX_RETRIES + 1,
            ));
            sleep($wait);

            $batch->refresh();
            if (! $batch->isActive()) {
                $generation->update([
                    'status'        => VertexGeneration::STATUS_FAILED,
                    'error_message' => 'Batch iptal edildi.',
                    'finished_at'   => now(),
                ]);
                if ($generation->batch_id !== null) {
                    $generation->batch?->incrementFailed();
                }
                return ['success' => false, 'message' => 'Batch iptal edildi.', 'retryable' => false, 'retried' => true];
            }

            $result = $service->execute($generation);

            if ($result['success']) {
                return [...$result, 'retried' => true];
            }

            if (empty($result['retryable'])) {
                return [...$result, 'retried' => true];
            }
        }

        $isRateLimited = str_contains($result['message'], '429');

        if ($isRateLimited) {
            $generation->update([
                'status'        => VertexGeneration::STATUS_PENDING,
                'error_message' => $result['message'],
                'started_at'    => null,
            ]);

            return [
                'success'      => false,
                'message'      => $result['message'],
                'retryable'    => false,
                'retried'      => true,
                'rate_limited' => true,
            ];
        }

        $generation->update([
            'status'        => VertexGeneration::STATUS_FAILED,
            'error_message' => $result['message'] . ' (' . self::MAX_RETRIES . ' retry sonrası)',
            'finished_at'   => now(),
        ]);

        if ($generation->batch_id !== null) {
            $generation->batch?->incrementFailed();
        }

        return [
            'success'      => false,
            'message'      => $result['message'],
            'retryable'    => false,
            'retried'      => true,
            'rate_limited' => false,
        ];
    }

    private function notifyBatchStarted(VertexBatch $batch): void
    {
        $promptName = $batch->prompt?->name ?? '—';

        $text = "🚀 <b>Vertex Batch #{$batch->id} başladı</b>\n"
            . "📋 Şablon: <b>{$promptName}</b>\n"
            . "🖼 Üretilecek: <b>{$batch->total_count}</b> görsel\n"
            . "⏱ Aralık: {$this->currentDelay}sn";

        $this->sendTelegram($text);
    }

    private function notifyBatchFinished(VertexBatch $batch): void
    {
        $emoji = $batch->failed_count === 0 ? '✅' : '⚠️';
        $promptName = $batch->prompt?->name ?? '—';

        $text = "{$emoji} <b>Vertex Batch #{$batch->id} tamamlandı</b>\n"
            . "📋 Şablon: {$promptName}\n"
            . "✓ Başarılı: <b>{$batch->completed_count}</b>/{$batch->total_count}\n"
            . "✗ Hatalı: <b>{$batch->failed_count}</b>\n"
            . "⏱ Süre: {$batch->formattedDuration()}";

        if ($this->rateLimitStrikes > 0) {
            $text .= "\n🔄 Rate limit: {$this->rateLimitStrikes} kez";
        }

        $this->sendTelegram($text);
    }

    private function notifyGenerationFailed(VertexBatch $batch, VertexGeneration $generation, string $error): void
    {
        $short = mb_substr($error, 0, 200, 'UTF-8');
        $text = "⚠️ <b>Vertex hata</b> (Batch #{$batch->id}, sıra {$generation->queue_position}/{$batch->total_count})\n"
            . "<code>{$short}</code>";

        $this->sendTelegram($text);
    }

    private function notifyBatchStopped(VertexBatch $batch, string $lastError): void
    {
        $short = mb_substr($lastError, 0, 200, 'UTF-8');
        $text = "🛑 <b>Vertex Batch #{$batch->id} DURDU</b>\n"
            . "Ardışık " . self::MAX_CONSECUTIVE_HARD_FAILURES . " kalıcı hata!\n"
            . "Son hata: <code>{$short}</code>\n"
            . "Kalan görseller iptal edildi.";

        $this->sendTelegram($text);
    }

    private function writeCronStatus(string $status, ?int $batchId = null): void
    {
        $data = [
            'status'             => $status,
            'batch_id'           => $batchId,
            'rate_limit_strikes' => $this->rateLimitStrikes,
            'current_delay'      => $this->currentDelay,
            'timestamp'          => now()->toIso8601String(),
        ];

        Setting::setValue('vertex_cron_status', json_encode($data), 'system', 'json');
    }

    private function sendTelegram(string $text): void
    {
        try {
            if (TelegramNotifier::isEnabled()) {
                TelegramNotifier::send($text);
            }
        } catch (\Throwable $e) {
            Log::warning('Vertex batch Telegram notification failed', ['error' => $e->getMessage()]);
        }
    }
}
