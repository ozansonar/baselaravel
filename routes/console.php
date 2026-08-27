<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

/**
 * Scheduled work.
 *
 * Everything here runs through Schedule::call(), never Schedule::command().
 * On the shared hosting this project targets, Schedule::command() spawns the
 * command as a separate process — and the functions needed to do that are
 * disabled, so the task silently never runs. Artisan::call() executes in the
 * same process and needs nothing special.
 *
 * See docs/SHARED-HOSTING.md. ScheduleUsesCallablesTest keeps this from
 * regressing.
 *
 * Because every task shares one PHP process, each closure is wrapped so a
 * failing task cannot take the rest of the schedule down with it.
 */

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Run an artisan command in-process, keeping a failure contained.
 */
$run = static function (string $command, array $parameters = []): callable {
    return static function () use ($command, $parameters): void {
        try {
            Artisan::call($command, $parameters);
        } catch (\Throwable $e) {
            Log::error('Zamanlanmış görev başarısız', [
                'command' => $command,
                'error'   => $e->getMessage(),
            ]);
            report($e);
        }
    };
};

// Process queued jobs every minute (shared hosting - no supervisor).
// queue:work requires the pcntl extension, which is not available here.
// Instead, pop and fire jobs directly via the Queue API — no Worker class.
// Aynı mantık panelden de tetikleniyor ("şimdi gönder"), o yüzden tek yerde.
Schedule::call(fn () => app(\App\Services\QueueRunner::class)->drain())
    ->name('queue-worker')
    ->everyMinute()
    ->withoutOverlapping(2);

// Bulk mail — every 5 minutes, sending only what the hourly limit allows.
// The interval must match CampaignDispatcher::RUN_INTERVAL_MINUTES, which is
// what the per-run quota is derived from.
Schedule::call($run('campaigns:dispatch'))
    ->name('campaigns-dispatch')
    ->everyFiveMinutes()
    ->withoutOverlapping(10);

// Analytics: aggregate the previous day's page_views into analytics_daily_stats
Schedule::call($run('analytics:aggregate-daily'))
    ->name('analytics-aggregate-daily')
    ->dailyAt('02:00')
    ->withoutOverlapping();

// Analytics: mask IPs older than 90 days (KVKK)
Schedule::call($run('analytics:anonymize-ips'))
    ->name('analytics-anonymize-ips')
    ->dailyAt('03:00')
    ->withoutOverlapping();

// Analytics: prune page_views older than 365 days (aggregates are preserved)
Schedule::call($run('analytics:prune-old'))
    ->name('analytics-prune-old')
    ->weekly()
    ->sundays()
    ->at('04:00')
    ->withoutOverlapping();

// Audit log cleanup — the same retention the screen tells the user about.
Schedule::call($run('audit-logs:prune', ['--days' => \App\Services\AuditLogService::RETENTION_DAYS]))
    ->name('audit-logs-prune')
    ->weekly()
    ->sundays()
    ->at('03:30')
    ->withoutOverlapping();

// Kampanya ekleri: kaydedilmeden bırakılmış yüklemeler bir günlük bekleme
// sonrası siliniyor. Dosya kampanyadan önce yüklendiği için form terk
// edilirse public/uploads altında sahipsiz kalıyor.
Schedule::call($run('campaigns:purge-attachments'))
    ->name('campaigns-purge-attachments')
    ->dailyAt('04:15')
    ->withoutOverlapping();

// Automatic backup — nightly (DB + uploads → ZIP).
// Given its own slot at 05:00: tasks due in the same minute run one after
// another inside a single PHP process, and the backup is by far the slowest
// thing in the schedule.
Schedule::call($run('backup:run'))
    ->name('backup-daily')
    ->dailyAt('05:00')
    ->withoutOverlapping(60);
