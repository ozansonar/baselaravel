<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\VertexGeneration;
use App\Services\Vertex\VertexImageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Async Vertex AI image generation.
 *
 * Tek görsel üretimi 10-60 sn sürdüğü için kullanıcı tarayıcısını kilitlemeyiz.
 * Controller pending bir VertexGeneration kaydı oluşturup bu Job'u dispatch eder;
 * UI status endpoint'ini her 2 saniyede bir polling yaparak sonucu çeker.
 */
final class GenerateVertexImageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;
    public int $timeout = 480;

    private const int MAX_RETRIES = 3;
    private const RETRY_BACKOFF = [20, 40, 60];

    public function __construct(
        public readonly int $generationId,
    ) {}

    public function handle(VertexImageService $service): void
    {
        $generation = VertexGeneration::find($this->generationId);
        if ($generation === null) {
            Log::warning('GenerateVertexImageJob: kayıt bulunamadı', ['id' => $this->generationId]);
            return;
        }

        if ($generation->status === VertexGeneration::STATUS_COMPLETED) {
            return;
        }

        $result = $service->execute($generation);

        if ($result['success'] || empty($result['retryable'])) {
            return;
        }

        for ($attempt = 0; $attempt < self::MAX_RETRIES; $attempt++) {
            sleep(self::RETRY_BACKOFF[$attempt] ?? 60);

            $result = $service->execute($generation);

            if ($result['success'] || empty($result['retryable'])) {
                return;
            }
        }

        $generation->update([
            'status'        => VertexGeneration::STATUS_FAILED,
            'error_message' => $result['message'] . ' (' . self::MAX_RETRIES . ' retry sonrası)',
            'finished_at'   => now(),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateVertexImageJob: kritik hata', [
            'id'    => $this->generationId,
            'error' => $exception->getMessage(),
        ]);

        $generation = VertexGeneration::find($this->generationId);
        if ($generation !== null && $generation->status !== VertexGeneration::STATUS_COMPLETED) {
            $generation->update([
                'status'        => VertexGeneration::STATUS_FAILED,
                'error_message' => 'Queue hatası: ' . $exception->getMessage(),
            ]);
        }
    }
}
