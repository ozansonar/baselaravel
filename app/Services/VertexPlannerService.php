<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InstagramMediaType;
use App\Enums\InstagramPostStatus;
use App\Models\InstagramPost;
use App\Models\VertexGeneration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class VertexPlannerService
{
    public function __construct(
        private readonly VertexImageSelector $selector,
    ) {}

    /**
     * @param  list<int>|null  $promptIds
     * @return array{posts: list<array>, feed_count: int, story_count: int, total: int, warnings: list<string>}
     */
    public function preview(
        Carbon $from,
        Carbon $to,
        bool $includeFeed,
        bool $includeStory,
        string $feedTime,
        string $storyTime,
        ?array $promptIds = null,
    ): array {
        $days = $this->buildDayRange($from, $to);
        $posts = [];
        $warnings = [];
        $usedIds = [];

        $feedNeeded = $includeFeed ? count($days) : 0;
        $storyNeeded = $includeStory ? count($days) : 0;

        $feedRatios = ['1:1', '4:5'];

        if ($includeFeed) {
            $feedAvailable = $this->selector->availableCount($feedRatios, promptIds: $promptIds);
            if ($feedAvailable < $feedNeeded) {
                $warnings[] = "Feed için {$feedNeeded} görsel gerekli, galeride {$feedAvailable} adet var. Bazı görseller tekrar kullanılacak.";
            }
        }

        if ($includeStory) {
            $storyAvailable = $this->selector->availableCount('9:16', promptIds: $promptIds);
            if ($storyAvailable < $storyNeeded) {
                $warnings[] = "Story için {$storyNeeded} görsel gerekli, galeride {$storyAvailable} adet var. Bazı görseller tekrar kullanılacak.";
            }
        }

        if ($includeFeed) {
            $feedImages = $this->pickImagesForDays($days, $feedRatios, $promptIds, $usedIds);
            foreach ($days as $i => $day) {
                $gen = $feedImages[$i] ?? null;
                if ($gen !== null) {
                    $usedIds[] = $gen->id;
                }
                $posts[] = $this->buildPreviewRow($day, $feedTime, InstagramMediaType::Image, $gen);
            }
        }

        if ($includeStory) {
            $storyImages = $this->pickImagesForDays($days, '9:16', $promptIds, $usedIds);
            foreach ($days as $i => $day) {
                $gen = $storyImages[$i] ?? null;
                if ($gen !== null) {
                    $usedIds[] = $gen->id;
                }
                $posts[] = $this->buildPreviewRow($day, $storyTime, InstagramMediaType::Story, $gen);
            }
        }

        usort($posts, static fn (array $a, array $b): int => strcmp($a['scheduled_at'], $b['scheduled_at']));

        return [
            'posts'       => $posts,
            'feed_count'  => $includeFeed ? count($days) : 0,
            'story_count' => $includeStory ? count($days) : 0,
            'total'       => count($posts),
            'warnings'    => $warnings,
        ];
    }

    /**
     * @param  list<array>  $planData
     * @return array{created: int, skipped: int}
     */
    public function confirm(array $planData, int $userId): array
    {
        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($planData, $userId, &$created, &$skipped): void {
            foreach ($planData as $item) {
                $generationId = $item['vertex_generation_id'] ?? null;
                if ($generationId === null || ($item['image_path'] ?? null) === null) {
                    $skipped++;
                    continue;
                }

                $scheduledAt = Carbon::parse($item['scheduled_at']);

                $exists = InstagramPost::query()
                    ->where('scheduled_at', $scheduledAt)
                    ->where('media_type', $item['media_type'])
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                $hashtags = [];
                if (! empty($item['ig_hashtags'])) {
                    $hashtags = array_values(array_filter(
                        preg_split('/[\s,]+/', (string) $item['ig_hashtags']) ?: [],
                        static fn (string $h): bool => $h !== '',
                    ));
                }

                InstagramPost::create([
                    'media_type'    => InstagramMediaType::from($item['media_type']),
                    'image_path'    => $item['image_path'],
                    'caption'       => $item['ig_caption'] ?? '',
                    'hashtags'      => $hashtags,
                    'scheduled_at'  => $scheduledAt,
                    'status'        => InstagramPostStatus::Scheduled,
                    'created_by'    => $userId,
                ]);

                VertexGeneration::where('id', $generationId)->increment('usage_count');
                $created++;
            }
        });

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * @param  list<Carbon>    $days
     * @param  list<int>|null  $promptIds
     * @param  list<int>       $excludeIds
     * @return list<VertexGeneration|null>
     */
    private function pickImagesForDays(array $days, string|array $aspectRatio, ?array $promptIds, array &$excludeIds): array
    {
        $needed = count($days);
        $images = $this->selector->pickLeastUsed($aspectRatio, $needed, promptIds: $promptIds, excludeIds: $excludeIds);
        $imageList = $images->values()->all();

        if (count($imageList) >= $needed) {
            return $imageList;
        }

        $shortage = $needed - count($imageList);
        $fallback = $this->selector->pickLeastUsed($aspectRatio, $shortage, promptIds: $promptIds, excludeIds: []);
        $fallbackList = $fallback->values()->all();

        $fallbackIdx = 0;
        while (count($imageList) < $needed) {
            if (isset($fallbackList[$fallbackIdx])) {
                $imageList[] = $fallbackList[$fallbackIdx];
                $fallbackIdx++;
            } else {
                $imageList[] = $fallbackList[0] ?? null;
                $fallbackIdx = 1;
            }
        }

        return $imageList;
    }

    /**
     * @return list<Carbon>
     */
    private function buildDayRange(Carbon $from, Carbon $to): array
    {
        $days = [];
        $current = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        while ($current->lte($end)) {
            $days[] = $current->copy();
            $current->addDay();
        }

        return $days;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPreviewRow(Carbon $day, string $time, InstagramMediaType $mediaType, ?VertexGeneration $generation): array
    {
        [$hour, $minute] = explode(':', $time);
        $scheduledAt = $day->copy()->setTime((int) $hour, (int) $minute);

        return [
            'date'                  => $day->format('d.m.Y'),
            'day_name'              => $day->translatedFormat('l'),
            'time'                  => $time,
            'scheduled_at'          => $scheduledAt->toIso8601String(),
            'media_type'            => $mediaType->value,
            'media_type_label'      => $mediaType->label(),
            'image_path'            => $generation?->output_image_path,
            'thumb_url'             => $generation?->output_image_path ? upload_url($generation->output_image_path, 'thumb') : null,
            'full_url'              => $generation?->output_image_path ? upload_url($generation->output_image_path) : null,
            'ig_caption'            => $generation?->ig_caption,
            'ig_title'              => $generation?->ig_title,
            'ig_hashtags'           => $generation?->ig_hashtags,
            'vertex_generation_id'  => $generation?->id,
            'prompt_name'           => $generation?->prompt?->name,
            'usage_count'           => $generation?->usage_count ?? 0,
        ];
    }
}
