<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Google Search Console sayfa bazlı metrikler.
 * page_type ile site içi sayfalar gruplanır:
 *   - 'product'    → ProductController
 *   - 'blog'       → BlogPost
 *   - 'city'       → CityLandingPage
 *   - 'page'       → Page (statik)
 *   - 'home'       → /
 *   - 'category'   → Kategori listesi
 *   - 'other'      → Diğer
 */
final class GscPageMetric extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const string TYPE_PRODUCT  = 'product';
    public const string TYPE_BLOG     = 'blog';
    public const string TYPE_CITY     = 'city';
    public const string TYPE_PAGE     = 'page';
    public const string TYPE_HOME     = 'home';
    public const string TYPE_CATEGORY = 'category';
    public const string TYPE_OTHER    = 'other';

    protected $fillable = [
        'page_url',
        'page_path',
        'page_type',
        'related_id',
        'date_from',
        'date_to',
        'clicks',
        'impressions',
        'ctr',
        'position',
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
            'related_id'  => 'integer',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('page_type', $type);
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
    public function scopeLowCtr(Builder $query): Builder
    {
        return $query->where('position', '<=', 10)
            ->where('ctr', '<', 2)
            ->where('impressions', '>=', 50);
    }
}
