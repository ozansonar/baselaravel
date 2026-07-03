<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use App\Models\VertexBatch;
use App\Models\VertexGeneration;
use App\Models\VertexPrompt;
use App\Services\Vertex\PromptVariableParser;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class VertexBatchService
{
    /**
     * @param  array<string, string>  $variables
     */
    private static function renderIgTemplate(?string $template, array $variables): ?string
    {
        if ($template === null || trim($template) === '') {
            return null;
        }

        return PromptVariableParser::substitute($template, $variables);
    }

    /**
     * @param  array<string, string>  $variables
     */
    public function createBatch(
        VertexPrompt $template,
        int $count,
        array $variables,
        ?int $userId,
        ?int $specialDayId = null,
        ?string $mediaType = null,
        ?string $aspectRatioOverride = null,
    ): VertexBatch {
        return DB::transaction(static function () use ($template, $count, $variables, $userId, $specialDayId, $mediaType, $aspectRatioOverride): VertexBatch {
            $randomOptions = is_array($template->random_options) ? $template->random_options : [];
            $hasRandom = $randomOptions !== [];

            $aspectRatio = $aspectRatioOverride ?? $template->aspect_ratio;

            if (! $hasRandom) {
                $finalPrompt = PromptVariableParser::substitute($template->prompt, $variables);
                $igCaption = self::renderIgTemplate($template->ig_caption_template, $variables);
                $igTitle = self::renderIgTemplate($template->ig_title_template, $variables);
                $igHashtags = self::renderIgTemplate($template->ig_hashtags_template, $variables);
            }

            $batch = VertexBatch::create([
                'prompt_id'      => $template->id,
                'special_day_id' => $specialDayId,
                'media_type'     => $mediaType,
                'variables'      => $variables !== [] ? $variables : null,
                'total_count'    => $count,
                'status'         => VertexBatch::STATUS_PENDING,
                'created_by'     => $userId,
            ]);

            $referenceImages = is_array($template->reference_images) && $template->reference_images !== []
                ? $template->reference_images
                : null;

            $rows = [];
            $now = now();

            for ($i = 1; $i <= $count; $i++) {
                if ($hasRandom) {
                    $rowVars = $variables;
                    foreach ($randomOptions as $varName => $options) {
                        if (($rowVars[$varName] ?? '') === '__random__' && $options !== []) {
                            $rowVars[$varName] = $options[array_rand($options)];
                        }
                    }
                    $rowPrompt = PromptVariableParser::substitute($template->prompt, $rowVars);
                    $rowCaption = self::renderIgTemplate($template->ig_caption_template, $rowVars);
                    $rowTitle = self::renderIgTemplate($template->ig_title_template, $rowVars);
                    $rowHashtags = self::renderIgTemplate($template->ig_hashtags_template, $rowVars);
                } else {
                    $rowVars = $variables;
                    $rowPrompt = $finalPrompt;
                    $rowCaption = $igCaption;
                    $rowTitle = $igTitle;
                    $rowHashtags = $igHashtags;
                }

                $resolvedVars = array_filter($rowVars, static fn (string $v): bool => $v !== '__random__' && $v !== '');

                $rows[] = [
                    'batch_id'           => $batch->id,
                    'queue_position'     => $i,
                    'prompt_id'          => $template->id,
                    'prompt_used'        => $rowPrompt,
                    'resolved_variables' => $resolvedVars !== [] ? json_encode($resolvedVars, JSON_UNESCAPED_UNICODE) : null,
                    'reference_images' => $referenceImages !== null ? json_encode($referenceImages) : null,
                    'ig_caption'       => $rowCaption,
                    'ig_title'         => $rowTitle,
                    'ig_hashtags'      => $rowHashtags,
                    'model'            => $template->model,
                    'aspect_ratio'     => $aspectRatio,
                    'image_size'       => $template->image_size,
                    'mime_type'        => $template->mime_type,
                    'status'           => VertexGeneration::STATUS_PENDING,
                    'created_by'       => $userId,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ];
            }

            foreach (array_chunk($rows, 100) as $chunk) {
                VertexGeneration::insert($chunk);
            }

            return $batch;
        });
    }

    /**
     * @return array{cron: array, queue: array, active_batch: array|null, today: array}
     */
    public function getSystemStatus(): array
    {
        $cronRaw = Setting::getValue('vertex_cron_status');
        $cron = $cronRaw !== null ? json_decode($cronRaw, true) : null;

        $cronStatus = $cron['status'] ?? 'unknown';
        $cronTimestamp = isset($cron['timestamp']) ? Carbon::parse($cron['timestamp']) : null;
        $cronAlive = $cronTimestamp !== null && $cronTimestamp->diffInMinutes(now()) < 5;

        $activeBatch = VertexBatch::query()->active()->with('prompt:id,name')->oldest()->first();

        $queuedPending = VertexGeneration::whereIn('status', [
            VertexGeneration::STATUS_PENDING,
            VertexGeneration::STATUS_PROCESSING,
        ])->count();

        $activeBatches = VertexBatch::query()->active()->get();
        $totalQueued = $activeBatches->sum('total_count');
        $totalProcessed = $activeBatches->sum(fn (VertexBatch $b) => $b->completed_count + $b->failed_count);

        $delay = (int) ($cron['current_delay'] ?? 20);
        $remaining = max(0, $queuedPending);
        $etaSeconds = $remaining * ($delay + 25);

        $todayCompleted = VertexGeneration::where('status', VertexGeneration::STATUS_COMPLETED)
            ->whereDate('finished_at', today())
            ->count();
        $todayFailed = VertexGeneration::where('status', VertexGeneration::STATUS_FAILED)
            ->whereDate('updated_at', today())
            ->count();

        return [
            'cron' => [
                'status'             => $cronStatus,
                'alive'              => $cronAlive,
                'last_run'           => $cronTimestamp?->format('d.m.Y H:i:s'),
                'last_run_ago'       => $cronTimestamp?->diffForHumans(),
                'rate_limit_strikes' => (int) ($cron['rate_limit_strikes'] ?? 0),
                'current_delay'      => $delay,
            ],
            'queue' => [
                'pending_count'      => $queuedPending,
                'total_queued'       => $totalQueued,
                'total_processed'    => $totalProcessed,
                'active_batch_count' => $activeBatches->count(),
                'eta_seconds'        => $etaSeconds,
                'eta_human'          => self::formatEta($etaSeconds),
            ],
            'active_batch' => $activeBatch !== null ? [
                'id'              => $activeBatch->id,
                'prompt_name'     => $activeBatch->prompt?->name ?? '—',
                'status'          => $activeBatch->status,
                'total_count'     => $activeBatch->total_count,
                'completed_count' => $activeBatch->completed_count,
                'failed_count'    => $activeBatch->failed_count,
                'progress'        => $activeBatch->progress(),
                'duration'        => $activeBatch->formattedDuration(),
            ] : null,
            'today' => [
                'completed' => $todayCompleted,
                'failed'    => $todayFailed,
            ],
        ];
    }

    private static function formatEta(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . ' saniye';
        }
        $minutes = (int) floor($seconds / 60);
        if ($minutes < 60) {
            return $minutes . ' dakika';
        }
        $hours = (int) floor($minutes / 60);
        $remainMinutes = $minutes % 60;

        return $hours . ' saat ' . ($remainMinutes > 0 ? $remainMinutes . ' dk' : '');
    }
}
