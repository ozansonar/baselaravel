<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CustomRouteType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Panelden açılmış bir adres.
 *
 * Dili boş olan kayıt her dilde geçerli; dolu olan yalnız o dilde. İkisi
 * birden eşleşirse dile özgü olan kazanıyor — genel bir kural koyup tek bir
 * dilde ayrıksı davranmak mümkün olsun diye.
 */
class CustomRoute extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'locale',
        'slug',
        'target_route',
        'target_params',
        'type',
        'is_active',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'target_params' => 'array',
            'type'          => CustomRouteType::class,
            'is_active'     => 'boolean',
        ];
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Bu dilde geçerli kayıtlar: dile özgü olanlar ve tüm dilleri kapsayanlar.
     *
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeForLocale(Builder $query, string $locale): Builder
    {
        return $query->where(function (Builder $inner) use ($locale): void {
            $inner->where('locale', $locale)->orWhereNull('locale');
        });
    }

    /** Ekranda gösterilecek dil adı. */
    public function localeLabel(): string
    {
        return $this->locale === null ? 'Tüm diller' : strtoupper($this->locale);
    }
}
