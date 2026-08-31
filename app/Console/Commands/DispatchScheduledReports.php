<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ReportSchedule;
use App\Services\ReportScheduleService;
use Illuminate\Console\Command;

/**
 * Zamanlanmış raporların cron girişi.
 *
 * Günde bir kez çalışması yeterli ama cron her dakika uğradığı için "bugün
 * zaten gönderildi mi" kontrolü servistedir; komut o kontrole güveniyor.
 */
final class DispatchScheduledReports extends Command
{
    protected $signature = 'reports:dispatch
                            {--schedule= : Yalnızca bu tanımı çalıştır (sırası gelmemiş olsa da)}';

    protected $description = 'Sırası gelen zamanlanmış raporları üretip alıcılarına gönderir';

    public function handle(ReportScheduleService $schedules): int
    {
        $id = $this->option('schedule');

        if ($id !== null) {
            $schedule = ReportSchedule::find((int) $id);

            if ($schedule === null) {
                $this->error('Tanım bulunamadı.');

                return self::FAILURE;
            }

            $ok = $schedules->run($schedule);
            $this->info($ok ? 'Rapor gönderildi.' : 'Rapor gönderilemedi: ' . (string) $schedule->fresh()?->last_error);

            return $ok ? self::SUCCESS : self::FAILURE;
        }

        $due = $schedules->due();

        if ($due->isEmpty()) {
            $this->info('Sırası gelen rapor yok.');

            return self::SUCCESS;
        }

        $gonderilen = 0;
        $dusen = 0;

        foreach ($due as $schedule) {
            $schedules->run($schedule) ? $gonderilen++ : $dusen++;
        }

        $this->info("{$gonderilen} rapor gönderildi, {$dusen} tanesi düştü.");

        return self::SUCCESS;
    }
}
