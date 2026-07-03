<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class YouTubeVideo extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'youtube_videos';

    protected $fillable = [
        'video_id',
        'title',
        'description',
        'thumbnail_url',
        'published_at',
        'duration',
        'view_count',
        'like_count',
        'is_visible',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'view_count'   => 'integer',
            'like_count'   => 'integer',
            'is_visible'   => 'boolean',
            'sort_order'   => 'integer',
        ];
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    public function getEmbedUrlAttribute(): string
    {
        return "https://www.youtube.com/embed/{$this->video_id}";
    }

    public function getWatchUrlAttribute(): string
    {
        return "https://www.youtube.com/watch?v={$this->video_id}";
    }

    public function getFormattedDurationAttribute(): string
    {
        if (empty($this->duration)) {
            return '';
        }

        // ISO 8601 duration: PT1H2M3S → 1:02:03
        $interval = new \DateInterval($this->duration);
        $hours = $interval->h;
        $minutes = $interval->i;
        $seconds = $interval->s;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    public function getFormattedViewCountAttribute(): string
    {
        if ($this->view_count >= 1000000) {
            return number_format($this->view_count / 1000000, 1) . 'M';
        }

        if ($this->view_count >= 1000) {
            return number_format($this->view_count / 1000, 1) . 'B';
        }

        return number_format($this->view_count);
    }
}
