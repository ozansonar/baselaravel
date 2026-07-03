<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\VertexGeneration;
use App\Models\VertexSchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class VertexScheduleService
{
    public function __construct(
        private readonly VertexBatchService $batchService,
    ) {}

    /**
     * @return array{processed: int, skipped: int, errors: int}
     */
    public function processDueSchedules(): array
    {
        $schedules = VertexSchedule::query()
            ->due()
            ->with('prompt')
            ->get();

        $processed = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($schedules as $schedule) {
            if ($schedule->prompt === null || ! $schedule->prompt->is_active) {
                $skipped++;
                continue;
            }

            try {
                $this->executeSchedule($schedule);
                $processed++;
            } catch (\Throwable $e) {
                $errors++;
                Log::error('Vertex schedule execution failed', [
                    'schedule_id' => $schedule->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        return ['processed' => $processed, 'skipped' => $skipped, 'errors' => $errors];
    }

    public function executeSchedule(VertexSchedule $schedule): void
    {
        $schedule->loadMissing('prompt');

        if ($schedule->prompt === null || ! $schedule->prompt->is_active) {
            throw new \RuntimeException("Schedule #{$schedule->id}: prompt is inactive or deleted.");
        }

        $variables = is_array($schedule->variables) ? $schedule->variables : [];

        $batch = $this->batchService->createBatch(
            template: $schedule->prompt,
            count: $schedule->count,
            variables: $variables,
            userId: $schedule->created_by,
            aspectRatioOverride: $schedule->aspect_ratio_override,
        );

        DB::table('vertex_batches')
            ->where('id', $batch->id)
            ->update(['schedule_id' => $schedule->id]);

        $schedule->markRun($schedule->count);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function store(array $data): VertexSchedule
    {
        $schedule = VertexSchedule::create($data);
        $schedule->update(['next_run_at' => $schedule->calculateNextRun()]);

        return $schedule->fresh();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(VertexSchedule $schedule, array $data): VertexSchedule
    {
        $schedule->update($data);
        $schedule->update(['next_run_at' => $schedule->calculateNextRun()]);

        return $schedule->fresh();
    }

    public function toggle(VertexSchedule $schedule): void
    {
        $newActive = ! $schedule->is_active;
        $schedule->update([
            'is_active'   => $newActive,
            'next_run_at' => $newActive ? $schedule->calculateNextRun() : null,
        ]);
    }

    public function destroy(VertexSchedule $schedule): void
    {
        $schedule->delete();
    }

    /**
     * @return array{total: int, active: int, today_generated: int, total_generated: int}
     */
    public function getStats(): array
    {
        return [
            'total'           => VertexSchedule::count(),
            'active'          => VertexSchedule::active()->count(),
            'today_generated' => VertexGeneration::where('status', VertexGeneration::STATUS_COMPLETED)
                ->whereDate('finished_at', today())
                ->whereHas('batch', fn ($q) => $q->whereNotNull('schedule_id'))
                ->count(),
            'total_generated' => VertexSchedule::sum('total_images_generated'),
        ];
    }
}
