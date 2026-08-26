<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommentStatus;
use App\Enums\ContentStatus;
use App\Traits\HasSlug;
use App\Traits\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BlogPost extends Model
{
    use HasTranslations, HasFactory, HasSlug, SoftDeletes;

    protected static function slugSource(): string
    {
        return 'title';
    }

    protected $fillable = [
        'locale',
        'lang_group_id',
        'blog_category_id',
        'user_id',
        'title',
        'slug',
        'excerpt',
        'body',
        'image',
        'meta_title',
        'meta_description',
        'status',
        'published_at',
        'views',
    ];

    protected function casts(): array
    {
        return [
            'blog_category_id' => 'integer',
            'status'           => ContentStatus::class,
            'published_at'     => 'datetime',
            'views'            => 'integer',
        ];
    }

    // ── Relationships ──

    /**
     * @return BelongsTo<BlogCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<BlogComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(BlogComment::class);
    }

    /**
     * @return HasMany<BlogComment, $this>
     */
    public function approvedComments(): HasMany
    {
        return $this->hasMany(BlogComment::class)
            ->where('status', CommentStatus::Approved)
            ->whereNull('parent_id')
            ->oldest();
    }

    // ── Scopes ──

    /**
     * @param \Illuminate\Database\Eloquent\Builder<static> $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopePublished(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', ContentStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<static> $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeRecent(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->orderByDesc('published_at');
    }
}
