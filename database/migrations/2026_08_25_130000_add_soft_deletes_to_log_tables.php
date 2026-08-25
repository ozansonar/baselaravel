<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brings the last three models in line with the project rule that every model
 * uses SoftDeletes.
 *
 * audit_logs and admin_notifications are queried on every panel request, so
 * the "deleted_at is null" clause the trait adds gets its own index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_notifications', function (Blueprint $table): void {
            $table->softDeletes();
            $table->index('deleted_at', 'admin_notifications_deleted_at_idx');
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->softDeletes();
            $table->index('deleted_at', 'audit_logs_deleted_at_idx');
        });

        Schema::table('analytics_daily_stats', function (Blueprint $table): void {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('admin_notifications', function (Blueprint $table): void {
            $table->dropIndex('admin_notifications_deleted_at_idx');
            $table->dropSoftDeletes();
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex('audit_logs_deleted_at_idx');
            $table->dropSoftDeletes();
        });

        Schema::table('analytics_daily_stats', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
