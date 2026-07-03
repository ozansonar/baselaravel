<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CityLandingPage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'city_name',
        'city_slug',
        'region',
        'tier',
        'title',
        'meta_title',
        'meta_description',
        'hero_heading',
        'hero_description',
        'content',
        'shipping_note',
        'delivery_time',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'tier'       => 'integer',
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    // ── Scopes ──

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeSorted(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('city_name');
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeTier(Builder $query, int $tier): Builder
    {
        return $query->where('tier', $tier);
    }

    // ── Helpers ──

    /**
     * URL slug segment: "{city_slug}-koy-urunleri"
     */
    public function getRoutePath(): string
    {
        return $this->city_slug . '-koy-urunleri';
    }
}
