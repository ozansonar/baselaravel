<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Google Search Console arama sorgusu metrikleri.
 * Tek satır = belirli bir tarih aralığında belirli bir kelimenin tek-kayıt verisi.
 */
final class GscQueryMetric extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'query',
        'date_from',
        'date_to',
        'clicks',
        'impressions',
        'ctr',
        'position',
        'country',
        'device',
    ];

    protected function casts(): array
    {
        return [
            'date_from'   => 'date',
            'date_to'     => 'date',
            'clicks'      => 'integer',
            'impressions' => 'integer',
            'ctr'         => 'decimal:2',
            'position'    => 'decimal:2',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeBetween(Builder $query, string $from, string $to): Builder
    {
        return $query->whereDate('date_from', '>=', $from)
            ->whereDate('date_to', '<=', $to);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeMostClicked(Builder $query): Builder
    {
        return $query->orderByDesc('clicks');
    }
}
