<?php

declare(strict_types=1);

namespace App\Exports;

use App\Services\HealthCheckService;
use App\Support\Export\ExportColumn;
use App\Support\Export\ListExport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Sistem durumu kontrollerinin dışa aktarma tanımı.
 *
 * Arkasında tablo yok: kontroller her istekte yeniden çalışıyor. Dosya, o anki
 * durumun kaydı oluyor — sunucu sorununu yerinde göremeyen birine iletmenin
 * yolu bu.
 */
final class HealthCheckExport extends ListExport
{
    public function __construct(
        private readonly HealthCheckService $health,
    ) {}

    public function title(): string
    {
        return 'Sistem Durumu';
    }

    public function authorize(): void
    {
        Gate::authorize('view-system-health');
    }

    /**
     * Sistem durumu ekranında süzgeç yok: kontrollerin tamamı iniyor.
     *
     * @return list<string>
     */
    public function filterKeys(): array
    {
        return [];
    }

    public function count(array $filters): int
    {
        return count($this->health->runAll()['checks'] ?? []);
    }

    public function eachChunk(array $filters, int $size, callable $handler): void
    {
        /** @var list<array<string, mixed>> $checks */
        $checks = $this->health->runAll()['checks'] ?? [];

        foreach (array_chunk($checks, $size) as $chunk) {
            $handler(new Collection($chunk));
        }
    }

    /** @return list<ExportColumn> */
    public function columns(): array
    {
        return [
            ExportColumn::make('Kontrol', static fn (array $check): string => (string) $check['label'])->width(22),
            // Ekrandaki rozetle aynı sözcükler.
            ExportColumn::make('Durum', static fn (array $check): string => match ($check['status']) {
                'ok'       => 'Sağlıklı',
                'warning'  => 'Uyarı',
                'critical' => 'Kritik',
                default    => 'Bilinmiyor',
            })->width(12),
            ExportColumn::make('Sonuç', static fn (array $check): string => (string) $check['message'])->width(38),
            ExportColumn::make('Ayrıntı', static fn (array $check): string => (string) ($check['detail'] ?? ''))->width(34),
            ExportColumn::make('Ne Ölçülüyor', static fn (array $check): string => (string) ($check['what'] ?? ''))->width(34),
            ExportColumn::make('Öneri', static fn (array $check): string => (string) ($check['hint'] ?? ''))->width(34),
        ];
    }
}
