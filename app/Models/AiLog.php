<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AiLogSource;
use App\Enums\AiLogStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class AiLog extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'status',
        'source',
        'product_name',
        'selected_product',
        'city_name',
        'product_topic_id',
        'category_slug',
        'prompt',
        'raw_response',
        'parsed_response',
        'blog_post_id',
        'error_message',
        'error_details',
        'api_duration_ms',
        'token_count',
        'cost_usd',
        'used_model',
        'attempt_count',
    ];

    protected $casts = [
        'status'          => AiLogStatus::class,
        'source'          => AiLogSource::class,
        'parsed_response' => 'array',
        'api_duration_ms' => 'integer',
        'token_count'     => 'integer',
        'cost_usd'        => 'decimal:6',
        'attempt_count'   => 'integer',
    ];

    // ── Relationships ──

    public function blogPost(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class);
    }

    public function productTopic(): BelongsTo
    {
        return $this->belongsTo(ProductTopic::class);
    }

    // ── Scopes ──

    public function scopeSuccessful($query)
    {
        return $query->where('status', AiLogStatus::Success);
    }

    public function scopeFailed($query)
    {
        return $query->whereNotIn('status', [
            AiLogStatus::Success,
            AiLogStatus::Started,
            AiLogStatus::ApiCalled,
        ]);
    }

    // ── Accessors ──

    public function getDurationFormattedAttribute(): string
    {
        if ($this->api_duration_ms === null) {
            return '-';
        }

        if ($this->api_duration_ms >= 1000) {
            return number_format($this->api_duration_ms / 1000, 1) . 's';
        }

        return $this->api_duration_ms . 'ms';
    }
}
