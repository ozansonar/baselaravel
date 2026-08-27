<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\MailLog;
use App\Services\MailLogService;
use App\Support\Export\ExportColumn;
use App\Support\Export\ListExport;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/** Mail kayıtları listesinin dışa aktarma tanımı. */
final class MailLogExport extends ListExport
{
    public function __construct(
        private readonly MailLogService $mailLogs,
    ) {}

    public function title(): string
    {
        return 'Mail Kayıtları';
    }

    public function authorize(): void
    {
        Gate::authorize('viewAny', MailLog::class);
    }

    /** @return list<string> */
    public function filterKeys(): array
    {
        return $this->mailLogs->filterKeys();
    }

    public function query(array $filters): Builder
    {
        return $this->mailLogs->query($filters);
    }

    /** @return list<ExportColumn> */
    public function columns(): array
    {
        return [
            ExportColumn::make('Tür', static fn (MailLog $log): string => (string) $log->mailable_label)->width(20),
            ExportColumn::make('Konu', static fn (MailLog $log): string => (string) $log->subject)->width(30),
            ExportColumn::make('Alıcı', static fn (MailLog $log): string => (string) $log->to)->width(26),
            ExportColumn::make('Durum', static fn (MailLog $log): string => $log->status?->label() ?? '')->width(12),
            // Başarısız gönderimin nedeni ekranda durumun altında duruyor;
            // dosyada da satırın yanında olmalı, yoksa "neden gitmedi" sorusu
            // panele geri dönmeden yanıtlanamıyor.
            ExportColumn::make('Hata', static fn (MailLog $log): string => (string) $log->error_message)->width(30),
            ExportColumn::make('Tarih', static fn (MailLog $log): ?\DateTimeInterface => $log->created_at)
                ->asDateTime()
                ->width(14),
        ];
    }
}
