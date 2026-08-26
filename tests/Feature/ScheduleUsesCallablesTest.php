<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * Scheduled work has to survive the hosting it runs on.
 *
 * Schedule::command() runs the command as a separate process. On the shared
 * hosting this project targets, the functions needed to spawn one are disabled,
 * so such a task never runs — and never reports that it did not. Backups,
 * analytics cleanup and bulk mail all sat dead this way until it was noticed.
 *
 * Schedule::call() with Artisan::call() runs in the same process and works
 * everywhere. See docs/SHARED-HOSTING.md.
 */
class ScheduleUsesCallablesTest extends TestCase
{
    private function schedule(): Schedule
    {
        return app(Schedule::class);
    }

    public function test_every_scheduled_task_runs_in_process(): void
    {
        $offenders = [];

        foreach ($this->schedule()->events() as $event) {
            if (! $event instanceof CallbackEvent) {
                $offenders[] = $event->description ?: $event->command ?: 'isimsiz görev';
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'Schedule::command() ile tanımlanmış görev bu hostingte hiç çalışmaz: ' . implode(', ', $offenders),
        );
    }

    /**
     * runInBackground() spawns a process too, for the same reason.
     *
     * Comments are stripped first: the file explains why these are forbidden,
     * so it necessarily names them.
     */
    public function test_the_forbidden_calls_appear_nowhere_in_the_schedule(): void
    {
        $code = $this->sourceWithoutComments(base_path('routes/console.php'));

        $this->assertStringNotContainsString('runInBackground', $code);
        $this->assertStringNotContainsString('Schedule::command', $code);
    }

    /**
     * Strip comments and strings so only executable code is examined.
     */
    private function sourceWithoutComments(string $path): string
    {
        $code = '';

        foreach (token_get_all((string) file_get_contents($path)) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }

                $code .= $token[1];

                continue;
            }

            $code .= $token;
        }

        return $code;
    }

    /**
     * withoutOverlapping() locks on the task name. A callback has no command to
     * borrow a name from, so an unnamed one throws at runtime.
     */
    public function test_every_task_has_a_name(): void
    {
        $unnamed = [];

        foreach ($this->schedule()->events() as $index => $event) {
            if (($event->description ?? null) === null || $event->description === '') {
                $unnamed[] = 'index ' . $index . ' (' . $event->expression . ')';
            }
        }

        $this->assertSame([], $unnamed, 'İsimsiz zamanlanmış görev: ' . implode(', ', $unnamed));
    }

    /**
     * The tasks the project depends on must actually be registered — a rename
     * that quietly drops one would otherwise go unnoticed until the backup is
     * needed.
     *
     * @return array<string, array{string}>
     */
    public static function expectedTasks(): array
    {
        $names = [
            'queue-worker',
            'campaigns-dispatch',
            'analytics-aggregate-daily',
            'analytics-anonymize-ips',
            'analytics-prune-old',
            'audit-logs-prune',
            'backup-daily',
        ];

        return array_combine($names, array_map(static fn (string $n): array => [$n], $names));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('expectedTasks')]
    public function test_the_task_is_scheduled(string $name): void
    {
        $names = array_map(
            static fn ($event): string => (string) $event->description,
            $this->schedule()->events(),
        );

        $this->assertContains($name, $names);
    }

    /**
     * The per-run quota is derived from how often the cron fires, so the two
     * have to agree or the hourly limit stops meaning what it says.
     */
    public function test_the_mail_dispatcher_runs_on_its_declared_interval(): void
    {
        $event = collect($this->schedule()->events())
            ->firstWhere('description', 'campaigns-dispatch');

        $this->assertNotNull($event);
        $this->assertSame(
            '*/' . \App\Services\CampaignDispatcher::RUN_INTERVAL_MINUTES . ' * * * *',
            $event->expression,
            'Cron aralığı ile CampaignDispatcher::RUN_INTERVAL_MINUTES birbirini tutmuyor',
        );
    }
}
