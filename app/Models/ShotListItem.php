<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * AI Çek Listesi — fotoğraf/video çekimi için yapılacaklar listesi.
 *
 * Gap analizden gelen AI önerileri buraya kalıcı kayıtlanır → kullanıcı
 * "yapıldı" işaretler, kalan görevleri takip eder.
 */
final class ShotListItem extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING   = 'pending';
    public const STATUS_PLANNED   = 'planned';
    public const STATUS_DONE      = 'done';
    public const STATUS_CANCELLED = 'cancelled';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PLANNED,
        self::STATUS_DONE,
        self::STATUS_CANCELLED,
    ];

    public const PRIORITY_LOW    = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH   = 'high';

    /** @var list<string> */
    public const PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_NORMAL,
        self::PRIORITY_HIGH,
    ];

    protected $fillable = [
        'topic',
        'media_type',
        'theme',
        'notes',
        'status',
        'priority',
        'done_at',
        'done_by',
        'ai_generated',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'done_at'      => 'datetime',
            'ai_generated' => 'boolean',
            'created_by'   => 'integer',
            'done_by'      => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function doer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by');
    }

    /** @param Builder<ShotListItem> $query */
    public function scopeOfStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /** @param Builder<ShotListItem> $query — pending önce, sonra planned, done en sonda */
    public function scopeByPriority(Builder $query): Builder
    {
        return $query
            ->orderByRaw("FIELD(status, 'pending', 'planned', 'done', 'cancelled')")
            ->orderByRaw("FIELD(priority, 'high', 'normal', 'low')")
            ->orderBy('created_at', 'desc');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING   => 'Bekliyor',
            self::STATUS_PLANNED   => 'Planlandı',
            self::STATUS_DONE      => 'Yapıldı',
            self::STATUS_CANCELLED => 'İptal',
            default                => $this->status,
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING   => 'bg-warning text-dark',
            self::STATUS_PLANNED   => 'bg-info',
            self::STATUS_DONE      => 'bg-success',
            self::STATUS_CANCELLED => 'bg-secondary',
            default                => 'bg-secondary',
        };
    }

    public function priorityLabel(): string
    {
        return match ($this->priority) {
            self::PRIORITY_HIGH   => 'Yüksek',
            self::PRIORITY_NORMAL => 'Normal',
            self::PRIORITY_LOW    => 'Düşük',
            default               => $this->priority,
        };
    }

    public function priorityBadgeClass(): string
    {
        return match ($this->priority) {
            self::PRIORITY_HIGH   => 'bg-danger',
            self::PRIORITY_NORMAL => 'bg-secondary',
            self::PRIORITY_LOW    => 'bg-light text-dark',
            default               => 'bg-secondary',
        };
    }
}
