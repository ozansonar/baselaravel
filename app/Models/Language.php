<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Language extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'native_name',
        'flag',
        'is_active',
        'is_default',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
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
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeSorted(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * The language's name in its own language.
     *
     * name sütunu dili Türkçe adlandırıyor ("İngilizce"); ön yüzde okunan ad
     * bu olamaz. Aynı ifade üç ayrı görünümde elle yazılmıştı ve biri geride
     * kalmıştı: dil değiştiricinin listesi "English" derken ekran okuyucuya
     * "İngilizce" diyordu.
     */
    public function displayName(): string
    {
        return $this->native_name ?: $this->name;
    }

    /**
     * Label for pickers: flag plus the name in its own language.
     */
    public function label(): string
    {
        return trim(($this->flag ? $this->flag . ' ' : '') . $this->displayName());
    }
}
