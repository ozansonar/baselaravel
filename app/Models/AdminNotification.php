<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Admin paneli içi bildirim — header bell icon + bildirim merkezi sayfası.
 *
 * NotificationCenter::send() ile üretilir. Telegram'a giden bildirimler
 * aynı zamanda buraya da düşer.
 */
final class AdminNotification extends Model
{
    use HasFactory, SoftDeletes;

    public $timestamps = false; // sadece created_at + read_at

    protected $fillable = [
        'user_id',
        'type',
        'level',
        'title',
        'message',
        'icon',
        'action_url',
        'read_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'level'      => NotificationLevel::class,
            'user_id'    => 'integer',
            'read_at'    => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param Builder<AdminNotification> $query */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /** @param Builder<AdminNotification> $query */
    public function scopeForUser(Builder $query, ?int $userId): Builder
    {
        if ($userId === null) {
            return $query->whereNull('user_id');
        }
        // user_id NULL = tüm admin'lere broadcast, user_id set = belirli admin
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)->orWhereNull('user_id');
        });
    }

    public function levelBadgeClass(): string
    {
        $color = $this->level?->color() ?? 'secondary';

        return $color === 'warning' ? 'bg-warning text-dark' : 'bg-' . $color;
    }

    public function levelIcon(): string
    {
        return $this->icon ?? $this->level?->icon() ?? 'bi-bell-fill';
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    /**
     * Listedeki gün başlığı: "Bugün", "Dün" ya da tarihin kendisi.
     *
     * Bildirimler zaman sırasına göre okunuyor; gün başlıkları olmadan uzun
     * liste tek yığın hâline geliyor.
     */
    public function dayGroupLabel(): string
    {
        $created = $this->created_at;

        if ($created === null) {
            return 'Tarihsiz';
        }

        return match (true) {
            $created->isToday()     => 'Bugün',
            $created->isYesterday() => 'Dün',
            $created->isCurrentYear() => $created->translatedFormat('d F'),
            default                 => $created->translatedFormat('d F Y'),
        };
    }

    /**
     * Bildirimi üreten olayın okunabilir adı — kart üzerindeki tür etiketi.
     */
    public function typeLabel(): string
    {
        return match ($this->type) {
            'backup_completed', 'backup_failed' => 'Yedekleme',
            'health_critical'                   => 'Sistem Sağlığı',
            'post_failed'                       => 'İçerik',
            'recycle_warning'                   => 'Geri Dönüşüm',
            'critical'                          => 'Sistem',
            default                             => Str::headline((string) $this->type),
        };
    }

    /**
     * Tür etiketinin tema sınıfı; tema renkleri olay ailesine göre veriyor.
     */
    public function typeTagVariant(): string
    {
        return match ($this->type) {
            'backup_completed', 'backup_failed' => 'system',
            'health_critical', 'critical'       => 'security',
            'post_failed'                       => 'content',
            'recycle_warning'                   => 'update',
            default                             => 'system',
        };
    }
}
