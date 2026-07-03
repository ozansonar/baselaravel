<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Instagram post insights — Meta Graph API'den çekilen engagement metric'leri.
 *
 * Bir post için zamanla birden fazla snapshot alınır (yayın sonrası 24-48-72 saat).
 * (instagram_post_id, fetched_at) unique — aynı dakika tekrar fetch yapılmaz.
 */
final class InstagramPostInsight extends Model
{
    protected $fillable = [
        'instagram_post_id',
        'fetched_at',
        'likes',
        'comments',
        'reach',
        'impressions',
        'saves',
        'shares',
        'plays',
        'raw_response',
    ];

    protected function casts(): array
    {
        return [
            'instagram_post_id' => 'integer',
            'fetched_at'        => 'datetime',
            'likes'             => 'integer',
            'comments'          => 'integer',
            'reach'             => 'integer',
            'impressions'       => 'integer',
            'saves'             => 'integer',
            'shares'            => 'integer',
            'plays'             => 'integer',
            'raw_response'      => 'array',
        ];
    }

    /** @return BelongsTo<InstagramPost, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(InstagramPost::class, 'instagram_post_id');
    }

    /** @param Builder<InstagramPostInsight> $query */
    public function scopeLatest(Builder $query): Builder
    {
        return $query->orderByDesc('fetched_at');
    }

    /**
     * Engagement Rate (ER) = (likes + comments + saves + shares) / reach × 100
     */
    public function engagementRate(): ?float
    {
        if ($this->reach <= 0) return null;
        $engagement = $this->likes + $this->comments + $this->saves + $this->shares;
        return round(($engagement / $this->reach) * 100, 2);
    }
}
