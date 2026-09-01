<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\ErrorLog;
use App\Services\ErrorLogService;
use App\Support\Export\ExportColumn;
use App\Support\Export\ListExport;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * Hata kayıtları listesinin dışa aktarma tanımı.
 *
 * Yığın izi kasten sütun değil: bir hücreye sığmıyor, Excel'i okunmaz hâle
 * getiriyor ve dosyayı gereksiz şişiriyor. İz, tek kaydın detay ekranında
 * duruyor — dosyanın işi listeyi paylaşmak, hatayı ayıklamak değil.
 */
final class ErrorLogExport extends ListExport
{
    public function __construct(
        private readonly ErrorLogService $errorLogs,
    ) {}

    public function title(): string
    {
        return 'Hata Kayıtları';
    }

    public function authorize(): void
    {
        Gate::authorize('viewAny', ErrorLog::class);
    }

    /** @return list<string> */
    public function filterKeys(): array
    {
        return $this->errorLogs->filterKeys();
    }

    /** @return Builder<ErrorLog> */
    public function query(array $filters): Builder
    {
        return $this->errorLogs->query($filters);
    }

    /** @return list<ExportColumn> */
    public function columns(): array
    {
        return [
            ExportColumn::make('Hata', static fn (ErrorLog $log): string => $log->shortException())->width(26),
            ExportColumn::make('Mesaj', static fn (ErrorLog $log): string => (string) $log->message)->width(52),
            ExportColumn::make('Konum', static fn (ErrorLog $log): string => $log->location())->width(42),
            ExportColumn::make('Kaynak', static fn (ErrorLog $log): string => $log->isVendor() ? 'Paket' : 'Proje kodu')->width(12),
            ExportColumn::make('Tekrar', static fn (ErrorLog $log): int => $log->occurrences)
                ->asNumber()
                ->width(9),
            ExportColumn::make('Adres', static fn (ErrorLog $log): string => (string) $log->url)->width(46),
            ExportColumn::make('Durum', static fn (ErrorLog $log): string => $log->isResolved() ? 'Çözüldü' : 'Açık')->width(11),
            ExportColumn::make('Çözen', static fn (ErrorLog $log): string => $log->resolver?->full_name ?? '')->width(20),
            ExportColumn::make('İlk Görülme', static fn (ErrorLog $log): ?\DateTimeInterface => $log->first_seen_at)
                ->asDateTime()
                ->width(16),
            ExportColumn::make('Son Görülme', static fn (ErrorLog $log): ?\DateTimeInterface => $log->last_seen_at)
                ->asDateTime()
                ->width(16),
        ];
    }
}
