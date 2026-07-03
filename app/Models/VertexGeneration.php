<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class VertexGeneration extends Model
{
    use HasFactory, SoftDeletes;

    public const string STATUS_PENDING = 'pending';
    public const string STATUS_PROCESSING = 'processing';
    public const string STATUS_COMPLETED = 'completed';
    public const string STATUS_FAILED = 'failed';

    protected $fillable = [
        'batch_id',
        'queue_position',
        'prompt_id',
        'prompt_used',
        'resolved_variables',
        'reference_images',
        'output_image_path',
        'ig_caption',
        'ig_title',
        'ig_hashtags',
        'usage_count',
        'model',
        'aspect_ratio',
        'image_size',
        'mime_type',
        'width',
        'height',
        'status',
        'error_message',
        'generation_time_ms',
        'started_at',
        'finished_at',
        'cost_usd',
        'token_count',
        'request_payload',
        'response_payload',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'resolved_variables' => 'array',
            'reference_images'   => 'array',
            'usage_count'        => 'integer',
            'width'              => 'integer',
            'height'             => 'integer',
            'generation_time_ms' => 'integer',
            'queue_position'     => 'integer',
            'token_count'        => 'integer',
            'cost_usd'           => 'decimal:6',
            'started_at'         => 'datetime',
            'finished_at'        => 'datetime',
            'request_payload'    => 'array',
            'response_payload'   => 'array',
        ];
    }

    /**
     * @return BelongsTo<VertexBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(VertexBatch::class, 'batch_id');
    }

    /**
     * @return BelongsTo<VertexPrompt, $this>
     */
    public function prompt(): BelongsTo
    {
        return $this->belongsTo(VertexPrompt::class, 'prompt_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<InstagramPost, $this>
     */
    public function instagramPosts(): HasMany
    {
        return $this->hasMany(InstagramPost::class, 'vertex_generation_id');
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isPending(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING], true);
    }

    public function retryGeneration(?int $userId): VertexBatch
    {
        return DB::transaction(function () use ($userId): VertexBatch {
            $batch = VertexBatch::create([
                'prompt_id'   => $this->prompt_id,
                'total_count' => 1,
                'status'      => VertexBatch::STATUS_PENDING,
                'created_by'  => $userId,
            ]);

            $this->update([
                'batch_id'           => $batch->id,
                'queue_position'     => 1,
                'status'             => self::STATUS_PENDING,
                'error_message'      => null,
                'started_at'         => null,
                'finished_at'        => null,
                'generation_time_ms' => null,
                'output_image_path'  => null,
                'token_count'        => null,
                'cost_usd'           => null,
            ]);

            return $batch;
        });
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeQueued(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING)
            ->whereNotNull('batch_id')
            ->orderBy('batch_id')
            ->orderBy('queue_position');
    }
}
