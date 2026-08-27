<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Support\Export\ExportColumn;
use App\Support\Export\ExportValueType;
use App\Support\Export\ListExport;
use DateTimeInterface;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Options\HeaderFooter;
use OpenSpout\Writer\XLSX\Options\PageOrientation;
use OpenSpout\Writer\XLSX\Options\PageSetup;
use OpenSpout\Writer\XLSX\Options\PaperSize;
use OpenSpout\Writer\XLSX\Properties;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Liste tanımını XLSX dosyasına yazar.
 *
 * Dosya, açılır açılmaz süzülebilir olsun diye kurulur: başlıklar ilk satırda,
 * veri hemen altında, tüm alan otomatik süzgeç kapsamında ve başlık satırı
 * donmuş. Proje ve belge adı veri alanına yazılmaz — orası tablonun kendisidir;
 * bu bilgi yazdırma altbilgisine ve dosya özelliklerine gider.
 *
 * Satırlar akış hâlinde yazılır: sonuç kümesinin tamamı hiçbir zaman belleğe
 * alınmaz, yüz binlerce satırda da bellek sabit kalır.
 */
final class ExcelExportService
{
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

        $writer = new Writer($this->options($title, $project));
        $writer->openToFile($path);

        $sheet = $writer->getCurrentSheet();
        $sheet->setName($this->sheetName($title));
        // Başlık satırı ekranda sabit kalsın: uzun listede hangi sütunun ne
        // olduğunu görmek için başa dönmek gerekmemeli.
        $sheet->setSheetView(new SheetView(freezeRow: 2));

        foreach ($columns as $index => $column) {
            $sheet->setColumnWidth($column->weight, $index + 1);
        }

        $writer->addRow(Row::fromValuesWithStyle(
            array_map(static fn (ExportColumn $column): string => $column->label, $columns),
            (new Style())
                ->withFontBold(true)
                ->withCellVerticalAlignment(CellVerticalAlignment::CENTER),
        ));

        $columnStyles = $this->columnStyles($columns);
        $rowCount = 0;

        $export->query($filters)
            ->chunk((int) config('export.chunk_size', 500), function ($records) use ($writer, $columns, $columnStyles, &$rowCount): void {
                foreach ($records as $record) {
                    $values = [];

                    foreach ($columns as $column) {
                        $values[] = $this->cellValue($column, $record);
                    }

                    $writer->addRow(Row::fromValuesWithStyles($values, $columnStyles));
                    ++$rowCount;
                }
            });

        // Süzgeç aralığı ancak satırlar yazıldıktan sonra bilinir. Sayı için
        // ayrıca sorgu atmıyoruz; yazarken tutulan sayaç zaten elimizde.
        if ($rowCount > 0) {
            $sheet->setAutoFilter(new AutoFilter(0, 1, count($columns) - 1, $rowCount + 1));
        }

        $writer->close();

        return $rowCount;
    }

    private function options(string $title, string $project): Options
    {
        return new Options(
            tempFolder: (string) config('export.temp_path'),
            // Yazdırma altbilgisi: PDF'teki footer'ın Excel karşılığı. Solda
            // proje, ortada belge adı, sağda sayfa numarası.
            headerFooter: new HeaderFooter(
                oddFooter: '&L' . $this->escapeHeaderFooter($project)
                    . '&C' . $this->escapeHeaderFooter($title)
                    . '&R&P / &N',
            ),
            pageSetup: new PageSetup(
                pageOrientation: PageOrientation::LANDSCAPE,
                paperSize: PaperSize::A4,
                fitToWidth: 1,
            ),
            // Proje ve belge adının veri alanını kirletmeden durduğu ikinci
            // yer: dosya özellikleri.
            properties: new Properties(
                title: $title,
                subject: $title,
                application: $project,
                creator: $project,
                lastModifiedBy: $project,
                description: $project . ' — ' . $title,
            ),
        );
    }

    /**
     * Sütun başına hücre biçimi — tarih sütunları metin değil tarih olarak
     * yazılır, yoksa Excel'de sıralama ve süzme alfabetik davranır.
     *
     * @param list<ExportColumn> $columns
     * @return array<int, Style>
     */
    private function columnStyles(array $columns): array
    {
        $styles = [];

        foreach ($columns as $index => $column) {
            $format = $column->type->excelFormat();

            $styles[$index] = $format === null
                ? new Style()
                : (new Style())->withFormat($format);
        }

        return $styles;
    }

    private function cellValue(ExportColumn $column, mixed $record): bool|DateTimeInterface|float|int|string|null
    {
        $value = $column->resolve($record);

        if ($value === null) {
            return '';
        }

        if ($value instanceof DateTimeInterface) {
            return $value;
        }

        if ($column->type === ExportValueType::Number) {
            return is_numeric($value) ? $value + 0 : (string) $value;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        return $this->neutralizeFormula((string) $value);
    }

    /**
     * Formül enjeksiyonuna karşı koruma.
     *
     * "=CMD(...)" gibi bir metin, dosya CSV'ye çevrildiğinde ya da hücre elle
     * onaylandığında formüle döner. Öndeki tek tırnak Excel'e "bu metindir"
     * der; sayısal hücreler zaten sayı tipiyle yazıldığı için buraya düşmez.
     */
    private function neutralizeFormula(string $value): string
    {
        return Str::startsWith($value, ['=', '+', '-', '@', "\t", "\r"])
            ? "'" . $value
            : $value;
    }

    /**
     * Excel sayfa adı en fazla 31 karakter ve bazı işaretleri kabul etmiyor;
     * sınırı aşan ad dosyayı açılamaz hâle getirir.
     */
    private function sheetName(string $title): string
    {
        $name = str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $title);

        return Str::limit(trim($name), 31, '');
    }

    /** Altbilgide "&" biçim kodu başlatır; metindeki & ikiye katlanmalı. */
    private function escapeHeaderFooter(string $value): string
    {
        return str_replace('&', '&&', $value);
    }
}
