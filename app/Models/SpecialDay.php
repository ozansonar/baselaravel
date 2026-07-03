<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Türkiye için özel gün takvimi.
 *
 * Kaynaklar (source):
 *  - fixed  → Sabit tarihli (1 Ocak Yılbaşı, 23 Nisan vs.) — config'den seed
 *  - rule   → Kural-bazlı (Anneler Günü 2.Pzr, Babalar 3.Pzr) — formülle hesaplanır
 *  - ai     → Hicri günler için AI'dan otomatik çekilir (yıllık 1 kez)
 *  - manual → Admin paneli üzerinden elle eklenmiş
 */
final class SpecialDay extends Model
{
    use SoftDeletes;

    public const SOURCE_FIXED  = 'fixed';
    public const SOURCE_RULE   = 'rule';
    public const SOURCE_AI     = 'ai';
    public const SOURCE_MANUAL = 'manual';

    public const CATEGORY_ULUSAL   = 'ulusal';
    public const CATEGORY_DINI     = 'dini';
    public const CATEGORY_OZEL     = 'ozel';
    public const CATEGORY_SEZONSAL = 'sezonsal';
    public const CATEGORY_GENEL    = 'genel';

    /** @var list<string> */
    public const CATEGORIES = [
        self::CATEGORY_ULUSAL,
        self::CATEGORY_DINI,
        self::CATEGORY_OZEL,
        self::CATEGORY_SEZONSAL,
        self::CATEGORY_GENEL,
    ];

    /** @var list<string> */
    public const SOURCES = [
        self::SOURCE_FIXED,
        self::SOURCE_RULE,
        self::SOURCE_AI,
        self::SOURCE_MANUAL,
    ];

    protected $fillable = [
        'date',
        'name',
        'slug',
        'category',
        'theme',
        'source',
        'is_movable',
        'notes',
        'verified_at',
        'verified_by',
    ];

    protected function casts(): array
    {
        return [
            'date'        => 'date',
            'is_movable'  => 'boolean',
            'verified_at' => 'datetime',
            'verified_by' => 'integer',
        ];
    }

    /** @return HasMany<SpecialDayImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(SpecialDayImage::class);
    }

    /** @return HasMany<VertexBatch, $this> */
    public function vertexBatches(): HasMany
    {
        return $this->hasMany(VertexBatch::class);
    }

    /** @return BelongsTo<User, $this> */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /** @param Builder<SpecialDay> $query */
    public function scopeInRange(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return $query->whereBetween('date', [$from->toDateString(), $to->toDateString()]);
    }

    /** @param Builder<SpecialDay> $query */
    public function scopeOfCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /** @param Builder<SpecialDay> $query */
    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->whereYear('date', $year);
    }

    public function categoryLabel(): string
    {
        return match ($this->category) {
            self::CATEGORY_ULUSAL   => 'Ulusal',
            self::CATEGORY_DINI     => 'Dini',
            self::CATEGORY_OZEL     => 'Özel',
            self::CATEGORY_SEZONSAL => 'Sezonsal',
            default                 => 'Genel',
        };
    }

    public function categoryBadgeClass(): string
    {
        return match ($this->category) {
            self::CATEGORY_ULUSAL   => 'badge-ulusal',
            self::CATEGORY_DINI     => 'badge-dini',
            self::CATEGORY_OZEL     => 'badge-ozel',
            self::CATEGORY_SEZONSAL => 'badge-sezonsal',
            default                 => 'badge-genel',
        };
    }

    public function sourceLabel(): string
    {
        return match ($this->source) {
            self::SOURCE_FIXED  => 'Sabit',
            self::SOURCE_RULE   => 'Kural',
            self::SOURCE_AI     => 'AI',
            self::SOURCE_MANUAL => 'Manuel',
            default             => $this->source,
        };
    }

    public function turkishWeekday(): string
    {
        static $map = [
            'Monday'    => 'Pazartesi',
            'Tuesday'   => 'Salı',
            'Wednesday' => 'Çarşamba',
            'Thursday'  => 'Perşembe',
            'Friday'    => 'Cuma',
            'Saturday'  => 'Cumartesi',
            'Sunday'    => 'Pazar',
        ];

        return $map[$this->date->format('l')] ?? '';
    }

    public function imageCount(string $mediaType): int
    {
        return $this->images()->where('media_type', $mediaType)->count();
    }
}
