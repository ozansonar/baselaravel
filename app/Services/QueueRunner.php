<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Kuyruktaki işleri elle çalıştırır.
 *
 * Paylaşımlı hostingde queue:work yok (pcntl eksik), kuyruk dakikada bir
 * çalışan zamanlanmış görevin işleri tek tek pop edip fire etmesiyle
 * ilerliyor. Aynı iş panelden de tetiklenebildiği için mantık burada tek
 * yerde duruyor: zamanlanmış görev de, "şimdi gönder" düğmesi de bunu çağırır.
 */
final class QueueRunner
{
    /** Zamanlanmış görevin bir turda işlediği iş sayısı. */
    public const MAX_JOBS = 20;

    /** Cron dakikasından taşmamak için bırakılan süre. */
    public const MAX_SECONDS = 50;

    /**
     * Kuyruğu işle.
     *
     * Bir işin patlaması kalanları durdurmaz: iş fail olarak işaretlenir ve
     * sıradakine geçilir.
     *
     * @return array{processed: int, failed: int, remaining: int}
     */
    public function drain(int $maxJobs = self::MAX_JOBS, int $maxSeconds = self::MAX_SECONDS): array
    {
        $start = time();
        $processed = 0;
        $failed = 0;

        $queue = app('queue')->connection('database');

        while ($processed + $failed < $maxJobs && (time() - $start) < $maxSeconds) {
            $job = $queue->pop('default');

            if ($job === null) {
                break;
            }

            try {
                $job->fire();
                $job->delete();
                $processed++;
            } catch (\Throwable $e) {
                $job->fail($e);
                report($e);
                $failed++;
            }
        }

        return [
            'processed' => $processed,
            'failed'    => $failed,
            'remaining' => $this->pendingJobs(),
        ];
    }

    /**
     * Kuyrukta bekleyen iş sayısı.
     */
    public function pendingJobs(): int
    {
        try {
            return (int) DB::table('jobs')->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
