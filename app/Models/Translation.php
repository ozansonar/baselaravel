<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One overridden interface string.
 *
 * Only rows an admin actually changed live here; everything else comes from the
 * lang/ files.
 */
class Translation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'locale',
        'group',
        'key',
        'value',
    ];

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeFor(Builder $query, string $locale, string $group): Builder
    {
        return $query->where('locale', $locale)->where('group', $group);
    }
}
