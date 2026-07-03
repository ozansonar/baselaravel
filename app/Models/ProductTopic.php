<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductTopic extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'title',
        'search_intent',
        'notes',
        'blog_post_id',
        'converted_at',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'converted_at' => 'datetime',
            'is_active'    => 'boolean',
            'sort_order'   => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<BlogPost, $this>
     */
    public function blogPost(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class);
    }

    // ── Scopes ──

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Henüz blog yazısına çevrilmemiş topic'ler.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeUnconverted(Builder $query): Builder
    {
        return $query->whereNull('blog_post_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSorted(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    // ── Helpers ──

    public function isConverted(): bool
    {
        return $this->blog_post_id !== null;
    }
}
