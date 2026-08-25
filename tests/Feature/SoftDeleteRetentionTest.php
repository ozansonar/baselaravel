<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminNotification;
use App\Models\AnalyticsDailyStat;
use App\Models\AuditLog;
use App\Services\AuditLogger;
use App\Services\NotificationCenter;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * SoftDeletes is mandatory on every model (CLAUDE.md).
 *
 * Turning it on for the log tables changes what ->delete() means, so the
 * retention jobs that are supposed to reclaim space are covered here too.
 */
class SoftDeleteRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_model_uses_soft_deletes(): void
    {
        $missing = [];

        /** @var SplFileInfo $file */
        foreach (Finder::create()->files()->in(app_path('Models'))->name('*.php') as $file) {
            $class = 'App\\Models\\' . $file->getBasename('.php');

            if (!class_exists($class)) {
                continue;
            }

            $traits = [];
            $reflection = new ReflectionClass($class);

            while ($reflection !== false) {
                $traits = array_merge($traits, $reflection->getTraitNames());
                $reflection = $reflection->getParentClass();
            }

            if (!in_array(SoftDeletes::class, $traits, true)) {
                $missing[] = $class;
            }
        }

        $this->assertSame(
            [],
            $missing,
            'SoftDeletes kullanmayan model(ler): ' . implode(', ', $missing),
        );
    }

    public function test_every_model_table_has_a_deleted_at_column(): void
    {
        foreach ([AdminNotification::class, AuditLog::class, AnalyticsDailyStat::class] as $class) {
            $table = (new $class())->getTable();

            $this->assertTrue(
                \Illuminate\Support\Facades\Schema::hasColumn($table, 'deleted_at'),
                "{$table} tablosunda deleted_at kolonu yok",
            );
        }
    }

    public function test_deleting_a_notification_keeps_the_row_but_hides_it(): void
    {
        $notification = AdminNotification::create([
            'type'  => 'test',
            'level' => 'info',
            'title' => 'Deneme',
        ]);

        $notification->delete();

        $this->assertSame(0, AdminNotification::count());
        $this->assertSame(1, AdminNotification::withTrashed()->count());
    }

    /**
     * Retention pruning must actually free the rows. A plain ->delete() would
     * only stamp deleted_at and the table would grow without bound.
     */
    public function test_audit_log_pruning_removes_rows_from_the_table(): void
    {
        AuditLog::create([
            'event'      => AuditLog::EVENT_CUSTOM,
            'label'      => 'Eski kayıt',
            'created_at' => now()->subDays(200),
        ]);

        // A row that was already soft deleted must be reclaimed as well.
        $soft = AuditLog::create([
            'event'      => AuditLog::EVENT_CUSTOM,
            'label'      => 'Önce soft silinmiş',
            'created_at' => now()->subDays(200),
        ]);
        $soft->delete();

        AuditLog::create([
            'event'      => AuditLog::EVENT_CUSTOM,
            'label'      => 'Yeni kayıt',
            'created_at' => now()->subDays(3),
        ]);

        $pruned = AuditLogger::pruneOlderThan(90);

        $this->assertSame(2, $pruned);
        $this->assertSame(1, DB::table('audit_logs')->count(), 'Satırlar tablodan gerçekten silinmedi');
    }

    public function test_notification_pruning_removes_rows_from_the_table(): void
    {
        AdminNotification::create([
            'type'       => 'test',
            'level'      => 'info',
            'title'      => 'Eski bildirim',
            'created_at' => now()->subDays(200),
        ]);

        NotificationCenter::pruneOlderThan(60);

        $this->assertSame(0, DB::table('admin_notifications')->count());
    }

    /**
     * analytics_daily_stats.date is unique. A soft-deleted row for the same
     * date is invisible to the default query, so the nightly aggregation would
     * try to insert and hit the unique index.
     */
    public function test_daily_aggregation_survives_a_soft_deleted_row_for_the_same_date(): void
    {
        $date = now()->subDay()->toDateString();

        $stat = AnalyticsDailyStat::create([
            'date'        => $date,
            'total_views' => 5,
        ]);
        $stat->delete();

        $this->artisan('analytics:aggregate-daily', ['--date' => $date])
            ->assertSuccessful();

        $this->assertSame(1, DB::table('analytics_daily_stats')->count());

        $fresh = AnalyticsDailyStat::whereDate('date', $date)->first();

        $this->assertNotNull($fresh, 'Kayıt geri yüklenmedi');
        $this->assertNull($fresh->deleted_at);
    }
}
