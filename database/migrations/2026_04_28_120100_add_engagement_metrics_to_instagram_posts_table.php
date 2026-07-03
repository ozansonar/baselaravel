<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Engagement metrics — Meta Graph API'den günlük olarak çekilir.
 *
 * Public alanlar (her hesapta var):
 *   - like_count, comments_count
 *
 * Insights alanlar (sadece business/creator hesapta):
 *   - reach, impressions, saved_count
 *
 * metrics_fetched_at: son sync zamanı (cron rate limit için).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instagram_posts', function (Blueprint $table) {
            $table->unsignedInteger('like_count')->nullable()->after('notified_failed_at');
            $table->unsignedInteger('comments_count')->nullable()->after('like_count');
            $table->unsignedInteger('reach')->nullable()->after('comments_count');
            $table->unsignedInteger('impressions')->nullable()->after('reach');
            $table->unsignedInteger('saved_count')->nullable()->after('impressions');
            $table->timestamp('metrics_fetched_at')->nullable()->after('saved_count');

            $table->index('metrics_fetched_at');
        });
    }

    public function down(): void
    {
        Schema::table('instagram_posts', function (Blueprint $table) {
            $table->dropIndex(['metrics_fetched_at']);
            $table->dropColumn([
                'like_count',
                'comments_count',
                'reach',
                'impressions',
                'saved_count',
                'metrics_fetched_at',
            ]);
        });
    }
};
