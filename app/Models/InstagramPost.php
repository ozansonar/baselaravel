<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InstagramMediaType;
use App\Enums\InstagramPostStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InstagramPost extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'image_path',
        'video_path',
        'video_duration_seconds',
        'media_type',
        'caption',
        'hashtags',
        'scheduled_at',
        'published_at',
        'status',
        'ig_media_id',
        'ig_post_id',
        'permalink',
        'error_message',
        'retry_count',
        'notified_failed_at',
        'created_by',
        'blog_post_id',
        // Engagement metrics
        'like_count',
        'comments_count',
        'reach',
        'impressions',
        'saved_count',
        'metrics_fetched_at',
        // Facebook cross-post
        'publish_to_facebook',
        'fb_post_id',
        'fb_permalink',
        'fb_published_at',
        'fb_error_message',
        // TikTok cross-post — docs/tiktok.md Bölüm 3
        'publish_to_tiktok',
        'tt_post_id',
        'tt_permalink',
        'tt_published_at',
        'tt_error_message',
        'tt_retry_count',
        'tt_inbox_id',
        // Bulk import (toplu Excel paylaşım) izleme
        'bulk_import_id',
        // Vertex AI galeri paylaşımı
        'vertex_generation_id',
    ];

    protected function casts(): array
    {
        return [
            'status'                  => InstagramPostStatus::class,
            'media_type'              => InstagramMediaType::class,
            'video_duration_seconds'  => 'integer',
            'hashtags'                => 'array',
            'scheduled_at'            => 'datetime',
            'published_at'            => 'datetime',
            'retry_count'             => 'integer',
            'notified_failed_at'      => 'datetime',
            'like_count'              => 'integer',
            'comments_count'          => 'integer',
            'reach'                   => 'integer',
            'impressions'             => 'integer',
            'saved_count'             => 'integer',
            'metrics_fetched_at'      => 'datetime',
            'publish_to_facebook'     => 'boolean',
            'fb_published_at'         => 'datetime',
            // TikTok cross-post
            'publish_to_tiktok'       => 'boolean',
            'tt_published_at'         => 'datetime',
            'tt_retry_count'          => 'integer',
        ];
    }

    // ── Relations ──

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Otomatik paylaşım kaynağı blog yazısı (cron'dan geliyorsa). Manuel
     * oluşturulan postlarda null.
     */
    public function blogPost(): BelongsTo
    {
        return $this->belongsTo(\App\Models\BlogPost::class, 'blog_post_id');
    }

    /**
     * Bulk import (toplu Excel paylaşım) kaynağı — varsa bulk durum sayfasında
     * listelenir, yoksa manuel/diğer kaynak.
     */
    public function bulkImport(): BelongsTo
    {
        return $this->belongsTo(\App\Models\BulkImport::class, 'bulk_import_id');
    }

    public function vertexGeneration(): BelongsTo
    {
        return $this->belongsTo(\App\Models\VertexGeneration::class, 'vertex_generation_id');
    }

    /**
     * AI ile üretilmiş görsel kaydı (varsa) — bulk'ta gorsel_kaynagi=ai
     * sırasında AiGeneratedImage tablosuna satır yazılır + post id bağlanır.
     */
    public function aiGeneratedImage(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\AiGeneratedImage::class, 'instagram_post_id');
    }

    /**
     * @return HasMany<InstagramPostLog>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(InstagramPostLog::class)->orderByDesc('created_at');
    }

    /**
     * Carousel ek görseller (sıralı). Tek görsel postlar için boş olabilir.
     *
     * @return HasMany<InstagramPostImage>
     */
    public function additionalImages(): HasMany
    {
        return $this->hasMany(InstagramPostImage::class)->orderBy('sort_order');
    }

    public function isCarousel(): bool
    {
        return $this->additionalImages()->exists();
    }

    /**
     * Tüm görseller (ana + ek). Sıralı.
     *
     * @return list<string>
     */
    public function allImagePaths(): array
    {
        $paths = [];
        if ($this->image_path) {
            $paths[] = $this->image_path;
        }
        foreach ($this->additionalImages as $img) {
            $paths[] = $img->image_path;
        }

        return $paths;
    }

    // ── Scopes ──

    public const int MAX_RETRY_COUNT = 3;

    /**
     * Cron tarafından işlenecek postlar:
     *   - Status=Scheduled: zamanı gelmiş planlanmış postlar
     *   - Status=Failed VE retry_count < MAX_RETRY_COUNT: kalıcı hata sayılmamış başarısızlar
     *
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeDue(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->where(function (Builder $sub): void {
                $sub->where('status', InstagramPostStatus::Scheduled->value)
                    ->whereNotNull('scheduled_at')
                    ->where('scheduled_at', '<=', now());
            })->orWhere(function (Builder $sub): void {
                $sub->where('status', InstagramPostStatus::Failed->value)
                    ->where('retry_count', '<', self::MAX_RETRY_COUNT)
                    ->whereNotNull('scheduled_at')
                    ->where('scheduled_at', '<=', now());
            });
        });
    }

    public function isRetryExhausted(): bool
    {
        return $this->status === InstagramPostStatus::Failed
            && $this->retry_count >= self::MAX_RETRY_COUNT;
    }

    // ── TikTok cross-post helpers (docs/tiktok.md Bölüm 3.4) ──────────

    /**
     * TikTok cross-post başarılı mı?
     */
    public function isTikTokPublished(): bool
    {
        return $this->tt_post_id !== null && $this->tt_published_at !== null;
    }

    /**
     * TikTok cross-post bekleyen mi (publish_to_tiktok=true ama henüz yayınlanmamış)?
     */
    public function isTikTokPending(): bool
    {
        return $this->publish_to_tiktok
            && $this->status === InstagramPostStatus::Published
            && ! $this->isTikTokPublished()
            && $this->tt_retry_count < self::MAX_RETRY_COUNT;
    }

    /**
     * TikTok cross-post kalıcı hatada mı?
     */
    public function isTikTokFailed(): bool
    {
        return $this->publish_to_tiktok
            && $this->tt_retry_count >= self::MAX_RETRY_COUNT
            && ! $this->isTikTokPublished();
    }

    /**
     * Cron: IG yayınlanmış + TikTok cross-post bekleyen post'ları seç.
     *
     * Kullanım: docs/tiktok.md Bölüm 5.4
     *
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeTiktokRetryDue(Builder $query): Builder
    {
        return $query
            ->where('status', InstagramPostStatus::Published->value)
            ->where('publish_to_tiktok', true)
            ->whereNull('tt_post_id')
            ->whereNull('tt_inbox_id')
            ->where('tt_retry_count', '<', self::MAX_RETRY_COUNT);
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    // ── Helpers ──

    /**
     * Instagram caption hard-limit (Meta Graph API constraint).
     * 2200 + 1 char gönderirsek API "caption was too long" döndürür.
     */
    public const int IG_CAPTION_MAX = 2200;

    /**
     * Build the full caption (user caption + joined hashtags).
     *
     * Akıllı kırpma stratejisi (toplam 2200 char garantisi):
     *   1. Caption + "\n\n" + tüm hashtag'ler birleştirilir
     *   2. Toplam 2200'ü aşarsa önce hashtag'ler sondan ATILIR
     *      (caption'ı korumak öncelikli — anlam orada)
     *   3. Hashtag silmek yeterli değilse caption sondan kırpılır + "…"
     *   4. Sonuç hep <= 2200 char (Meta limit)
     */
    public function buildFullCaption(): string
    {
        $caption = trim((string) $this->caption);
        $tags = is_array($this->hashtags) ? $this->hashtags : [];

        $normalizedTags = array_values(array_filter(array_map(function ($tag): string {
            $tag = trim((string) $tag);
            if ($tag === '') {
                return '';
            }
            return str_starts_with($tag, '#') ? $tag : '#' . $tag;
        }, $tags)));

        // Hashtag yoksa sadece caption'ı limitli döndür
        if ($normalizedTags === []) {
            return $this->truncateCaption($caption, self::IG_CAPTION_MAX);
        }

        // 1) İlk deneme: caption + "\n\n" + tüm tag'ler
        $separator = "\n\n";
        $tagsStr = implode(' ', $normalizedTags);
        $combined = trim($caption . $separator . $tagsStr);

        if (mb_strlen($combined, 'UTF-8') <= self::IG_CAPTION_MAX) {
            return $combined;
        }

        // 2) Hashtag'leri sondan çıkararak yeniden dene (caption anlamlı, tag süs)
        while (count($normalizedTags) > 0) {
            array_pop($normalizedTags);
            $tagsStr = implode(' ', $normalizedTags);
            $combined = $normalizedTags === []
                ? trim($caption)
                : trim($caption . $separator . $tagsStr);

            if (mb_strlen($combined, 'UTF-8') <= self::IG_CAPTION_MAX) {
                return $combined;
            }
        }

        // 3) Tüm tag'ler atıldı, hala uzun → caption'ı kırp
        return $this->truncateCaption($caption, self::IG_CAPTION_MAX);
    }

    /**
     * Caption'ı verilen char limitine kelime sınırına saygılı kırp + "…".
     */
    private function truncateCaption(string $text, int $max): string
    {
        if (mb_strlen($text, 'UTF-8') <= $max) {
            return $text;
        }

        // 3 char "…" için yer ayır
        $cut = mb_substr($text, 0, $max - 1, 'UTF-8');

        // Son boşluğa geri sar (kelime ortasında kesme)
        $lastSpace = mb_strrpos($cut, ' ', 0, 'UTF-8');
        if ($lastSpace !== false && $lastSpace > $max * 0.8) {
            $cut = mb_substr($cut, 0, $lastSpace, 'UTF-8');
        }

        return rtrim($cut, " ,.;:-") . '…';
    }
}
