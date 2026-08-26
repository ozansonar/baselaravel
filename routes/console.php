<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Process queued jobs every minute (shared hosting - no supervisor)
// queue:work requires pcntl extension which is not available on shared hosting.
// Instead, we pop and fire jobs directly via Queue API - no Worker class needed.
Schedule::call(function () {
    $maxJobs = 20;
    $maxTime = 50; // seconds, leave margin for cron minute
    $start = time();
    $processed = 0;

    $queue = app('queue')->connection('database');

    while ($processed < $maxJobs && (time() - $start) < $maxTime) {
        $job = $queue->pop('default');

        if (!$job) {
            break;
        }

        try {
            $job->fire();
            $job->delete();
            $processed++;
        } catch (\Throwable $e) {
            $job->fail($e);
            report($e);
        }
    }
})->name('queue-worker')->everyMinute()->withoutOverlapping(2);

// Analytics: Mask IPs older than 90 days (KVKK)
Schedule::command('analytics:anonymize-ips')
    ->name('analytics-anonymize-ips')
    ->dailyAt('03:00')
    ->withoutOverlapping();

// Analytics: Aggregate previous day's page_views into analytics_daily_stats
Schedule::command('analytics:aggregate-daily')
    ->name('analytics-aggregate-daily')
    ->dailyAt('02:00')
    ->withoutOverlapping();

// Analytics: Prune page_views older than 365 days (aggregate data preserved)
Schedule::command('analytics:prune-old')
    ->name('analytics-prune-old')
    ->weekly()
    ->sundays()
    ->at('04:00')
    ->withoutOverlapping();

// Audit log cleanup — remove entries older than 90 days weekly
Schedule::command('audit-logs:prune --days=90')
    ->name('audit-logs-prune')
    ->weekly()
    ->sundays()
    ->at('03:30')
    ->withoutOverlapping();

// Automatic backup — nightly at 03:00 (DB + uploads → ZIP)
Schedule::command('backup:run')
    ->name('backup-daily')
    ->dailyAt('03:00')
    ->withoutOverlapping(60);

// Bulk mail — every 5 minutes, sending only what the hourly limit allows.
// The interval must match CampaignDispatcher::RUN_INTERVAL_MINUTES, which is
// what the per-run quota is derived from.
Schedule::command('campaigns:dispatch')
    ->name('campaigns-dispatch')
    ->everyFiveMinutes()
    ->withoutOverlapping(10);
