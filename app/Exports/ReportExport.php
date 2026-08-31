<?php

declare(strict_types=1);

namespace App\Exports;

use App\Enums\ReportType;
use App\Services\ReportService;
use App\Support\Export\ExportColumn;
use App\Support\Export\ListExport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Rapor satırlarının Excel/PDF çıktısı.
 *
 * Arkasında sorgu yok: satırlar ReportService'in ürettiği toplamlardan
 * geliyor. Sınıf yine de ListExport'tan türüyor, çünkü "nasıl yazılacağı"
 * (Excel akışı, PDF sayfalama, dosya adı, indirme yanıtı) orada bir kez
 * çözülmüş durumda — ikinci bir yazıcı yazmak, aynı hatayı iki yerde
 * düzeltmek demekti.
 *
 * Sütun başlıkları raporun kendisinden geliyor; her rapor türü için ayrı bir
 * export sınıfı yok.
 */
final class ReportExport extends ListExport
{
    private ReportType $type;

    private Carbon $from;

    private Carbon $to;

    /** @var array<string, mixed>|null */
    private ?array $report = null;

    /**
     * Konu ve aralık istekten okunuyor.
     *
     * Sütun başlıkları rapor türüne göre değiştiği için `columns()` çağrılana
     * kadar hangi raporun aktarıldığı bilinmek zorunda; yazıcılar ise
     * `columns()`'a süzgeç geçirmiyor. Zamanlanmış gönderim gibi istek dışı
     * çağrılar {@see for()} ile aynı şeyi elle söylüyor.
     */
    public function __construct(
        private readonly ReportService $reports,
    ) {
        $request = request();

        $this->type = ReportType::tryFrom((string) $request->query('type', '')) ?? ReportType::Traffic;

        [$this->from, $this->to] = $this->reports->resolveRange(
            $request->query('range') !== null ? (string) $request->query('range') : null,
            $request->query('from') !== null ? (string) $request->query('from') : null,
            $request->query('to') !== null ? (string) $request->query('to') : null,
        );
    }

    /**
     * Hangi rapor, hangi aralık.
     */
    public function for(ReportType $type, Carbon $from, Carbon $to): self
    {
        $this->type = $type;
        $this->from = $from;
        $this->to = $to;
        $this->report = null;

        return $this;
    }

    public function title(): string
    {
        return $this->type->label() . ' (' . $this->from->format('d.m.Y') . ' – ' . $this->to->format('d.m.Y') . ')';
    }

    public function authorize(): void
    {
        Gate::authorize('view-reports');
    }

    /** @return list<string> */
    public function filterKeys(): array
    {
        return ['type', 'range', 'from', 'to', 'search'];
    }

    public function count(array $filters): int
    {
        return count($this->rows($filters));
    }

    public function eachChunk(array $filters, int $size, callable $handler): void
    {
        foreach (array_chunk($this->rows($filters), $size) as $chunk) {
            $handler(new Collection($chunk));
        }
    }

    /** @return list<ExportColumn> */
    public function columns(): array
    {
        $columns = [];

        foreach ($this->build()['columns'] as $index => $heading) {
            $columns[] = ExportColumn::make(
                (string) $heading,
                static fn (array $row): string => (string) ($row[$index] ?? ''),
            )->width($index === 0 ? 34 : 16);
        }

        return $columns;
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<int, string>>
     */
    private function rows(array $filters): array
    {
        /** @var list<array<int, string>> $rows */
        $rows = $this->build()['rows'];

        return $this->reports->filterRows($rows, isset($filters['search']) ? (string) $filters['search'] : null);
    }

    /**
     * Rapor bir istekte bir kez üretiliyor: sütunlar, sayım ve satırlar aynı
     * veriye üç kez soruyor.
     *
     * @return array<string, mixed>
     */
    private function build(): array
    {
        return $this->report ??= $this->reports->build($this->type, $this->from, $this->to);
    }
}
