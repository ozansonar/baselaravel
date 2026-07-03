<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Admin paneli içi bildirim — header bell icon + bildirim merkezi sayfası.
 *
 * NotificationCenter::send() ile üretilir. Telegram'a giden bildirimler
 * aynı zamanda buraya da düşer.
 */
final class AdminNotification extends Model
{
    public const LEVEL_INFO     = 'info';
    public const LEVEL_SUCCESS  = 'success';
    public const LEVEL_WARNING  = 'warning';
    public const LEVEL_ERROR    = 'error';
    public const LEVEL_CRITICAL = 'critical';

    /** @var list<string> */
    public const LEVELS = [
        self::LEVEL_INFO,
        self::LEVEL_SUCCESS,
        self::LEVEL_WARNING,
        self::LEVEL_ERROR,
        self::LEVEL_CRITICAL,
    ];

    public $timestamps = false; // sadece created_at + read_at

    protected $fillable = [
        'user_id',
        'type',
        'level',
        'title',
        'message',
        'icon',
        'action_url',
        'read_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id'    => 'integer',
            'read_at'    => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param Builder<AdminNotification> $query */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /** @param Builder<AdminNotification> $query */
    public function scopeForUser(Builder $query, ?int $userId): Builder
    {
        if ($userId === null) {
            return $query->whereNull('user_id');
        }
        // user_id NULL = tüm admin'lere broadcast, user_id set = belirli admin
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)->orWhereNull('user_id');
        });
    }

    public function levelBadgeClass(): string
    {
        return match ($this->level) {
            self::LEVEL_INFO     => 'bg-info',
            self::LEVEL_SUCCESS  => 'bg-success',
            self::LEVEL_WARNING  => 'bg-warning text-dark',
            self::LEVEL_ERROR    => 'bg-danger',
            self::LEVEL_CRITICAL => 'bg-danger',
            default              => 'bg-secondary',
        };
    }

    public function levelIcon(): string
    {
        return $this->icon ?? match ($this->level) {
            self::LEVEL_INFO     => 'bi-info-circle-fill',
            self::LEVEL_SUCCESS  => 'bi-check-circle-fill',
            self::LEVEL_WARNING  => 'bi-exclamation-triangle-fill',
            self::LEVEL_ERROR    => 'bi-x-circle-fill',
            self::LEVEL_CRITICAL => 'bi-exclamation-octagon-fill',
            default              => 'bi-bell-fill',
        };
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }
}
