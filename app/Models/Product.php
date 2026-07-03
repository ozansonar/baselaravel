<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductStatus;
use App\Traits\HasSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, HasSlug, SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'short_description',
        'description',
        'price',
        'discounted_price',
        'stock',
        'unit',
        'sku',
        'status',
        'is_featured',
        'is_new',
        'allow_reviews',
        'show_related',
        'image',
        'sort_order',
        'meta_title',
        'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'price' => 'decimal:2',
            'discounted_price' => 'decimal:2',
            'stock' => 'integer',
            'is_featured' => 'boolean',
            'is_new' => 'boolean',
            'allow_reviews' => 'boolean',
            'show_related' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    // ── Relationships ──

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<ProductNutrition, $this>
     */
    public function nutritions(): HasMany
    {
        return $this->hasMany(ProductNutrition::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<ProductReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class)->latest();
    }

    /**
     * @return HasMany<ProductReview, $this>
     */
    public function approvedReviews(): HasMany
    {
        return $this->hasMany(ProductReview::class)->approved()->latest();
    }

    /**
     * @return HasMany<ProductShippingItem, $this>
     */
    public function shippingItems(): HasMany
    {
        return $this->hasMany(ProductShippingItem::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<ProductFeature, $this>
     */
    public function features(): HasMany
    {
        return $this->hasMany(ProductFeature::class)->orderBy('sort_order');
    }

    /**
     * Topic Cluster (Modül 4) — bu ürün etrafındaki blog konu kümesi.
     *
     * @return HasMany<ProductTopic, $this>
     */
    public function topics(): HasMany
    {
        return $this->hasMany(ProductTopic::class)->orderBy('sort_order');
    }

    // ── Scopes ──

    /**
     * @param \Illuminate\Database\Eloquent\Builder<static> $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', ProductStatus::Active);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<static> $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeFeatured(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<static> $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeSorted(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->orderBy('sort_order');
    }

    // ── Accessors ──

    /**
     * Get cover image: product image or first gallery image.
     */
    public function getCoverImageAttribute(): ?string
    {
        if ($this->image) {
            return $this->image;
        }

        return $this->images->first()?->image;
    }

    // ── Helpers ──

    public function currentPrice(): float
    {
        return (float) $this->price;
    }

    public function hasDiscount(): bool
    {
        return $this->discounted_price !== null && $this->discounted_price > $this->price;
    }

    public function inStock(): bool
    {
        return $this->stock > 0;
    }
}
