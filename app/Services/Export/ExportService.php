<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Services\AuditLogger;
use App\Support\Export\ExportFormat;
use App\Support\Export\ListExport;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Dışa aktarma akışının tek girişi: dosyayı ürettirir, adlandırır, denetim
 * kaydını düşer ve tarayıcıya verir.
 *
 * Dosya önce geçici dizine yazılır, indirme bitince silinir. Doğrudan çıktıya
 * yazmak yerine bunun tercih edilmesinin nedeni, iki yazıcının da (ZIP ve PDF)
 * dosyayı ancak sonunda bütünleyebilmesi.
 */
final class ExportService
{
    public function __construct(
        private readonly ExcelExportService $excel,
        private readonly PdfExportService $pdf,
    ) {}

    /**
     * PDF satır tavanının altında mı? Aşımda dosya üretilmez.
     *
     * @param array<string, mixed> $filters
     */
    public function exceedsPdfLimit(ListExport $export, array $filters): bool
    {
        return $this->pdfLimit() > 0
            && $export->count($filters) > $this->pdfLimit();
    }

    /**
     * Geçerli PDF satır tavanı.
     *
     * Ayarlanan tavan ile sunucunun belleğinin gerçekte kaldırdığı satır
     * sayısından küçük olanı geçerlidir: mPDF sayfaları belge kapanana kadar
     * bellekte tuttuğu için, ayarda 5.000 yazması 128 MB'lık bir hosting'de
     * dosyanın üretileceği anlamına gelmiyor. Küçük olanı seçmek, yarısında
     * ölen bir istek yerine anlaşılır bir uyarı verdiriyor.
     */
    public function pdfLimit(): int
    {
        $configured = (int) config('export.pdf_row_limit', 5000);
        $affordable = $this->memoryAffordableRows();

        return $affordable === null ? $configured : min($configured, $affordable);
    }

    /**
     * Bellek sınırının kaldırdığı yaklaşık satır sayısı; sınır yoksa null.
     */
    private function memoryAffordableRows(): ?int
    {
        $limit = $this->memoryLimitInBytes();

        if ($limit === null) {
            return null;
        }

        $baseline = (int) config('export.pdf_memory_baseline_mb', 60) * 1024 * 1024;
        $perRow = (int) config('export.pdf_memory_per_row_kb', 55) * 1024;

        if ($perRow <= 0) {
            return null;
        }

        // Sınırın tamamı kullanılmaz: isteğin kendi yükü de aynı bellekte.
        $usable = (int) ($limit * 0.85) - $baseline;

        return max(0, (int) floor($usable / $perRow));
    }

    /** memory_limit değerini bayta çevirir; sınırsızsa null. */
    private function memoryLimitInBytes(): ?int
    {
        $raw = trim((string) ini_get('memory_limit'));

        if ($raw === '' || $raw === '-1') {
            return null;
        }

        $value = (int) $raw;

        return match (strtolower(substr($raw, -1))) {
            'g'     => $value * 1024 * 1024 * 1024,
            'm'     => $value * 1024 * 1024,
            'k'     => $value * 1024,
            default => $value,
        };
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function download(ListExport $export, ExportFormat $format, array $filters): BinaryFileResponse
    {
        $path = $this->temporaryPath($format);

        $rowCount = match ($format) {
            ExportFormat::Excel => $this->excel->write($export, $filters, $path),
            ExportFormat::Pdf   => $this->pdf->write($export, $filters, $path),
        };

        $this->recordAudit($export, $format, $filters, $rowCount);

        return response()
            ->download($path, $this->fileName($export, $format), [
                'Content-Type' => $format->mimeType(),
            ])
            ->deleteFileAfterSend();
    }

    /**
     * Dosya adı: proje adı, belge adı ve üretim anı. Aynı listeden farklı
     * zamanlarda alınan dosyalar indirme klasöründe birbirini ezmez.
     */
    public function fileName(ListExport $export, ExportFormat $format): string
    {
        return implode('_', [
            Str::slug((string) config('app.name')),
            Str::slug($export->title()),
            now()->format('Y-m-d-Hi'),
        ]) . '.' . $format->extension();
    }

    private function temporaryPath(ExportFormat $format): string
    {
        $directory = (string) config('export.temp_path');

        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        return $directory . '/' . Str::random(32) . '.' . $format->extension();
    }

    /**
     * Kimin hangi listeyi hangi süzgeçlerle indirdiği denetim kaydına düşer:
     * dışa aktarma, veriyi sistemin dışına çıkaran tek okuma işlemi.
     *
     * @param array<string, mixed> $filters
     */
    private function recordAudit(ListExport $export, ExportFormat $format, array $filters, int $rowCount): void
    {
        AuditLogger::custom(
            $export->title() . ' listesi ' . $format->label() . ' olarak dışa aktarıldı',
            [
                'format'  => $format->value,
                'title'   => $export->title(),
                'filters' => $filters,
                'rows'    => $rowCount,
            ],
        );
    }
}
