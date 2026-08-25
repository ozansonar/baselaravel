<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MailLogStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MailLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'to',
        'cc',
        'bcc',
        'from',
        'reply_to',
        'subject',
        'body',
        'mailable_class',
        'status',
        'error_message',
        'sent_at',
        'metadata',
        'ip_address',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'status'   => MailLogStatus::class,
            'sent_at'  => 'datetime',
            'metadata' => 'array',
        ];
    }

    /* -------------------- Relations -------------------- */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /* -------------------- Scopes -------------------- */

    public function scopeByStatus(Builder $query, MailLogStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeRecent(Builder $query): Builder
    {
        return $query->orderByDesc('created_at');
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $q) use ($term): void {
            $q->where('to', 'like', "%{$term}%")
              ->orWhere('subject', 'like', "%{$term}%")
              ->orWhere('mailable_class', 'like', "%{$term}%");
        });
    }

    /* -------------------- Helpers -------------------- */

    /**
     * Mailable class → human-readable Turkish label map.
     */
    private const MAILABLE_LABELS = [
        'ContactMessageNotification' => ['label' => 'İletişim Formu', 'icon' => 'bi-chat-dots-fill', 'color' => 'text-info'],
        'WelcomeMail'                => ['label' => 'Hoş Geldiniz', 'icon' => 'bi-person-plus-fill', 'color' => 'text-success'],
        'ResetPasswordMail'          => ['label' => 'Şifre Sıfırlama', 'icon' => 'bi-key-fill', 'color' => 'text-warning'],
        'ContactMessageReplyMail'    => ['label' => 'İletişim Yanıtı', 'icon' => 'bi-reply-fill', 'color' => 'text-neon-green'],
        'TestMail'                   => ['label' => 'Test Maili', 'icon' => 'bi-wrench-adjustable', 'color' => 'text-secondary'],
    ];

    public function getShortMailableAttribute(): string
    {
        if (!$this->mailable_class) {
            return 'Raw Mail';
        }

        return class_basename($this->mailable_class);
    }

    public function getMailableLabelAttribute(): string
    {
        $basename = $this->short_mailable;

        return self::MAILABLE_LABELS[$basename]['label'] ?? $basename;
    }

    public function getMailableIconAttribute(): string
    {
        $basename = $this->short_mailable;

        return self::MAILABLE_LABELS[$basename]['icon'] ?? 'bi-envelope';
    }

    public function getMailableColorAttribute(): string
    {
        $basename = $this->short_mailable;

        return self::MAILABLE_LABELS[$basename]['color'] ?? 'text-secondary';
    }
}
