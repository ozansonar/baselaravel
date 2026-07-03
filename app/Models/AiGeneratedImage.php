<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiGeneratedImage extends Model
{
    use HasFactory, SoftDeletes;

    public const string STATUS_PENDING = 'pending';
    public const string STATUS_COMPLETED = 'completed';
    public const string STATUS_FAILED = 'failed';

    public const string SOURCE_ADMIN_AI_IMAGES = 'admin-ai-images';
    public const string SOURCE_INSTAGRAM_CREATE = 'instagram-create';
    public const string SOURCE_BLOG_COVER = 'blog-cover';
    public const string SOURCE_PRODUCT_ENHANCE = 'product-enhance';
    public const string SOURCE_BULK_IMPORT = 'bulk-import';

    protected $fillable = [
        'prompt_template_id',
        'final_prompt',
        'image_path',
        'provider',
        'model',
        'aspect_ratio',
        'source',
        'width',
        'height',
        'status',
        'error_message',
        'generation_time_ms',
        'cost_usd',
        'attempt_count',
        'blog_post_id',
        'instagram_post_id',
        'request_payload',
        'response_payload',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'generation_time_ms' => 'integer',
            'width'              => 'integer',
            'height'             => 'integer',
            'cost_usd'           => 'decimal:6',
            'attempt_count'      => 'integer',
            'request_payload'    => 'array',
            'response_payload'   => 'array',
        ];
    }

    /**
     * @return BelongsTo<AiImagePrompt, $this>
     */
    public function promptTemplate(): BelongsTo
    {
        return $this->belongsTo(AiImagePrompt::class, 'prompt_template_id');
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

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeForSource(Builder $query, string $source): Builder
    {
        return $query->where('source', $source);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
