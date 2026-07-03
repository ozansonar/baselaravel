<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class VertexSchedule extends Model
{
    use SoftDeletes;

    public const string FREQ_DAILY = 'daily';
    public const string FREQ_WEEKLY = 'weekly';
    public const string FREQ_MONTHLY = 'monthly';

    protected $fillable = [
        'name',
        'prompt_id',
        'variables',
        'count',
        'aspect_ratio_override',
        'frequency',
        'days_of_week',
        'day_of_month',
        'time',
        'is_active',
        'last_run_at',
        'next_run_at',
        'total_runs',
        'total_images_generated',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'variables'               => 'array',
            'days_of_week'            => 'array',
            'count'                   => 'integer',
            'day_of_month'            => 'integer',
            'is_active'               => 'boolean',
            'last_run_at'             => 'datetime',
            'next_run_at'             => 'datetime',
            'total_runs'              => 'integer',
            'total_images_generated'  => 'integer',
        ];
    }

    /**
     * @return BelongsTo<VertexPrompt, $this>
     */
    public function prompt(): BelongsTo
    {
        return $this->belongsTo(VertexPrompt::class, 'prompt_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<VertexBatch, $this>
     */
    public function batches(): HasMany
    {
        return $this->hasMany(VertexBatch::class, 'schedule_id');
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeDue(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now());
    }

    public function calculateNextRun(?Carbon $after = null): ?Carbon
    {
        $base = $after ?? now();
        [$hour, $minute] = array_map('intval', explode(':', $this->time));

        return match ($this->frequency) {
            self::FREQ_DAILY   => $this->nextDaily($base, $hour, $minute),
            self::FREQ_WEEKLY  => $this->nextWeekly($base, $hour, $minute),
            self::FREQ_MONTHLY => $this->nextMonthly($base, $hour, $minute),
            default            => null,
        };
    }

    public function markRun(int $imagesCount): void
    {
        $this->update([
            'last_run_at'            => now(),
            'next_run_at'            => $this->calculateNextRun(),
            'total_runs'             => $this->total_runs + 1,
            'total_images_generated' => $this->total_images_generated + $imagesCount,
        ]);
    }

    public function frequencyLabel(): string
    {
        $time = $this->time;

        return match ($this->frequency) {
            self::FREQ_DAILY   => "Her Gün {$time}",
            self::FREQ_WEEKLY  => $this->weeklyLabel($time),
            self::FREQ_MONTHLY => "Her Ayın {$this->day_of_month}. günü {$time}",
            default            => $this->frequency,
        };
    }

    private function nextDaily(Carbon $base, int $hour, int $minute): Carbon
    {
        $candidate = $base->copy()->setTime($hour, $minute, 0);
        if ($candidate->lte($base)) {
            $candidate->addDay();
        }

        return $candidate;
    }

    private function nextWeekly(Carbon $base, int $hour, int $minute): Carbon
    {
        $days = is_array($this->days_of_week) ? $this->days_of_week : [1];
        sort($days);

        foreach ($days as $dow) {
            $candidate = $base->copy()->startOfWeek()->addDays($dow - 1)->setTime($hour, $minute, 0);
            if ($candidate->gt($base)) {
                return $candidate;
            }
        }

        $nextWeekFirst = $base->copy()->startOfWeek()->addWeek()->addDays($days[0] - 1)->setTime($hour, $minute, 0);

        return $nextWeekFirst;
    }

    private function nextMonthly(Carbon $base, int $hour, int $minute): Carbon
    {
        $dayOfMonth = min($this->day_of_month ?? 1, 28);

        $candidate = $base->copy()->setDay($dayOfMonth)->setTime($hour, $minute, 0);
        if ($candidate->lte($base)) {
            $candidate->addMonth()->setDay($dayOfMonth);
        }

        return $candidate;
    }

    private function weeklyLabel(string $time): string
    {
        $dayNames = [1 => 'Pzt', 2 => 'Sal', 3 => 'Çar', 4 => 'Per', 5 => 'Cum', 6 => 'Cmt', 7 => 'Paz'];
        $days = is_array($this->days_of_week) ? $this->days_of_week : [];
        sort($days);

        $labels = array_map(static fn (int $d): string => $dayNames[$d] ?? '?', $days);

        return 'Her ' . implode(', ', $labels) . " {$time}";
    }
}
