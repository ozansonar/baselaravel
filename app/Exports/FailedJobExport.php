<?php

declare(strict_types=1);

namespace App\Exports;

use App\Services\QueueMonitorService;
use App\Support\Export\ExportColumn;
use App\Support\Export\ListExport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Başarısız işlerin dışa aktarma tanımı.
 *
 * Kuyruk ekranındaki liste kalıcı değil: iş yeniden denendiğinde ya da
 * temizlendiğinde kaydı siliniyor. Bu yüzden dosya, tabloyu boşaltmadan önce
 * elde kalan tek kayıt oluyor — hata metninin ilk satırı da bu yüzden
 * sütunlardan biri.
 */
final class FailedJobExport extends ListExport
{
    public function __construct(
        private readonly QueueMonitorService $queue,
    ) {}

    public function title(): string
    {
        return 'Başarısız İşler';
    }

    public function authorize(): void
    {
        Gate::authorize('view-queue');
    }

    /** @return list<string> */
    public function filterKeys(): array
    {
        return $this->queue->filterKeys();
    }

    public function count(array $filters): int
    {
        return $this->queue->countFailed($filters);
    }

    public function eachChunk(array $filters, int $size, callable $handler): void
    {
        $this->queue->eachFailedChunk($filters, $size, static function (array $rows) use ($handler): void {
            $handler(new Collection($rows));
        });
    }

    /** @return list<ExportColumn> */
    public function columns(): array
    {
        return [
            ExportColumn::make('İş', static fn (array $job): string => (string) $job['job'])->width(30),
            ExportColumn::make('Kuyruk', static fn (array $job): string => (string) $job['queue'])->width(14),
            ExportColumn::make('Bağlantı', static fn (array $job): string => (string) $job['connection'])->width(12),
            ExportColumn::make('Deneme', static fn (array $job): int => (int) $job['attempts'])
                ->asNumber()
                ->width(9),
            ExportColumn::make('Hata', static fn (array $job): string => (string) $job['error'])->width(46),
            ExportColumn::make('Kimlik', static fn (array $job): string => (string) $job['uuid'])->width(22),
            ExportColumn::make(
                'Hata Zamanı',
                static fn (array $job): ?\DateTimeInterface => $job['failed_at'],
            )->asDateTime()->width(16),
        ];
    }
}
