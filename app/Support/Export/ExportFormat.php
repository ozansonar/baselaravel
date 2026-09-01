<?php

declare(strict_types=1);

namespace App\Support\Export;

/** Dışa aktarma biçimleri — adres satırındaki değer ve dosya karşılıkları. */
enum ExportFormat: string
{
    case Excel = 'excel';
    case Csv = 'csv';
    case Pdf = 'pdf';

    public function extension(): string
    {
        return match ($this) {
            self::Excel => 'xlsx',
            self::Csv   => 'csv',
            self::Pdf   => 'pdf',
        };
    }

    public function mimeType(): string
    {
        return match ($this) {
            self::Excel => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            // Ayraçlı metin dosyası; charset başlıkta duruyor çünkü dosyanın
            // başındaki BOM'u okumayan araçlar da var.
            self::Csv   => 'text/csv; charset=UTF-8',
            self::Pdf   => 'application/pdf',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Excel => 'Excel',
            self::Csv   => 'CSV',
            self::Pdf   => 'PDF',
        };
    }

    /**
     * Satır sayısı tavanına tabi mi?
     *
     * Excel ve CSV satırları akış hâlinde yazar; bellek satır sayısından
     * bağımsız sabit kalır. mPDF ise sayfaları belge kapanana kadar bellekte
     * tuttuğu için tek sınırlı biçim odur.
     */
    public function hasRowLimit(): bool
    {
        return $this === self::Pdf;
    }
}
