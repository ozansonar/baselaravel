<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriberStatus;
use App\Support\PersonName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Subscriber extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'email',
        'first_name',
        'last_name',
        'locale',
        'status',
        'source',
        'unsubscribe_token',
        'subscribed_at',
        'unsubscribed_at',
    ];

    protected function casts(): array
    {
        return [
            'status'          => SubscriberStatus::class,
            'subscribed_at'   => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $subscriber): void {
            $subscriber->unsubscribe_token ??= self::newToken();
            $subscriber->subscribed_at ??= now();
        });
    }

    public static function newToken(): string
    {
        return Str::lower(Str::random(64));
    }

    /**
     * Gösterim için birleşik isim; ad ve soyad ayrı sütunlarda tutuluyor.
     */
    public function getFullNameAttribute(): ?string
    {
        return PersonName::full($this->first_name, $this->last_name);
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeSubscribed(Builder $query): Builder
    {
        return $query->where('status', SubscriberStatus::Subscribed);
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeLocale(Builder $query, ?string $locale): Builder
    {
        return $locale === null ? $query : $query->where('locale', $locale);
    }
}
