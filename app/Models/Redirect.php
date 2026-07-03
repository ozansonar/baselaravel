<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Redirect extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'old_url',
        'new_url',
        'status_code',
        'hit_count',
        'last_hit_at',
        'is_active',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'hit_count'   => 'integer',
            'last_hit_at' => 'datetime',
            'is_active'   => 'boolean',
        ];
    }

    /**
     * @param Builder<Redirect> $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param Builder<Redirect> $query
     */
    public function scopeByStatusCode(Builder $query, int $code): void
    {
        $query->where('status_code', $code);
    }
}
