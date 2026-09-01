<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContentStatus;
use App\Traits\HasContentFiles;
use App\Traits\HasRevisions;
use App\Traits\HasSlug;
use App\Traits\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Page extends Model
{
    use HasTranslations, HasContentFiles, HasFactory, HasRevisions, HasSlug, SoftDeletes;

    protected $fillable = [
        'locale',
        'lang_group_id',
        'title',
        'slug',
        'content',
        'excerpt',
        'image',
        'status',
        'sort_order',
        'meta_title',
        'meta_description',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'sort_order' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    protected static function slugSource(): string
    {
        return 'title';
    }

    // ── Scopes ──

    /**
     * @param \Illuminate\Database\Eloquent\Builder<static> $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopePublished(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', ContentStatus::Published)
            ->where(function ($q) {
                $q->whereNull('published_at')
                  ->orWhere('published_at', '<=', now());
            });
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

    // ── Helpers ──
}
