<?php

declare(strict_types=1);

namespace App\Exports;

use App\Services\BackupService;
use App\Support\Export\ExportColumn;
use App\Support\Export\ListExport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Yedek listesinin dışa aktarma tanımı.
 *
 * Bu listenin arkasında tablo yok: kayıtlar diskteki zip dosyalarından
 * okunuyor. Bu yüzden sorgu yerine servisin döndürdüğü satırlar akıtılıyor;
 * yedek sayısı onlarla ölçüldüğü için bunun bellek maliyeti yok.
 */
final class BackupExport extends ListExport
{
    public function __construct(
        private readonly BackupService $backups,
    ) {}

    public function title(): string
    {
        return 'Yedekler';
    }

    public function authorize(): void
    {
        Gate::authorize('view-backups');
    }

    /** @return list<string> */
    public function filterKeys(): array
    {
        return ['q', 'sort'];
    }

    public function count(array $filters): int
    {
        return count($this->backups->list($filters));
    }

    public function eachChunk(array $filters, int $size, callable $handler): void
    {
        foreach (array_chunk($this->backups->list($filters), $size) as $chunk) {
            $handler(new Collection($chunk));
        }
    }

    /** @return list<ExportColumn> */
    public function columns(): array
    {
        return [
            ExportColumn::make('Dosya', static fn (array $backup): string => (string) $backup['name'])->width(34),
            ExportColumn::make('Boyut', static fn (array $backup): string => (string) $backup['size_human'])->width(12),
            ExportColumn::make(
                'Veritabanı',
                static fn (array $backup): string => (string) ($backup['contents']['db_size_human'] ?? ''),
            )->width(12),
            ExportColumn::make(
                'Dosyalar',
                static fn (array $backup): string => (string) ($backup['contents']['files_size_human'] ?? ''),
            )->width(12),
            ExportColumn::make('Tarih', static fn (array $backup): ?\DateTimeInterface => $backup['created_at'])
                ->asDateTime()
                ->width(16),
            // Ekranda "3 gün kaldı" diye duran uyarı; yedeğin ne zaman
            // kendiliğinden silineceğini dosyadan da görmek gerekiyor.
            ExportColumn::make('Saklama', static function (array $backup): string {
                $remaining = (int) $backup['expires_in_days'];

                return match (true) {
                    $remaining <= 0  => 'Bugün silinecek',
                    $remaining === 1 => 'Yarın silinecek',
                    default          => $remaining . ' gün kaldı',
                };
            })->width(16),
        ];
    }
}
