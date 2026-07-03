<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Google Indexing API gönderim kaydı (queue + history).
 * Yayınlanan içerik (Product/BlogPost/CityLandingPage/Page) sonrası
 * Observer bir satır açar, Job arka planda Google'a gönderir.
 */
final class IndexingRequest extends Model
{
    use HasFactory;

    public const string STATUS_PENDING = 'pending';
    public const string STATUS_SENT    = 'sent';
    public const string STATUS_SUCCESS = 'success';
    public const string STATUS_FAILED  = 'failed';

    public const string TYPE_UPDATED = 'URL_UPDATED';
    public const string TYPE_DELETED = 'URL_DELETED';

    protected $fillable = [
        'url',
        'type',
        'status',
        'related_type',
        'related_id',
        'response',
        'error_message',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'response'   => 'array',
            'sent_at'    => 'datetime',
            'related_id' => 'integer',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeSucceeded(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
