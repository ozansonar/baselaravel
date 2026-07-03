<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class HashtagPool extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'hashtag_pool';

    public const string CATEGORY_GENERAL  = 'genel';
    public const string CATEGORY_PRODUCT  = 'urun';
    public const string CATEGORY_LOCATION = 'lokasyon';
    public const string CATEGORY_TREND    = 'trend';
    public const string CATEGORY_SEASONAL = 'sezon';

    protected $fillable = [
        'tag',
        'category',
        'usage_count',
        'last_used_at',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'usage_count'  => 'integer',
            'last_used_at' => 'datetime',
            'is_active'    => 'boolean',
            'sort_order'   => 'integer',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeMostUsed(Builder $query): Builder
    {
        return $query->orderByDesc('usage_count');
    }

    /**
     * Kullanım sayacını artır.
     */
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Tag adını normalize et — # ve trim, lowercase.
     */
    public static function normalize(string $raw): string
    {
        return mb_strtolower(trim($raw, " \t\n\r\0\x0B#"));
    }

    /**
     * @return array<int, string>
     */
    public static function categoryLabels(): array
    {
        return [
            self::CATEGORY_GENERAL  => 'Genel',
            self::CATEGORY_PRODUCT  => 'Ürün',
            self::CATEGORY_LOCATION => 'Lokasyon',
            self::CATEGORY_TREND    => 'Trend',
            self::CATEGORY_SEASONAL => 'Sezon',
        ];
    }
}
