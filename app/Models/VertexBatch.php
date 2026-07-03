<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class VertexBatch extends Model
{
    use SoftDeletes;

    public const string STATUS_PENDING = 'pending';
    public const string STATUS_PROCESSING = 'processing';
    public const string STATUS_COMPLETED = 'completed';
    public const string STATUS_PARTIAL = 'partial_completed';
    public const string STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'prompt_id',
        'special_day_id',
        'schedule_id',
        'media_type',
        'variables',
        'total_count',
        'completed_count',
        'failed_count',
        'status',
        'started_at',
        'finished_at',
        'total_generation_time_ms',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'variables'                => 'array',
            'special_day_id'           => 'integer',
            'total_count'              => 'integer',
            'completed_count'          => 'integer',
            'failed_count'             => 'integer',
            'total_generation_time_ms' => 'integer',
            'started_at'               => 'datetime',
            'finished_at'              => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<VertexPrompt, $this>
     */
    public function prompt(): BelongsTo
    {
        return $this->belongsTo(VertexPrompt::class, 'prompt_id');
    }

    /**
     * @return HasMany<VertexGeneration, $this>
     */
    public function generations(): HasMany
    {
        return $this->hasMany(VertexGeneration::class, 'batch_id');
    }

    /**
     * @return BelongsTo<SpecialDay, $this>
     */
    public function specialDay(): BelongsTo
    {
        return $this->belongsTo(SpecialDay::class);
    }

    /**
     * @return BelongsTo<VertexSchedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(VertexSchedule::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PROCESSING);
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_PROCESSING]);
    }

    public function progress(): float
    {
        if ($this->total_count === 0) {
            return 100.0;
        }

        return round(($this->completed_count + $this->failed_count) / $this->total_count * 100, 1);
    }

    public function processedCount(): int
    {
        return $this->completed_count + $this->failed_count;
    }

    public function remainingCount(): int
    {
        return max(0, $this->total_count - $this->processedCount());
    }

    public function markProcessing(): void
    {
        $this->update([
            'status'     => self::STATUS_PROCESSING,
            'started_at' => $this->started_at ?? now(),
        ]);
    }

    public function incrementCompleted(int $generationTimeMs = 0): void
    {
        $this->increment('completed_count');
        if ($generationTimeMs > 0) {
            $this->increment('total_generation_time_ms', $generationTimeMs);
        }
        $this->checkAndFinalize();
    }

    public function incrementFailed(): void
    {
        $this->increment('failed_count');
        $this->checkAndFinalize();
    }

    public function checkAndFinalize(): void
    {
        $this->refresh();

        $actualCompleted = $this->generations()
            ->where('status', VertexGeneration::STATUS_COMPLETED)
            ->count();
        $actualFailed = $this->generations()
            ->where('status', VertexGeneration::STATUS_FAILED)
            ->count();
        $actualProcessed = $actualCompleted + $actualFailed;

        if ($this->completed_count !== $actualCompleted || $this->failed_count !== $actualFailed) {
            $this->update([
                'completed_count' => $actualCompleted,
                'failed_count'    => $actualFailed,
            ]);
        }

        if ($actualProcessed < $this->total_count) {
            return;
        }

        $status = $actualFailed === 0
            ? self::STATUS_COMPLETED
            : ($actualCompleted === 0 ? self::STATUS_CANCELLED : self::STATUS_PARTIAL);

        $this->update([
            'status'      => $status,
            'finished_at' => now(),
        ]);
    }

    public function cancel(): void
    {
        $this->generations()
            ->whereIn('status', [VertexGeneration::STATUS_PENDING, VertexGeneration::STATUS_PROCESSING])
            ->update(['status' => VertexGeneration::STATUS_FAILED, 'error_message' => 'Batch iptal edildi.', 'finished_at' => now()]);

        $actualFailed = $this->generations()
            ->where('status', VertexGeneration::STATUS_FAILED)
            ->count();

        $this->update([
            'status'       => self::STATUS_CANCELLED,
            'failed_count' => $actualFailed,
            'finished_at'  => now(),
        ]);
    }

    /**
     * @return array{batch: self, retried_count: int}
     */
    public function retryFailed(?int $userId): array
    {
        return DB::transaction(function () use ($userId): array {
            $failedGenerations = $this->generations()
                ->where('status', VertexGeneration::STATUS_FAILED)
                ->get();

            $retryBatch = self::create([
                'prompt_id'   => $this->prompt_id,
                'total_count' => $failedGenerations->count(),
                'status'      => self::STATUS_PENDING,
                'created_by'  => $userId,
            ]);

            $position = 0;
            foreach ($failedGenerations as $gen) {
                $position++;
                $gen->update([
                    'batch_id'           => $retryBatch->id,
                    'queue_position'     => $position,
                    'status'             => VertexGeneration::STATUS_PENDING,
                    'error_message'      => null,
                    'started_at'         => null,
                    'finished_at'        => null,
                    'generation_time_ms' => null,
                    'output_image_path'  => null,
                    'token_count'        => null,
                    'cost_usd'           => null,
                ]);
            }

            return ['batch' => $retryBatch, 'retried_count' => $failedGenerations->count()];
        });
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING], true);
    }

    public function formattedDuration(): string
    {
        $ms = $this->total_generation_time_ms;
        if ($ms < 1000) {
            return $ms . 'ms';
        }
        $seconds = (int) round($ms / 1000);
        if ($seconds < 60) {
            return $seconds . 'sn';
        }
        $minutes = (int) floor($seconds / 60);
        $remaining = $seconds % 60;

        return $minutes . 'dk ' . $remaining . 'sn';
    }
}
