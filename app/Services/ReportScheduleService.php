<?php

declare(strict_types=1);

namespace App\Services;

use App\Exports\ReportExport;
use App\Mail\ScheduledReportMail;
use App\Models\ReportSchedule;
use App\Support\Export\ExportFormat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Zamanlanmış raporların üretimi ve gönderimi.
 *
 * Cron dakikada bir uğruyor; burada iki soru var: "bugün sırası geldi mi" ve
 * "bugün zaten gönderildi mi". İkincisi olmasa günlük rapor bin kez giderdi.
 *
 * Üretim tamamen anlık raporla aynı yolu kullanıyor (ReportExport): ekranda
 * indirilen dosya ile e-postayla gelen dosya aynı kodun ürünü, yani zamanla
 * ayrışmıyorlar.
 */
final class ReportScheduleService
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly ReportExport $export,
        private readonly \App\Services\Export\ExportService $exports,
        private readonly MailService $mail,
    ) {}

    /**
     * Bugün gönderilmesi gereken tanımlar.
     *
     * @return Collection<int, ReportSchedule>
     */
    public function due(): Collection
    {
        return ReportSchedule::active()
            ->get()
            ->filter(fn (ReportSchedule $schedule): bool => $schedule->frequency->dueOn(now())
                && ! $schedule->alreadyRanToday());
    }

    /**
     * Tek bir tanımı çalıştırır.
     *
     * Hata yutulmuyor ama akışı da durdurmuyor: bir tanımın alıcısı bozuksa
     * ötekiler yine gitmeli. Sebep tanımın üstüne yazılıyor, ekranda görünüyor.
     */
    public function run(ReportSchedule $schedule): bool
    {
        try {
            [$from, $to] = $this->reports->resolveRange($schedule->range);

            $format = ExportFormat::tryFrom($schedule->format) ?? ExportFormat::Excel;

            $export = $this->export->for($schedule->type, $from, $to);
            $path = $this->exports->store($export, $format, []);

            foreach ($schedule->recipients as $recipient) {
                $this->mail->queue(
                    (string) $recipient,
                    new ScheduledReportMail($schedule, $from, $to, $path, $export->title()),
                );
            }

            $schedule->forceFill(['last_run_at' => now(), 'last_error' => null])->save();

            return true;
        } catch (\Throwable $e) {
            Log::warning('Zamanlanmış rapor gönderilemedi', [
                'schedule_id' => $schedule->getKey(),
                'error'       => $e->getMessage(),
            ]);

            $schedule->forceFill([
                'last_run_at' => now(),
                'last_error'  => mb_substr($e->getMessage(), 0, 500),
            ])->save();

            return false;
        }
    }

    /**
     * Üretilen dosyaları temizler.
     *
     * Rapor ekleri geçici: gönderildikten sonra sunucuda durmalarının bir
     * anlamı yok ve içlerinde kişisel veri olabiliyor. Kuyruk maili hemen
     * göndermediği için dosya bir süre kalmak zorunda — bu yüzden silme
     * gönderimin ardından değil, ertesi gün cron'da yapılıyor.
     */
    public function purgeGeneratedFiles(int $olderThanHours = 24): int
    {
        $dir = (string) config('export.temp_path');

        if (! File::isDirectory($dir)) {
            return 0;
        }

        $silinen = 0;

        foreach (File::files($dir) as $file) {
            if ($file->getMTime() < now()->subHours($olderThanHours)->getTimestamp()) {
                File::delete($file->getPathname());
                $silinen++;
            }
        }

        return $silinen;
    }
}
