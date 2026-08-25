<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GalleryType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasTranslations;

class GalleryItem extends Model
{
    use HasTranslations, HasFactory, SoftDeletes;

    protected $fillable = [
        'locale',
        'lang_group_id',
        'title',
        'description',
        'image',
        'type',
        'gallery_category_id',
        'video_url',
        'duration',
        'view_count',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type'                => GalleryType::class,
            'gallery_category_id' => 'integer',
            'view_count'          => 'integer',
            'sort_order'          => 'integer',
            'is_active'           => 'boolean',
        ];
    }

    // ── Relationships ──

    /**
     * @return BelongsTo<GalleryCategory, $this>
     */
    public function galleryCategory(): BelongsTo
    {
        return $this->belongsTo(GalleryCategory::class);
    }

    // ── Scopes ──

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
        return $query->orderBy('sort_order');
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopePhotos(Builder $query): Builder
    {
        return $query->where('type', GalleryType::Photo);
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeVideos(Builder $query): Builder
    {
        return $query->where('type', GalleryType::Video);
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeOfCategory(Builder $query, int $categoryId): Builder
    {
        return $query->where('gallery_category_id', $categoryId);
    }
}
