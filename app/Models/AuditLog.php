<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AuditEvent;
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
            'event'        => AuditEvent::class,
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
        return $this->event?->label() ?? '—';
    }

    public function eventBadgeClass(): string
    {
        return 'bg-' . ($this->event?->color() ?? 'secondary');
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
