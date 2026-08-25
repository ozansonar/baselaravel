<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Activity Log / Audit Trail kaydı.
 *
 * Kim ne zaman ne yaptı? — auditable observer veya AuditLogger::log() ile
 * otomatik üretilir. Soft delete YOK (denetim kaydı silinmemeli).
 */
final class AuditLog extends Model
{
    use SoftDeletes;

    public const EVENT_CREATED = 'created';
    public const EVENT_UPDATED = 'updated';
    public const EVENT_DELETED = 'deleted';
    public const EVENT_CUSTOM  = 'custom';

    public $timestamps = false; // sadece created_at — useCurrent migration'da

    protected $fillable = [
        'user_id',
        'event',
        'auditable_type',
        'auditable_id',
        'label',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'url',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values'   => 'array',
            'new_values'   => 'array',
            'auditable_id' => 'integer',
            'user_id'      => 'integer',
            'created_at'   => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Model, $this> */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @param Builder<AuditLog> $query */
    public function scopeOfEvent(Builder $query, string $event): Builder
    {
        return $query->where('event', $event);
    }

    /** @param Builder<AuditLog> $query */
    public function scopeOfModel(Builder $query, string $modelClass): Builder
    {
        return $query->where('auditable_type', $modelClass);
    }

    public function eventLabel(): string
    {
        return match ($this->event) {
            self::EVENT_CREATED => 'Oluşturuldu',
            self::EVENT_UPDATED => 'Güncellendi',
            self::EVENT_DELETED => 'Silindi',
            self::EVENT_CUSTOM  => 'Özel',
            default             => $this->event,
        };
    }

    public function eventBadgeClass(): string
    {
        return match ($this->event) {
            self::EVENT_CREATED => 'bg-success',
            self::EVENT_UPDATED => 'bg-info',
            self::EVENT_DELETED => 'bg-danger',
            self::EVENT_CUSTOM  => 'bg-warning text-dark',
            default             => 'bg-secondary',
        };
    }

    public function modelLabel(): string
    {
        if (! $this->auditable_type) return '—';
        return class_basename($this->auditable_type);
    }

    /**
     * Old vs new değerleri kıyaslayıp sadece değişenleri döner.
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function changedFields(): array
    {
        $old = is_array($this->old_values) ? $this->old_values : [];
        $new = is_array($this->new_values) ? $this->new_values : [];

        $changed = [];
        foreach ($new as $key => $newVal) {
            $oldVal = $old[$key] ?? null;
            if ($oldVal !== $newVal) {
                $changed[$key] = ['old' => $oldVal, 'new' => $newVal];
            }
        }
        return $changed;
    }
}
