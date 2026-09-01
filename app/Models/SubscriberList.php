<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Adlandırılmış abone listesi: tedarikçiler, pazarlamacılar, bülten…
 *
 * Kampanyanın hedefi bu listelerden seçiliyor. Üyelik çoklu — aynı kişi
 * birden fazla listede olabilir.
 */
class SubscriberList extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_default',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<Subscriber, $this>
     */
    public function subscribers(): BelongsToMany
    {
        // Tablo adı açıkça bildiriliyor: Laravel'in alfabetik varsayılanı
        // "subscriber_subscriber_list" olurdu, okunmuyor.
        return $this->belongsToMany(Subscriber::class, 'subscriber_list_subscriber')->withTimestamps();
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
