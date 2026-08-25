<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PopupSize;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasTranslations;

class Popup extends Model
{
    use HasTranslations, HasFactory, SoftDeletes;

    protected $fillable = [
        'locale',
        'lang_group_id',
        'title',
        'description',
        'image',
        'button_text',
        'button_url',
        'size',
        'pages',
        'start_date',
        'end_date',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'size'       => PopupSize::class,
            'pages'      => 'array',
            'start_date' => 'date',
            'end_date'   => 'date',
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    // ── Scopes ──

    /**
     * @param \Illuminate\Database\Eloquent\Builder<static> $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<static> $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeScheduled(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        $today = now()->toDateString();

        return $query->where(function ($q) use ($today): void {
            $q->whereNull('start_date')->orWhere('start_date', '<=', $today);
        })->where(function ($q) use ($today): void {
            $q->whereNull('end_date')->orWhere('end_date', '>=', $today);
        });
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<static> $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeSorted(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->orderBy('sort_order')->orderByDesc('created_at');
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<static> $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForPage(\Illuminate\Database\Eloquent\Builder $query, string $page): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where(function ($q) use ($page): void {
            $q->whereJsonContains('pages', 'all')
                ->orWhereJsonContains('pages', $page);
        });
    }
}
