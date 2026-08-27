<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CampaignRecipientStatus;
use App\Support\PersonName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CampaignRecipient extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'campaign_id',
        'email',
        'first_name',
        'last_name',
        'locale',
        'status',
        'unsubscribe_token',
        'attempts',
        'sent_at',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'status'   => CampaignRecipientStatus::class,
            'attempts' => 'integer',
            'sent_at'  => 'datetime',
        ];
    }

    /**
     * Gösterim için birleşik isim; ad ve soyad ayrı sütunlarda tutuluyor.
     */
    public function getFullNameAttribute(): ?string
    {
        return PersonName::full($this->first_name, $this->last_name);
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', CampaignRecipientStatus::Pending);
    }
}
