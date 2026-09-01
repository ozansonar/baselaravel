<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriberStatus;
use App\Support\PersonName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property string|null $first_name
 * @property string|null $last_name
 */
class Subscriber extends Model implements HasLocalePreference
{
    use HasFactory, SoftDeletes;

    /**
     * Abonenin bülteni okuduğu dil.
     *
     * Laravel, alıcı bu arayüzü uyguladığında maili kendiliğinden bu dilde
     * çiziyor; kayıtlı dil yoksa gönderimin kendi dili geçerli kalıyor.
     */
    public function preferredLocale(): ?string
    {
        return $this->locale;
    }

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
     * Kişinin bulunduğu listeler. Üyelik çoklu: bir tedarikçi aynı zamanda
     * bültene de kayıtlı olabilir.
     *
     * @return BelongsToMany<SubscriberList, $this>
     */
    public function lists(): BelongsToMany
    {
        return $this->belongsToMany(SubscriberList::class, 'subscriber_list_subscriber')->withTimestamps();
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
