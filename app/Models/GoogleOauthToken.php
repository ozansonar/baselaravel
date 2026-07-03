<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Stored Google OAuth 2.0 token for a single admin user.
 *
 * Tokens are encrypted at rest via Eloquent's `encrypted` cast — a DB dump
 * alone doesn't reveal them, only `APP_KEY` + DB together does.
 */
final class GoogleOauthToken extends Model
{
    use HasFactory, SoftDeletes;

    /** @var string */
    protected $table = 'google_oauth_tokens';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'google_email',
        'access_token',
        'refresh_token',
        'token_type',
        'scopes',
        'expires_at',
        'last_refreshed_at',
    ];

    protected function casts(): array
    {
        return [
            'access_token'      => 'encrypted',
            'refresh_token'     => 'encrypted',
            'expires_at'        => 'datetime',
            'last_refreshed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Token is expired if no expiry is recorded or its expiry is in the past.
     */
    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return true;
        }

        return $this->expires_at->isPast();
    }

    /**
     * 5-minute buffer — refresh proactively to avoid 401 mid-call.
     */
    public function isExpiringSoon(): bool
    {
        if ($this->expires_at === null) {
            return true;
        }

        return $this->expires_at->lte(now()->addMinutes(5));
    }

    /**
     * @return list<string>
     */
    public function scopeList(): array
    {
        return array_values(array_filter(explode(' ', (string) $this->scopes)));
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopeList(), true);
    }

    /**
     * Display-only short label for the integration card.
     */
    protected function lastRefreshedLabel(): Attribute
    {
        return Attribute::get(
            fn (): string => $this->last_refreshed_at?->diffForHumans() ?? 'henüz yenilenmedi',
        );
    }
}
