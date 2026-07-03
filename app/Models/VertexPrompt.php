<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class VertexPrompt extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'tags',
        'ig_caption_template',
        'ig_title_template',
        'ig_hashtags_template',
        'prompt',
        'reference_images',
        'random_options',
        'model',
        'aspect_ratio',
        'image_size',
        'mime_type',
        'temperature',
        'max_output_tokens',
        'top_p',
        'thinking_level',
        'person_generation',
        'safety_off',
        'sort_order',
        'is_active',
        'is_pinned',
    ];

    protected function casts(): array
    {
        return [
            'tags'              => 'array',
            'reference_images'  => 'array',
            'random_options'    => 'array',
            'temperature'       => 'decimal:2',
            'top_p'             => 'decimal:2',
            'max_output_tokens' => 'integer',
            'safety_off'        => 'boolean',
            'is_active'         => 'boolean',
            'is_pinned'         => 'boolean',
            'sort_order'        => 'integer',
        ];
    }

    /**
     * @param Builder<static> $query
     */
    public function scopeWithTag(Builder $query, string $tag): Builder
    {
        return $query->whereJsonContains('tags', $tag);
    }

    /**
     * @return HasMany<VertexGeneration>
     */
    public function generations(): HasMany
    {
        return $this->hasMany(VertexGeneration::class, 'prompt_id');
    }

    /**
     * @return HasMany<VertexPromptVersion>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(VertexPromptVersion::class)->orderByDesc('version_number');
    }

    /**
     * @return HasOne<VertexPromptVersion>
     */
    public function latestVersion(): HasOne
    {
        return $this->hasOne(VertexPromptVersion::class)->orderByDesc('version_number');
    }

    public function snapshotData(): array
    {
        return collect($this->fillable)
            ->mapWithKeys(fn (string $key) => [$key => $this->getAttribute($key)])
            ->toArray();
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopePinned(Builder $query): Builder
    {
        return $query->where('is_pinned', true);
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
