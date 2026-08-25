<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PageView extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'url',
        'url_path',
        'ip_address',
        'ip_masked',
        'user_agent',
        'device_type',
        'browser',
        'browser_version',
        'os',
        'referrer',
        'referrer_domain',
        'session_id',
        'user_id',
        'is_bot',
        'bot_name',
        'screen_width',
        'screen_height',
        'viewed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_bot'        => 'bool',
            'ip_masked'     => 'bool',
            'viewed_at'     => 'datetime',
            'screen_width'  => 'integer',
            'screen_height' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeHumans(Builder $query): Builder
    {
        return $query->where('is_bot', false);
    }

    public function scopeBots(Builder $query): Builder
    {
        return $query->where('is_bot', true);
    }

    public function scopeBetweenDates(Builder $query, \DateTimeInterface $from, \DateTimeInterface $to): Builder
    {
        return $query->whereBetween('viewed_at', [$from, $to]);
    }
}
