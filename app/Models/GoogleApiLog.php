<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Google API çağrı geçmişi — admin sağlık widget'ı + hata analizi.
 * Retention: 3 ay (PurgeOldGoogleMetrics komutu).
 */
final class GoogleApiLog extends Model
{
    use HasFactory;

    public const string SERVICE_GSC      = 'gsc';
    public const string SERVICE_GA4      = 'ga4';
    public const string SERVICE_INDEXING = 'indexing';

    public const string STATUS_SUCCESS = 'success';
    public const string STATUS_FAILED  = 'failed';

    protected $fillable = [
        'service',
        'operation',
        'status',
        'http_code',
        'error_message',
        'request_payload',
        'response_size',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'http_code'       => 'integer',
            'response_size'   => 'integer',
            'duration_ms'     => 'integer',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeService(Builder $query, string $service): Builder
    {
        return $query->where('service', $service);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeRecent(Builder $query, int $hours = 24): Builder
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }
}
