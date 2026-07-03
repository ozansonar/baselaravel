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

// Fetch Google reviews daily at 06:00
Schedule::call(function () {
    Artisan::call('google:fetch-reviews');
})->name('google-fetch-reviews')->dailyAt('06:00')->withoutOverlapping();

// Fetch YouTube videos daily at 07:00
Schedule::call(function () {
    Artisan::call('youtube:fetch-videos');
})->name('youtube-fetch-videos')->dailyAt('07:00')->withoutOverlapping();

// Generate AI blog content 4 times a day at off-peak minutes.
// Round-hour slots (09:00, 12:00, ...) collide with every other scheduler on
// earth and are the worst time to hit Gemini. Offset each slot by a unique
// minute to reduce 503 "high demand" errors.
Schedule::call(function () {
    Artisan::call('blog:generate');
})->name('blog-generate-morning')->dailyAt('09:07')->withoutOverlapping();

Schedule::call(function () {
    Artisan::call('blog:generate');
})->name('blog-generate-noon')->dailyAt('12:23')->withoutOverlapping();

Schedule::call(function () {
    Artisan::call('blog:generate');
})->name('blog-generate-afternoon')->dailyAt('15:41')->withoutOverlapping();

Schedule::call(function () {
    Artisan::call('blog:generate');
})->name('blog-generate-evening')->dailyAt('18:16')->withoutOverlapping();

// Publish scheduled Instagram posts every 5 minutes — 00:00, 00:05, 00:10, ...
// her 5 dakikada bir scheduled_at ≤ now olan post varsa yayınlar.
// --limit 1 → her tur 1 post (birden fazla varsa sırayla, sonraki turda).
// withoutOverlapping(4) → bir önceki tur 4 dakikadan uzun sürdüyse override
// edilir (interval 5dk olduğu için kilit max 4dk tutulur).
// Heartbeat: writes "instagram_cron_last_run" so admin UI can show cron health.
Schedule::call(function () {
    \App\Models\Setting::setValue('instagram_cron_last_run', now()->toDateTimeString(), 'instagram', 'text');
    Artisan::call('instagram:publish-scheduled', ['--limit' => 1]);
})->name('instagram-publish-scheduled')->everyFiveMinutes()->withoutOverlapping(4);

// Prune Instagram API logs older than 30 days (keep table size in check)
Schedule::command('instagram:prune-logs --days=30')
    ->name('instagram-prune-logs')
    ->dailyAt('03:30')
    ->withoutOverlapping();

// Sync Instagram engagement metrics (likes, comments, reach) — günde 1 kez
Schedule::command('instagram:sync-engagement')
    ->name('instagram-sync-engagement')
    ->dailyAt('04:30')
    ->withoutOverlapping();

// Instagram Insights snapshot (Meta Graph API) — son 7 gün post'ları için
// günlük metric trend'i çek (instagram_post_insights tablosuna kaydeder)
Schedule::command('instagram:fetch-insights --days=7')
    ->name('instagram-fetch-insights')
    ->dailyAt('05:00')
    ->withoutOverlapping();

// Refresh Instagram token daily (renews if <10 days until expiry)
Schedule::command('instagram:refresh-token')
    ->name('instagram-refresh-token')
    ->dailyAt('04:00')
    ->withoutOverlapping();

// Refresh TikTok access token daily — yalnızca 3 günden az kaldıysa
// fiilen API'yi çağırır. docs/tiktok.md Bölüm 6 / Faz 6.
Schedule::command('tiktok:refresh-token')
    ->name('tiktok-refresh-token')
    ->dailyAt('04:15')
    ->withoutOverlapping();

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

// Google Search Console — top queries + per-page metrics
// Off-peak saatlerde 6 saatte bir, dakika offset Google API kotasını dağıtır.
Schedule::command('gsc:fetch')
    ->name('gsc-fetch-metrics')
    ->cron('13 */6 * * *')          // 02:13, 08:13, 14:13, 20:13
    ->withoutOverlapping(60);

// Görsel kütüphanesi haftalık özet — Pazartesi 09:30 Telegram'a gider
Schedule::command('image-library:weekly-report')
    ->name('image-library-weekly-report')
    ->weekly()
    ->mondays()
    ->at('09:30')
    ->withoutOverlapping();

// Audit log temizliği — 90 günden eskileri haftada bir sil
Schedule::command('audit-logs:prune --days=90')
    ->name('audit-logs-prune')
    ->weekly()
    ->sundays()
    ->at('03:30')
    ->withoutOverlapping();

// Vertex zamanlanmış üretimler — her dakika due schedule'ları kontrol et
Schedule::command('vertex:process-schedules')
    ->name('vertex-process-schedules')
    ->everyMinute()
    ->withoutOverlapping(5);

// Vertex batch görsel üretimi — her dakika kontrol et, 15sn arayla üret
// 100 görsel ~40dk sürebilir, mutex en az o kadar tutmalı
Schedule::command('vertex:process-batch')
    ->name('vertex-process-batch')
    ->everyMinute()
    ->withoutOverlapping(120);

// Otomatik yedek — her gece 03:00 (DB + uploads → ZIP)
Schedule::command('backup:run')
    ->name('backup-daily')
    ->dailyAt('03:00')
    ->withoutOverlapping(60);
