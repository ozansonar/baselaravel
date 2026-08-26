<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CampaignAudience;
use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campaign extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'subject',
        'body',
        'from_name',
        'from_email',
        'reply_to',
        'locale',
        'audience',
        'audience_filter',
        'status',
        'throttled',
        'scheduled_at',
        'started_at',
        'completed_at',
        'total_recipients',
        'sent_count',
        'failed_count',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'audience'         => CampaignAudience::class,
            'status'           => CampaignStatus::class,
            'audience_filter'  => 'array',
            'throttled'        => 'boolean',
            'scheduled_at'     => 'datetime',
            'started_at'       => 'datetime',
            'completed_at'     => 'datetime',
            'total_recipients' => 'integer',
            'sent_count'       => 'integer',
            'failed_count'     => 'integer',
        ];
    }

    /**
     * @return HasMany<CampaignRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    /**
     * @return HasMany<CampaignAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(CampaignAttachment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeDispatchable(Builder $query): Builder
    {
        return $query->whereIn('status', [CampaignStatus::Scheduled, CampaignStatus::Sending]);
    }

    public function pendingCount(): int
    {
        return $this->recipients()
            ->where('status', CampaignRecipientStatus::Pending)
            ->count();
    }

    /**
     * How far along the send is, for the progress bar.
     */
    public function progress(): int
    {
        if ($this->total_recipients < 1) {
            return 0;
        }

        $done = $this->sent_count + $this->failed_count;

        return (int) min(100, round($done / $this->total_recipients * 100));
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    /**
     * The address a campaign goes out from, falling back to the mail config.
     */
    public function senderAddress(): string
    {
        return $this->from_email ?: (string) config('mail.from.address');
    }

    public function senderName(): string
    {
        return $this->from_name ?: (string) Setting::getValue('site_name', config('app.name'));
    }
}
