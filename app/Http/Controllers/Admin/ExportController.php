<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Export\ExportService;
use App\Support\Export\ExportFormat;
use App\Support\Export\ExportRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Tüm listeleme ekranlarının tek dışa aktarma ucu.
 *
 * Hangi listenin aktarılacağı adres satırındaki anahtardan, hangi kayıtların
 * gireceği ise ekrandaki süzgeçlerden gelir. Denetleyici yalnızca yetkiyi ve
 * sınırı denetler; dosyayı servis üretir.
 */
final class ExportController extends Controller
{
    public function __construct(
        private readonly ExportRegistry $registry,
        private readonly ExportService $exports,
    ) {}

    public function __invoke(Request $request, string $key, string $format): BinaryFileResponse|RedirectResponse
    {
        $exportFormat = ExportFormat::tryFrom($format);

        if ($exportFormat === null) {
            throw new NotFoundHttpException("Tanımsız dışa aktarma biçimi: {$format}");
        }

        $export = $this->registry->get($key);
        $export->authorize();

        $filters = $export->filtersFromRequest($request);

        // Satır tavanı: dosya sessizce kırpılmaz, kullanıcı ne olduğunu öğrenir.
        // Tavanı olan tek biçim PDF; Excel ve CSV akış hâlinde yazıldığı için
        // satır sayısından etkilenmiyor.
        if ($this->exports->exceedsRowLimit($export, $exportFormat, $filters)) {
            $limit = number_format($this->exports->pdfLimit(), 0, ',', '.');

            return back()->with(
                'warning',
                "Bu liste {$exportFormat->label()} sınırını ({$limit} kayıt) aşıyor. "
                . 'Süzgeçleri daraltın ya da Excel/CSV olarak indirin.',
            );
        }

        return $this->exports->download($export, $exportFormat, $filters);
    }
}
