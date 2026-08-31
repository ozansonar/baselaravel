<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\ReportSchedule;
use App\Models\Setting;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Carbon;

/**
 * Zamanlanmış raporun kendisi — ek olarak.
 *
 * Gövde bilerek kısa: rapor eklentide, mailin işi onu taşımak. Uzun bir özet
 * yazmak, aynı sayıları iki yerde tutmak ve birinin bayatlaması demekti.
 */
final class ScheduledReportMail extends BaseMail
{
    /**
     * Tarih alanları `from`/`to` diye adlandırılamıyor: Mailable'ın kendi
     * `$from` özelliği var ve onu readonly olarak yeniden tanımlamak PHP
     * hatası veriyor. Ad değişikliği bu yüzden.
     */
    public function __construct(
        public readonly ReportSchedule $schedule,
        public readonly Carbon $rangeStart,
        public readonly Carbon $rangeEnd,
        private readonly string $filePath,
        private readonly string $reportTitle,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->schedule->type->label() . ' — ' . Setting::getValue('site_name', config('app.name')),
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->filePath)
                ->as(basename($this->filePath))
                ->withMime($this->schedule->format === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ];
    }

    protected function emailView(): string
    {
        return 'emails.scheduled-report';
    }

    protected function templateKey(): string
    {
        return 'scheduled_report';
    }

    /**
     * @return array<string, string>
     */
    protected function templateVariables(): array
    {
        return [
            'report_title' => $this->reportTitle,
            'report_range' => $this->rangeStart->format('d.m.Y') . ' – ' . $this->rangeEnd->format('d.m.Y'),
            'frequency'    => $this->schedule->frequency->label(),
            'site_name'    => (string) Setting::getValue('site_name', config('app.name')),
        ];
    }
}
