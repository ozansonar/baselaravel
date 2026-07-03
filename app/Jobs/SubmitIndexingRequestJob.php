<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\IndexingRequest;
use App\Services\Google\IndexingApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * IndexingRequest'i Google'a gönderen async job.
 * Observer'lar (Product/BlogPost/CityLandingPage/Page) yayınlanan içerik
 * için bu job'ı dispatch eder — admin yayınla butonuna basınca beklemez.
 *
 * Retry: 3 deneme, 60-300sn aralıkla (queue worker yönetir).
 */
final class SubmitIndexingRequestJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 180, 420];

    public function __construct(
        public readonly int $indexingRequestId,
    ) {}

    public function handle(IndexingApiService $service): void
    {
        $request = IndexingRequest::find($this->indexingRequestId);

        if ($request === null) {
            return;
        }

        if ($request->isSuccess()) {
            return;
        }

        $service->submit($request);
    }

    public function failed(Throwable $e): void
    {
        $request = IndexingRequest::find($this->indexingRequestId);

        if ($request === null) {
            return;
        }

        $request->update([
            'status'        => IndexingRequest::STATUS_FAILED,
            'error_message' => mb_substr('Job failed (' . self::class . '): ' . $e->getMessage(), 0, 1000),
            'sent_at'       => now(),
        ]);
    }
}
