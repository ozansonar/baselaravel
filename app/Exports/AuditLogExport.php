<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\AuditLog;
use App\Services\AuditLogService;
use App\Support\Export\ExportColumn;
use App\Support\Export\ListExport;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/** Aktivite kayıtları listesinin dışa aktarma tanımı. */
final class AuditLogExport extends ListExport
{
    public function __construct(
        private readonly AuditLogService $logs,
    ) {}

    public function title(): string
    {
        return 'Aktivite Kayıtları';
    }

    public function authorize(): void
    {
        Gate::authorize('viewAny', AuditLog::class);
    }

    /** @return list<string> */
    public function filterKeys(): array
    {
        return $this->logs->filterKeys();
    }

    public function query(array $filters): Builder
    {
        return $this->logs->query($filters);
    }

    /** @return list<ExportColumn> */
    public function columns(): array
    {
        return [
            ExportColumn::make('Zaman', static fn (AuditLog $log): ?\DateTimeInterface => $log->created_at)
                ->asDateTime()
                ->width(16),
            // Kaydı sistemin kendisi bıraktıysa kullanıcı yoktur; ekranda da
            // "Sistem" yazıyor.
            ExportColumn::make('Kullanıcı', static function (AuditLog $log): string {
                if ($log->user === null) {
                    return 'Sistem';
                }

                $name = trim(($log->user->first_name ?? '') . ' ' . ($log->user->last_name ?? ''));

                return $name !== '' ? $name : (string) $log->user->email;
            })->width(20),
            ExportColumn::make('İşlem', static fn (AuditLog $log): string => $log->eventLabel())->width(14),
            ExportColumn::make('Kayıt', static fn (AuditLog $log): string => $log->auditable_type
                ? $log->modelLabel() . ' #' . $log->auditable_id
                : '')->width(18),
            ExportColumn::make('Açıklama', static fn (AuditLog $log): string => (string) ($log->label ?: $log->modelLabel() . ' #' . $log->auditable_id))->width(34),
            ExportColumn::make('IP', static fn (AuditLog $log): string => (string) $log->ip_address)->width(14),
        ];
    }
}
