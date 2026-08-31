<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AuditEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
    use HasFactory, SoftDeletes;

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
        if (! $this->auditable_type) {
            return '—';
        }
        return class_basename($this->auditable_type);
    }

    /**
     * Detay ekranında gösterilecek değer kümesi ve başlığı.
     *
     * Olay türüne göre anlamlı olan taraf değişir: güncellemede eski/yeni
     * kıyası, oluşturmada kaydın kendisi, silmede kaybolan kayıt, özel olayda
     * ise işlemin bağlamı.
     *
     * @return array{title: string, hint: string, mode: string, rows: array<string, mixed>}
     */
    public function detailValues(): array
    {
        return match ($this->event) {
            AuditEvent::Updated => [
                'title' => 'Değişiklikler',
                'hint'  => 'Yalnızca değeri değişen alanlar listelenir.',
                'mode'  => 'diff',
                'rows'  => $this->changedFields(),
            ],
            AuditEvent::Created => [
                'title' => 'Oluşturulan kayıt',
                'hint'  => 'Kayıt oluşturulduğunda alanların aldığı değerler.',
                'mode'  => 'single',
                'rows'  => is_array($this->new_values) ? $this->new_values : [],
            ],
            AuditEvent::Deleted => [
                'title' => 'Silinen kayıt',
                'hint'  => 'Silinmeden önceki son hâli.',
                'mode'  => 'single',
                'rows'  => is_array($this->old_values) ? $this->old_values : [],
            ],
            default => [
                'title' => 'İşlem ayrıntısı',
                'hint'  => 'İşlemi yapan kodun kayda bıraktığı bilgiler.',
                'mode'  => 'single',
                'rows'  => is_array($this->new_values) ? $this->new_values : [],
            ],
        };
    }

    /**
     * Bir değeri ekranda okunacak hâle getirir.
     *
     * Ham JSON'da null "null", true "1" görünüyordu; denetim kaydına bakan
     * kişi için ikisi de anlamsız.
     */
    public static function formatValue(mixed $value): string
    {
        return match (true) {
            $value === null            => '—',
            is_bool($value)            => $value ? 'Evet' : 'Hayır',
            $value === ''              => '(boş)',
            is_array($value)           => (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            default                    => (string) $value,
        };
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
