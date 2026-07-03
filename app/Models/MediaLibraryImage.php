<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Genel görsel kütüphanesi — bulk Instagram import için önceden hazırlanmış,
 * kontrolden geçmiş görsellerin tag/tema/mood etiketleriyle saklandığı havuz.
 *
 * Akıllı seçim mantığı: Excel "konu" sütunu → tag/tema/mood eşleşmesi →
 * least-used görsel seçimi (SpecialDayImage ile aynı pattern).
 *
 * AI üretim maliyetini sıfırlar — kütüphanedeki görseller arası rotasyon yapar,
 * tükendiğinde Telegram'a "yeni görsel ekle" bildirimi atar.
 */
final class MediaLibraryImage extends Model
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

    public const THEME_PRODUCTS  = 'products';   // Süt, peynir, tereyağı, bal vb. ürün tanıtımları
    public const THEME_FARM      = 'farm';       // Çiftlik hayatı, manzara, hayvanlar, sabah/akşam
    public const THEME_RECIPES   = 'recipes';    // Tarif, yemek, sunum
    public const THEME_SEASONAL  = 'seasonal';   // Mevsimsel — bahar otları, kış tarhanası
    public const THEME_HOLIDAYS  = 'holidays';   // Bayram, kandil, milli gün
    public const THEME_NATURE    = 'nature';     // Doğa, kır, çiçekler
    public const THEME_LIFESTYLE = 'lifestyle';  // Aile, kahvaltı sofrası, paylaşım

    /** @var list<string> */
    public const THEMES = [
        self::THEME_PRODUCTS,
        self::THEME_FARM,
        self::THEME_RECIPES,
        self::THEME_SEASONAL,
        self::THEME_HOLIDAYS,
        self::THEME_NATURE,
        self::THEME_LIFESTYLE,
    ];

    public const MOOD_SAMIMI       = 'samimi';
    public const MOOD_PROFESYONEL  = 'profesyonel';
    public const MOOD_EGLENCELI    = 'eglenceli';
    public const MOOD_NOSTALJIK    = 'nostaljik';

    /** @var list<string> */
    public const MOODS = [
        self::MOOD_SAMIMI,
        self::MOOD_PROFESYONEL,
        self::MOOD_EGLENCELI,
        self::MOOD_NOSTALJIK,
    ];

    public const SEASON_ILKBAHAR = 'ilkbahar';
    public const SEASON_YAZ      = 'yaz';
    public const SEASON_SONBAHAR = 'sonbahar';
    public const SEASON_KIS      = 'kis';

    /** @var list<string> */
    public const SEASONS = [
        self::SEASON_ILKBAHAR,
        self::SEASON_YAZ,
        self::SEASON_SONBAHAR,
        self::SEASON_KIS,
    ];

    public const VISIBILITY_PRIVATE = 'private';
    public const VISIBILITY_PUBLIC  = 'public';

    /** @var list<string> */
    public const VISIBILITIES = [
        self::VISIBILITY_PRIVATE,
        self::VISIBILITY_PUBLIC,
    ];

    protected $fillable = [
        'image_path',
        'video_path',
        'video_duration_seconds',
        'is_video',
        'media_type',
        'tags',
        'theme',
        'mood',
        'season',
        'description',
        'ai_generated_tags',
        'usage_count',
        'last_used_at',
        'sort_order',
        'alt_text',
        'visibility',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tags'                   => 'array',
            'ai_generated_tags'      => 'boolean',
            'is_video'               => 'boolean',
            'video_duration_seconds' => 'integer',
            'usage_count'            => 'integer',
            'last_used_at'           => 'datetime',
            'sort_order'             => 'integer',
            'created_by'             => 'integer',
        ];
    }

    /** Video mü görsel mi formatta tüketim için */
    public function previewUrl(string $size = 'md'): string
    {
        // Video poster (image_path) her durumda var — video'larda da poster çıkartılır
        return upload_url($this->image_path, $size);
    }

    /** Bulk import sırasında kullanılacak ana medya path'i */
    public function primaryPath(): string
    {
        return $this->is_video && $this->video_path ? (string) $this->video_path : (string) $this->image_path;
    }

    public function durationLabel(): ?string
    {
        if (! $this->is_video || ! $this->video_duration_seconds) return null;
        $sec = $this->video_duration_seconds;
        $m = (int) floor($sec / 60);
        $s = $sec % 60;
        return $m > 0 ? sprintf('%d:%02d', $m, $s) : $s . 'sn';
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ─── Scopes ──────────────────────────────────────────────

    /** @param Builder<MediaLibraryImage> $query */
    public function scopeOfMediaType(Builder $query, string $type): Builder
    {
        return $query->where('media_type', $type);
    }

    /** @param Builder<MediaLibraryImage> $query */
    public function scopeOfTheme(Builder $query, string $theme): Builder
    {
        return $query->where('theme', $theme);
    }

    /** @param Builder<MediaLibraryImage> $query */
    public function scopeOfMood(Builder $query, string $mood): Builder
    {
        return $query->where('mood', $mood);
    }

    /** @param Builder<MediaLibraryImage> $query */
    public function scopeOfSeason(Builder $query, string $season): Builder
    {
        return $query->where('season', $season);
    }

    /** @param Builder<MediaLibraryImage> $query — least-used + en eski kullanılan önce */
    public function scopeLeastUsed(Builder $query): Builder
    {
        return $query
            ->orderBy('usage_count', 'asc')
            ->orderByRaw('last_used_at IS NULL DESC')
            ->orderBy('last_used_at', 'asc')
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc');
    }

    /** @param Builder<MediaLibraryImage> $query */
    public function scopeNeverUsed(Builder $query): Builder
    {
        return $query->where('usage_count', 0);
    }

    /**
     * Tag listesinde verilen kelimelerden herhangi biri varsa eşleşir.
     * MySQL JSON_OVERLAPS / JSON_CONTAINS kullanır.
     *
     * @param Builder<MediaLibraryImage> $query
     * @param list<string> $keywords
     */
    public function scopeWithAnyTag(Builder $query, array $keywords): Builder
    {
        if ($keywords === []) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($keywords): void {
            foreach ($keywords as $kw) {
                $kw = mb_strtolower(trim($kw));
                if ($kw === '') continue;
                // JSON_SEARCH case-insensitive değil — JSON'u text olarak ara (LOWER + LIKE)
                $q->orWhereRaw('LOWER(JSON_UNQUOTE(tags)) LIKE ?', ['%"' . str_replace('"', '\\"', $kw) . '"%']);
            }
        });
    }

    // ─── Helpers ─────────────────────────────────────────────

    public function mediaTypeLabel(): string
    {
        return match ($this->media_type) {
            self::MEDIA_FEED  => 'Feed Post',
            self::MEDIA_REELS => 'Reels',
            self::MEDIA_STORY => 'Story',
            default           => $this->media_type,
        };
    }

    public function themeLabel(): string
    {
        return match ($this->theme) {
            self::THEME_PRODUCTS  => 'Ürünler',
            self::THEME_FARM      => 'Çiftlik Hayatı',
            self::THEME_RECIPES   => 'Tarifler',
            self::THEME_SEASONAL  => 'Mevsimsel',
            self::THEME_HOLIDAYS  => 'Bayram & Özel Gün',
            self::THEME_NATURE    => 'Doğa',
            self::THEME_LIFESTYLE => 'Yaşam Tarzı',
            default               => $this->theme ?? '—',
        };
    }

    public function moodLabel(): string
    {
        return match ($this->mood) {
            self::MOOD_SAMIMI      => 'Samimi',
            self::MOOD_PROFESYONEL => 'Profesyonel',
            self::MOOD_EGLENCELI   => 'Eğlenceli',
            self::MOOD_NOSTALJIK   => 'Nostaljik',
            default                => $this->mood ?? '—',
        };
    }

    public function visibilityLabel(): string
    {
        return $this->visibility === self::VISIBILITY_PUBLIC ? 'Halka Açık' : 'Sadece Bulk';
    }

    public function seasonLabel(): string
    {
        return match ($this->season) {
            self::SEASON_ILKBAHAR => 'İlkbahar 🌸',
            self::SEASON_YAZ      => 'Yaz ☀️',
            self::SEASON_SONBAHAR => 'Sonbahar 🍂',
            self::SEASON_KIS      => 'Kış ❄️',
            default               => $this->season ?? '—',
        };
    }

    /** @return list<string> */
    public function tagList(): array
    {
        $tags = $this->tags;
        if (! is_array($tags)) {
            return [];
        }
        return array_values(array_filter(array_map('strval', $tags), static fn ($t) => trim($t) !== ''));
    }

    public function tagListString(): string
    {
        return implode(', ', $this->tagList());
    }
}
