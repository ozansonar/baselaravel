<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Services\Export\Concerns\GuardsSpreadsheetFormulas;
use App\Support\Export\ExportColumn;
use App\Support\Export\ExportValueType;
use App\Support\Export\ListExport;
use DateTimeInterface;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Options;
use OpenSpout\Writer\CSV\Writer;

/**
 * Liste tanımını CSV dosyasına yazar.
 *
 * XLSX zaten varken CSV'nin ayrı bir işi var: veri başka bir sisteme
 * taşınacağında (muhasebe programı, e-posta aracı, veri ambarı, betik) tablo
 * biçimi değil ham metin isteniyor. Bu yüzden burada ne başlık/altbilgi ne de
 * biçimlendirme var — yalnız başlık satırı ve veri.
 *
 * İki ayar Türkçe Excel'i hedefliyor:
 *
 *  - **BOM**: dosyanın başındaki UTF-8 imzası olmadan Excel dosyayı sistemin
 *    kod sayfasıyla açıyor ve Türkçe harfler bozuluyor.
 *  - **Noktalı virgül ayracı**: ondalık ayracı virgül olan yerel ayarlarda
 *    Excel virgülle ayrılmış dosyayı tek sütuna basıyor.
 *
 * İkisi de yapılandırmadan geliyor; farklı yerel ayara kurulan bir projede
 * `.env` üzerinden değiştirilebilir.
 *
 * Satırlar akış hâlinde yazılır: sonuç kümesinin tamamı hiçbir zaman belleğe
 * alınmaz, yüz binlerce satırda da bellek sabit kalır.
 */
final class CsvExportService
{
    use GuardsSpreadsheetFormulas;

    /**
     * Dosyayı yazar ve yazılan veri satırı sayısını döndürür.
     *
     * @param array<string, mixed> $filters
     */
    public function write(ListExport $export, array $filters, string $path): int
    {
        $columns = $export->columns();

        $writer = new Writer($this->options());
        $writer->openToFile($path);

        $writer->addRow(Row::fromValues(
            array_map(static fn (ExportColumn $column): string => $column->label, $columns),
        ));

        $rowCount = 0;

        $export->eachChunk($filters, (int) config('export.chunk_size', 500), function ($records) use ($writer, $columns, &$rowCount): void {
            foreach ($records as $record) {
                $values = [];

                foreach ($columns as $column) {
                    $values[] = $this->cellValue($column, $record);
                }

                $writer->addRow(Row::fromValues($values));
                ++$rowCount;
            }
        });

        $writer->close();

        return $rowCount;
    }

    private function options(): Options
    {
        return new Options(
            FIELD_DELIMITER: $this->delimiter(),
            SHOULD_ADD_BOM: (bool) config('export.csv_bom', true),
            // Windows satır sonu: Excel ve eski araçlar tek \n gördüğünde
            // hücre içindeki satır sonlarını kayıt sonu sanabiliyor.
            EOL: "\r\n",
        );
    }

    /** @return non-empty-string */
    private function delimiter(): string
    {
        $delimiter = (string) config('export.csv_delimiter', ';');

        return $delimiter === '' ? ';' : $delimiter;
    }

    /**
     * Hücrenin metin karşılığı.
     *
     * CSV'de tip yoktur; her şey metindir. Bu yüzden tarih ve sayı burada
     * okunabilir biçime çevrilir — XLSX'te bunu hücre biçimi yapıyordu.
     */
    private function cellValue(ExportColumn $column, mixed $record): string
    {
        $value = $column->resolve($record);

        return match (true) {
            $value === null                     => '',
            $value instanceof DateTimeInterface => $value->format($column->type->phpDateFormat()),
            is_bool($value)                     => $value ? 'Evet' : 'Hayır',
            default                             => $this->scalarValue($column, $value),
        };
    }

    /**
     * Sayılar yerel ondalık ayracıyla yazılır: alan ayracı noktalı virgül
     * olduğu için "12,5" iki sütuna bölünmez ve Türkçe Excel'de sayı olarak
     * okunur. Ondalık ayracı ile alan ayracı aynı olacak şekilde
     * yapılandırılırsa dönüşüm yapılmaz — bölünen hücre, yanlış okunan
     * sayıdan beterdir.
     */
    private function scalarValue(ExportColumn $column, mixed $value): string
    {
        $text = (string) $value;

        if ($column->type === ExportValueType::Number && is_numeric($value)) {
            $separator = (string) config('export.csv_decimal_separator', ',');

            return $separator === $this->delimiter()
                ? $text
                : str_replace('.', $separator, $text);
        }

        return $this->neutralizeFormula($text);
    }
}
