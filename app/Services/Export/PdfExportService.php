<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Support\Export\ExportColumn;
use App\Support\Export\ListExport;
use DateTimeInterface;
use Illuminate\Support\Facades\View;
use Mpdf\HTMLParserMode;
use Mpdf\Output\Destination;
use Mpdf\Mpdf;

/**
 * Liste tanımını PDF dosyasına yazar.
 *
 * Sayfa yönü sabit değil: sütunlar dikey sayfaya sığıyorsa dikey, sığmıyorsa
 * yatay üretilir. Her sayfanın altında proje adı, belge adı ve sayfa numarası
 * durur.
 */
final class PdfExportService
{
    /**
     * A4'ün dikey ve yatay kullanılabilir genişliğinin yaklaşık karakter
     * karşılığı (7.5pt yoğun yazı tipiyle). Sütun ağırlıkları toplamı dikey
     * sınırı aşıyorsa sayfa yatay çevrilir.
     */
    private const PORTRAIT_CAPACITY = 95.0;

    /**
     * Dosyayı yazar ve yazılan veri satırı sayısını döndürür.
     *
     * @param array<string, mixed> $filters
     */
    public function write(ListExport $export, array $filters, string $path): int
    {
        $columns = $export->columns();
        $title = $export->title();
        $project = (string) config('app.name');
        $total = $export->count($filters);

        $mpdf = $this->makeMpdf($columns);
        $mpdf->SetTitle($title);
        $mpdf->SetAuthor($project);
        $mpdf->SetCreator($project);

        // Stil sayfası önce: altbilgi de gövde de aynı kuralları görsün.
        $mpdf->WriteHTML(View::make('exports.pdf.styles')->render(), HTMLParserMode::HEADER_CSS);

        $mpdf->SetHTMLFooter(View::make('exports.pdf.footer', [
            'project' => $project,
            'title'   => $title,
        ])->render());

        $mpdf->WriteHTML(View::make('exports.pdf.document-head', [
            'title'       => $title,
            'project'     => $project,
            'generatedAt' => now()->format('d.m.Y H:i'),
            'columns'     => $columns,
            'widths'      => $this->columnWidths($columns),
            'rowCount'    => $total,
        ])->render(), HTMLParserMode::HTML_BODY);

        $rowCount = $this->writeRows($mpdf, $export, $filters, $columns);

        $mpdf->WriteHTML(
            View::make('exports.pdf.document-foot', ['rowCount' => $total])->render(),
            HTMLParserMode::HTML_BODY,
        );

        $mpdf->Output($path, Destination::FILE);

        return $rowCount;
    }

    /**
     * @param list<ExportColumn> $columns
     */
    private function makeMpdf(array $columns): Mpdf
    {
        return new Mpdf([
            'tempDir'           => (string) config('export.temp_path'),
            'format'            => $this->needsLandscape($columns) ? 'A4-L' : 'A4',
            'margin_top'        => 10,
            'margin_bottom'     => 14,
            'margin_left'       => 8,
            'margin_right'      => 8,
            'margin_footer'     => 6,
            // DejaVu, Türkçe harflerin tamamını taşıyan gömülü yazı tipi.
            'default_font'      => 'dejavusanscondensed',
            'default_font_size' => 7.5,
            // Tablo hücrelerini sıkıştırarak tutar: geniş listelerde bellek
            // tüketimini belirgin düşürür.
            'packTableData'     => true,
            // Sığmayan tabloyu kesmek yerine küçültür; sütun taşması olmaz.
            'shrink_tables_to_fit' => 1,
        ]);
    }

    /**
     * Satırları parça parça yazar.
     *
     * Kayıtlar parçalar hâlinde çekiliyor, her parça kendi HTML'ine çevrilip
     * hemen belgeye yazılıyor: ne modellerin tamamı ne de HTML'in tamamı aynı
     * anda bellekte duruyor. Tek yazım zaten mümkün değil — mPDF, PCRE geri
     * izleme sınırı yüzünden 1 MB'tan büyük HTML'i reddediyor.
     *
     * @param array<string, mixed> $filters
     * @param list<ExportColumn> $columns
     */
    private function writeRows(Mpdf $mpdf, ListExport $export, array $filters, array $columns): int
    {
        $rowCount = 0;

        $export->eachChunk($filters, (int) config('export.chunk_size', 500), function ($records) use ($mpdf, $columns, &$rowCount): void {
            $rows = [];

            foreach ($records as $record) {
                $cells = [];

                foreach ($columns as $column) {
                    $cells[] = [
                        'value' => $this->stringValue($column, $record),
                        'class' => $column->type->alignmentClass(),
                    ];
                }

                $rows[] = $cells;
                ++$rowCount;
            }

            $mpdf->WriteHTML(
                View::make('exports.pdf.rows', ['rows' => $rows])->render(),
                HTMLParserMode::HTML_BODY,
            );
        });

        return $rowCount;
    }

    private function stringValue(ExportColumn $column, mixed $record): string
    {
        $value = $column->resolve($record);

        return match (true) {
            $value === null              => '',
            $value instanceof DateTimeInterface => $value->format($column->type->phpDateFormat()),
            is_bool($value)              => $value ? 'Evet' : 'Hayır',
            default                      => (string) $value,
        };
    }

    /**
     * Sütun ağırlıklarını yüzdeye çevirir: mPDF sabit genişlik yerine oranla
     * çalıştığında dar sütunlar dar, uzun metin sütunları geniş kalır.
     *
     * @param list<ExportColumn> $columns
     * @return list<float>
     */
    private function columnWidths(array $columns): array
    {
        $total = array_sum(array_map(static fn (ExportColumn $column): float => $column->weight, $columns));

        if ($total <= 0.0) {
            return array_fill(0, count($columns), round(100 / max(count($columns), 1), 2));
        }

        return array_map(
            static fn (ExportColumn $column): float => round($column->weight / $total * 100, 2),
            $columns,
        );
    }

    /**
     * @param list<ExportColumn> $columns
     */
    private function needsLandscape(array $columns): bool
    {
        $total = array_sum(array_map(static fn (ExportColumn $column): float => $column->weight, $columns));

        return $total > self::PORTRAIT_CAPACITY;
    }
}
