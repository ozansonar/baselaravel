<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoogleReview extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'author_name',
        'author_photo_url',
        'author_profile_url',
        'text',
        'rating',
        'language',
        'relative_time',
        'review_time',
        'google_review_id',
        'is_visible',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'rating'      => 'integer',
            'is_visible'  => 'boolean',
            'sort_order'  => 'integer',
            'review_time' => 'datetime',
        ];
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<static> $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeVisible(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_visible', true);
    }

    public function getInitialsAttribute(): string
    {
        $words = explode(' ', $this->author_name);
        $initials = '';
        foreach (array_slice($words, 0, 2) as $word) {
            $initials .= mb_strtoupper(mb_substr($word, 0, 1));
        }

        return $initials;
    }
}
