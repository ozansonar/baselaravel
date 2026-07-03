<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Bir özel güne ait görsel galerisi.
 *
 * Akıllı seçim: bulk import sırasında en az kullanılan + en eski kullanılmış
 * görsel öncelikli seçilir. Tüm görseller en az 1 kez kullanılmışsa recycle
 * başlar — bu durumda Telegram'a "yeni görsel ekle" bildirimi gider.
 */
final class SpecialDayImage extends Model
{
    use SoftDeletes;

    public const MEDIA_FEED  = 'feed';
    public const MEDIA_REELS = 'reels';
    public const MEDIA_STORY = 'story';

    /** @var list<string> */
    public const MEDIA_TYPES = [
        self::MEDIA_FEED,
        self::MEDIA_REELS,
        self::MEDIA_STORY,
    ];

    protected $fillable = [
        'special_day_id',
        'image_path',
        'media_type',
        'usage_count',
        'last_used_at',
        'sort_order',
        'alt_text',
    ];

    protected function casts(): array
    {
        return [
            'special_day_id' => 'integer',
            'usage_count'    => 'integer',
            'last_used_at'   => 'datetime',
            'sort_order'     => 'integer',
        ];
    }

    /** @return BelongsTo<SpecialDay, $this> */
    public function specialDay(): BelongsTo
    {
        return $this->belongsTo(SpecialDay::class);
    }

    /** @param Builder<SpecialDayImage> $query */
    public function scopeOfMediaType(Builder $query, string $type): Builder
    {
        return $query->where('media_type', $type);
    }

    /** @param Builder<SpecialDayImage> $query — least-used + en eski kullanılan önce */
    public function scopeLeastUsed(Builder $query): Builder
    {
        return $query
            ->orderBy('usage_count', 'asc')
            ->orderByRaw('last_used_at IS NULL DESC') // null önce
            ->orderBy('last_used_at', 'asc')
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc');
    }

    public function mediaTypeLabel(): string
    {
        return match ($this->media_type) {
            self::MEDIA_FEED  => 'Feed Post',
            self::MEDIA_REELS => 'Reels',
            self::MEDIA_STORY => 'Story',
            default           => $this->media_type,
        };
    }

    public function mediaTypeBadgeClass(): string
    {
        return match ($this->media_type) {
            self::MEDIA_FEED  => 'badge-media-feed',
            self::MEDIA_REELS => 'badge-media-reels',
            self::MEDIA_STORY => 'badge-media-story',
            default           => '',
        };
    }
}
