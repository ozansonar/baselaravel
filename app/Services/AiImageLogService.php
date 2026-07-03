<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiGeneratedImage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Read-only stats + filter facade over `ai_generated_images` for the
 * /admin/ai-image-logs viewer.
 *
 * No HTTP calls here — pure DB aggregates. Heavy queries are the unfiltered
 * grand-total summaries, so we keep them small via single-pass GROUP BYs.
 */
final class AiImageLogService
{
    /** @var array<string, string> Source slug → human label */
    public const array SOURCE_LABELS = [
        AiGeneratedImage::SOURCE_ADMIN_AI_IMAGES  => 'Admin / AI Görsel',
        AiGeneratedImage::SOURCE_INSTAGRAM_CREATE => 'Instagram Post',
        AiGeneratedImage::SOURCE_BLOG_COVER       => 'Blog Kapak',
        AiGeneratedImage::SOURCE_PRODUCT_ENHANCE  => 'Ürün Foto Enhance',
        AiGeneratedImage::SOURCE_BULK_IMPORT      => 'Bulk Import',
    ];

    /**
     * @param  array{source?: string, status?: string, model?: string, q?: string, date_from?: string, date_to?: string}  $filters
     * @return LengthAwarePaginator<int, AiGeneratedImage>
     */
    public function paginate(int $perPage, array $filters = []): LengthAwarePaginator
    {
        return $this->applyFilters(AiGeneratedImage::query()->with(['promptTemplate', 'author']), $filters)
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Top-line stats: today / this month / all-time totals + cost.
     *
     * @return array{
     *     today: array{count: int, cost: float},
     *     month: array{count: int, cost: float},
     *     total: array{count: int, cost: float},
     *     success_rate: float,
     *     completed: int,
     *     failed: int,
     * }
     */
    public function summary(): array
    {
        $today = AiGeneratedImage::query()
            ->whereDate('created_at', today())
            ->selectRaw('COUNT(*) as c, COALESCE(SUM(cost_usd), 0) as s')
            ->first();

        $month = AiGeneratedImage::query()
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->selectRaw('COUNT(*) as c, COALESCE(SUM(cost_usd), 0) as s')
            ->first();

        $totalRow = AiGeneratedImage::query()
            ->selectRaw('COUNT(*) as c, COALESCE(SUM(cost_usd), 0) as s')
            ->first();

        $completed = AiGeneratedImage::query()
            ->where('status', AiGeneratedImage::STATUS_COMPLETED)
            ->count();

        $failed = AiGeneratedImage::query()
            ->where('status', AiGeneratedImage::STATUS_FAILED)
            ->count();

        $totalCount = (int) ($totalRow->c ?? 0);
        $successRate = $totalCount > 0 ? round(($completed / $totalCount) * 100, 1) : 0.0;

        return [
            'today'        => ['count' => (int) $today->c, 'cost' => (float) $today->s],
            'month'        => ['count' => (int) $month->c, 'cost' => (float) $month->s],
            'total'        => ['count' => $totalCount,      'cost' => (float) $totalRow->s],
            'success_rate' => $successRate,
            'completed'    => $completed,
            'failed'       => $failed,
        ];
    }

    /**
     * Distinct model names that appear in the table — for filter dropdown.
     *
     * @return list<string>
     */
    public function distinctModels(): array
    {
        return AiGeneratedImage::query()
            ->whereNotNull('model')
            ->where('model', '!=', '')
            ->distinct()
            ->orderBy('model')
            ->pluck('model')
            ->all();
    }

    /**
     * Source breakdown — count + cost per source (for sidebar/breakdown panel).
     *
     * @return list<array{source: ?string, count: int, cost: float}>
     */
    public function sourceBreakdown(): array
    {
        return AiGeneratedImage::query()
            ->selectRaw('source, COUNT(*) as count, COALESCE(SUM(cost_usd), 0) as cost')
            ->groupBy('source')
            ->orderByDesc('count')
            ->get()
            ->map(static fn ($row): array => [
                'source' => $row->source,
                'count'  => (int) $row->count,
                'cost'   => (float) $row->cost,
            ])
            ->all();
    }

    /**
     * @param  Builder<AiGeneratedImage>  $query
     * @param  array{source?: string, status?: string, model?: string, q?: string, date_from?: string, date_to?: string}  $filters
     * @return Builder<AiGeneratedImage>
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['model'])) {
            $query->where('model', 'like', '%' . $filters['model'] . '%');
        }

        if (! empty($filters['q'])) {
            $term = '%' . $filters['q'] . '%';
            $query->where(static function (Builder $q) use ($term): void {
                $q->where('final_prompt', 'like', $term)
                  ->orWhere('error_message', 'like', $term);
            });
        }

        if (! empty($filters['date_from'])) {
            try {
                $query->where('created_at', '>=', Carbon::parse($filters['date_from'])->startOfDay());
            } catch (\Throwable) {
                // ignore malformed input — no filter applied
            }
        }

        if (! empty($filters['date_to'])) {
            try {
                $query->where('created_at', '<=', Carbon::parse($filters['date_to'])->endOfDay());
            } catch (\Throwable) {
                // ignore
            }
        }

        return $query;
    }

    /**
     * Lookup a human label for a source slug. Falls back to the slug itself.
     */
    public static function sourceLabel(?string $source): string
    {
        if ($source === null || $source === '') {
            return '—';
        }

        return self::SOURCE_LABELS[$source] ?? $source;
    }

    /**
     * Bootstrap badge color class for a source slug.
     */
    public static function sourceColor(?string $source): string
    {
        return match ($source) {
            AiGeneratedImage::SOURCE_ADMIN_AI_IMAGES  => 'bg-info',
            AiGeneratedImage::SOURCE_INSTAGRAM_CREATE => 'bg-primary',
            AiGeneratedImage::SOURCE_BLOG_COVER       => 'bg-warning text-dark',
            AiGeneratedImage::SOURCE_PRODUCT_ENHANCE  => 'bg-success',
            AiGeneratedImage::SOURCE_BULK_IMPORT      => 'bg-secondary',
            default                                   => 'bg-dark',
        };
    }
}
