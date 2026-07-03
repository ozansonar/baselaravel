<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SpecialDay;
use App\Models\SpecialDayImage;
use App\Models\VertexBatch;
use App\Models\VertexPrompt;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class SpecialDayVertexService
{
    private const ASPECT_FEED  = '1:1';
    private const ASPECT_STORY = '9:16';

    public function __construct(
        private readonly VertexBatchService $batchService,
    ) {}

    /**
     * @return array{batches_created: int, days_processed: int, skipped: int}
     */
    public function generateForPeriod(
        Carbon $from,
        Carbon $to,
        VertexPrompt $feedPrompt,
        VertexPrompt $storyPrompt,
        int $countPerDay,
        ?int $userId,
    ): array {
        $days = SpecialDay::query()
            ->inRange($from, $to)
            ->orderBy('date')
            ->get();

        $dayIds = $days->pluck('id')->all();
        $existingBatches = $this->loadExistingBatchMap($dayIds);

        $batchesCreated = 0;
        $skipped = 0;

        foreach ($days as $day) {
            $feedCreated = $this->createBatchForDay(
                $day, $feedPrompt, SpecialDayImage::MEDIA_FEED, self::ASPECT_FEED, $countPerDay, $userId, $existingBatches,
            );
            $storyCreated = $this->createBatchForDay(
                $day, $storyPrompt, SpecialDayImage::MEDIA_STORY, self::ASPECT_STORY, $countPerDay, $userId, $existingBatches,
            );

            if ($feedCreated) {
                $batchesCreated++;
            } else {
                $skipped++;
            }

            if ($storyCreated) {
                $batchesCreated++;
            } else {
                $skipped++;
            }
        }

        return [
            'batches_created' => $batchesCreated,
            'days_processed'  => $days->count(),
            'skipped'         => $skipped,
        ];
    }

    /**
     * @return Collection<int, SpecialDay>
     */
    public function getSpecialDaysInRange(Carbon $from, Carbon $to): Collection
    {
        return SpecialDay::query()
            ->inRange($from, $to)
            ->withCount(['images as feed_count' => fn ($q) => $q->where('media_type', SpecialDayImage::MEDIA_FEED)])
            ->withCount(['images as story_count' => fn ($q) => $q->where('media_type', SpecialDayImage::MEDIA_STORY)])
            ->orderBy('date')
            ->get();
    }

    /**
     * @param  list<int>  $dayIds
     * @return array<string, true>
     */
    public function loadExistingBatchMap(array $dayIds): array
    {
        if ($dayIds === []) {
            return [];
        }

        $rows = VertexBatch::query()
            ->whereIn('special_day_id', $dayIds)
            ->whereNotNull('media_type')
            ->whereIn('status', [
                VertexBatch::STATUS_PENDING,
                VertexBatch::STATUS_PROCESSING,
                VertexBatch::STATUS_COMPLETED,
                VertexBatch::STATUS_PARTIAL,
            ])
            ->select('special_day_id', 'media_type')
            ->distinct()
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[$row->special_day_id . ':' . $row->media_type] = true;
        }

        return $map;
    }

    private function createBatchForDay(
        SpecialDay $day,
        VertexPrompt $prompt,
        string $mediaType,
        string $aspectRatio,
        int $count,
        ?int $userId,
        array $existingBatches,
    ): bool {
        if (isset($existingBatches[$day->id . ':' . $mediaType])) {
            return false;
        }

        $variables = [
            'ozel_gun_adi'      => $day->name,
            'ozel_gun_tarihi'   => $day->date->translatedFormat('d F'),
            'ozel_gun_temasi'   => $day->theme ?? $day->name,
            'ozel_gun_kategori' => $day->categoryLabel(),
        ];

        $this->batchService->createBatch(
            template: $prompt,
            count: $count,
            variables: $variables,
            userId: $userId,
            specialDayId: $day->id,
            mediaType: $mediaType,
            aspectRatioOverride: $aspectRatio,
        );

        return true;
    }
}
