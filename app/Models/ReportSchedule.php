<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReportFrequency;
use App\Enums\ReportType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * "Şu raporu şu sıklıkla şu adreslere gönder" tanımı.
 */
class ReportSchedule extends Model
{
    /** @use HasFactory<\Database\Factories\ReportScheduleFactory> */
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'type',
        'frequency',
        'range',
        'format',
        'recipients',
        'is_active',
        'user_id',
        'last_run_at',
        'last_error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type'        => ReportType::class,
            'frequency'   => ReportFrequency::class,
            'recipients'  => 'array',
            'is_active'   => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param Builder<$this> $query
     * @return Builder<$this>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Bugün zaten gönderildi mi?
     *
     * Cron dakikada bir çalışıyor; bu kontrol olmasaydı günlük rapor bin kez
     * giderdi.
     */
    public function alreadyRanToday(): bool
    {
        return $this->last_run_at !== null && $this->last_run_at->isToday();
    }
}
