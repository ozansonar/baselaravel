<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CampaignType;
use App\Traits\HasSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campaign extends Model
{
    use HasFactory, HasSlug, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'description',
        'discount_value',
        'discount_type',
        'min_order_amount',
        'code',
        'start_date',
        'end_date',
        'is_active',
        'usage_limit',
        'used_count',
    ];

    protected function casts(): array
    {
        return [
            'type' => CampaignType::class,
            'discount_value' => 'decimal:2',
            'min_order_amount' => 'decimal:2',
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'is_active' => 'boolean',
            'usage_limit' => 'integer',
            'used_count' => 'integer',
        ];
    }

    // ── Scopes ──

    /**
     * @param \Illuminate\Database\Eloquent\Builder<static> $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_active', true)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now());
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<static> $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeByCode(\Illuminate\Database\Eloquent\Builder $query, string $code): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('code', $code);
    }

    // ── Helpers ──

    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now();

        if ($now->lt($this->start_date) || $now->gt($this->end_date)) {
            return false;
        }

        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    public function isPercentage(): bool
    {
        return $this->discount_type === 'percentage';
    }
}
