<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessBulkImportRowJob;
use App\Models\AiImagePrompt;
use App\Models\BulkImport;
use App\Services\BulkScheduleImporter;
use App\Services\BulkTsvParser;
use App\Services\InstagramTopicSuggestionService;
use App\Services\TurkishSpecialDaysService;
use App\Services\UploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Toplu Instagram Post Planlama (Bulk Schedule).
 *
 * Endpoint'ler:
 *   GET  /admin/instagram-posts/bulk            → form
 *   GET  /admin/instagram-posts/bulk/template   → Excel şablon indir
 *   POST /admin/instagram-posts/bulk/upload     → Excel (+ZIP) yükle, valida + queue
 *   GET  /admin/instagram-posts/bulk/{id}       → progress sayfası
 *   GET  /admin/instagram-posts/bulk/{id}/json  → AJAX status
 */
final class InstagramPostBulkController extends Controller
{
    public function __construct(
        private readonly BulkScheduleImporter $importer,
        private readonly UploadService $uploadService,
    ) {}

    public function form(TurkishSpecialDaysService $specialDays): View
    {
        $currentYear = (int) now()->format('Y');

        // Hareketli günler için kayıtlı yıllar + bu yıl + sonraki yıl
        $years = array_values(array_unique(array_merge(
            $specialDays->availableMovableYears(),
            [$currentYear, $currentYear + 1]
        )));
        sort($years);

        // Her yılın takvimini upfront yükle (JS year switcher için)
        $specialDaysByYear = [];
        foreach ($years as $y) {
            $specialDaysByYear[$y] = $specialDays->getDaysForYear($y);
        }

        return view('admin.instagram-posts.bulk-import', [
            'maxRows'           => BulkScheduleImporter::MAX_ROWS,
            'recentImports'     => BulkImport::with('author')->latest()->limit(5)->get(),
            'promptTemplates'   => AiImagePrompt::active()->orderBy('sort_order')->orderBy('id')->get(),
            'specialDaysYears'  => $years,
            'specialDaysByYear' => $specialDaysByYear,
            'specialDaysYear'   => $currentYear,
        ]);
    }

    /**
     * Excel şablonunu (.xlsx) indir — header satırı + örnek veri + biçimlendirme.
     */
    public function template(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Instagram Plan');

        $headers = [
            'tarih', 'saat', 'tip', 'konu', 'caption', 'hashtags',
            'gorsel_kaynagi', 'gorsel_degeri', 'facebook', 'prompt_template',
        ];
        $sheet->fromArray($headers, null, 'A1');

        $examples = [
            // 1) Minimum satır → sadece tarih, geri kalan akıllı varsayılanlar
            //    Default gorsel_kaynagi=panel olduğu için post Draft olarak oluşur,
            //    kullanıcı panelden manuel görsel + caption ekleyip planlar.
            ['2026-06-01', '', '', '', '', '', '', '', '', ''],

            // 2) Panel mode + caption + hashtag dolu → Draft, görsel sonradan eklenir
            ['2026-06-02', '09:30', 'image', 'Çiftliğimizden günaydın', 'Yeni günümüze çiftliğimizden bakmak ile başlıyoruz!', 'gunaydin,ciftlik,dogal', 'panel', '', 'evet', ''],

            // 3) image + ai + manuel caption + manuel hashtag (formdan default template kullanılır)
            ['2026-06-03', '10:00', 'image', 'Çiftliğimizden günaydın', 'Yeni günümüze çiftliğimizden başlıyoruz!', 'gunaydin,ciftlik,dogal', 'ai', '', 'evet', ''],

            // 4) image + ai + özel Imagen prompt + override template adı (per-row)
            ['2026-06-04', '12:00', 'image', 'Taze peynir tanıtımı', '', '', 'ai', 'A close-up of fresh white cheese on a wooden board, rustic farm style, natural light', 'evet', 'Ürün Fotoğrafı'],

            // 5) image + url + manuel caption (Facebook'a da gönder)
            ['2026-06-05', '14:00', 'image', '', 'Bayramınız mübarek olsun!', 'bayram,kurban,ailem', 'url', 'https://example.com/banner.jpg', 'evet', ''],

            // 6) image + upload + manuel caption (Facebook'a gönderme)
            ['2026-06-06', '18:00', 'image', '', 'Bugünkü hasattan kareler', 'hasat,koyhayati', 'upload', 'hasat-2026.jpg', 'hayir', ''],

            // 7) reels + url + AI caption (konu dolu, caption boş → AI üretir)
            ['2026-06-07', '20:00', 'reels', 'Sabah inek sağımı süreci', '', '', 'url', 'https://example.com/sagim.mp4', 'evet', ''],

            // 8) reels + upload + manuel caption + manuel hashtag
            ['2026-06-08', '21:00', 'reels', 'Yayık tereyağı yapımı', 'Geleneksel yöntemle yayık tereyağı yapıyoruz!', 'tereyagi,gelenek,koy', 'upload', 'tereyagi-reels.mp4', 'evet', ''],

            // 9) reels + panel — video sonradan panelden yüklenir
            ['2026-06-09', '19:30', 'reels', 'Hafta sonu tarla turu', 'Hafta sonu çiftlik turumuza katılın!', 'tarla,gezi,etkinlik', 'panel', '', 'evet', ''],

            // 10) story + ai + AI caption (Story Facebook'ta paylaşılmaz)
            ['2026-06-10', '08:00', 'story', 'Kahvaltı seti', '', '', 'ai', '', 'hayir', ''],

            // 11) story + url + manuel caption
            ['2026-06-11', '10:00', 'story', '', 'Yarın açıklarız!', '', 'url', 'https://example.com/story-teaser.jpg', 'hayir', ''],

            // 12) story + upload + manuel caption (#hashtag prefix otomatik temizlenir)
            ['2026-06-12', '15:00', 'story', '', 'Yeni ürünümüz raflarda', '#yeniurun, #ciftlik, #dogal', 'upload', 'yeni-urun.jpg', 'hayir', ''],
        ];
        $sheet->fromArray($examples, null, 'A2');

        // Header stillemesi (koyu zemin, beyaz yazı, kalın)
        $lastColLetter = Coordinate::stringFromColumnIndex(count($headers));
        $headerRange = "A1:{$lastColLetter}1";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '198675'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']],
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);

        // Sütun genişliklerini otomatik ayarla
        foreach (range(1, count($headers)) as $col) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setAutoSize(true);
        }

        // Tarih + saat sütunlarını metin olarak biçimlendir (Excel auto-cast'ini engelle)
        $lastRow = count($examples) + 1;
        $sheet->getStyle("A2:A{$lastRow}")->getNumberFormat()->setFormatCode('@');
        $sheet->getStyle("B2:B{$lastRow}")->getNumberFormat()->setFormatCode('@');

        $writer = new Xlsx($spreadsheet);
        $filename = 'instagram-bulk-template.xlsx';

        return new StreamedResponse(
            static function () use ($writer): void {
                $writer->save('php://output');
            },
            200,
            [
                'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control'       => 'max-age=0, no-cache, no-store, must-revalidate',
                'Pragma'              => 'no-cache',
            ],
        );
    }

    /**
     * AJAX preview — Excel'i parse eder, ilk 10 valid satır + tüm hataları
     * JSON olarak döner. Yükleme yapmadan önce kullanıcı validation sonuçlarını
     * görür. ZIP yüklenmesine gerek yoktur (bu adım sadece okuma).
     */
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'excel_file' => [
                'required',
                'file',
                'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,application/zip,application/x-zip-compressed,application/octet-stream',
                'max:5120',
            ],
        ], [
            'excel_file.required'  => 'Excel dosyası zorunlu.',
            'excel_file.mimetypes' => 'Excel dosyası geçersiz format. .xlsx uzantılı dosya yükleyin.',
            'excel_file.max'       => 'Excel en fazla 5 MB olabilir.',
        ]);

        // Defansif: dosya uzantısı .xlsx olmalı
        $excelExt = strtolower($request->file('excel_file')->getClientOriginalExtension());
        if ($excelExt !== 'xlsx') {
            return response()->json([
                'success' => false,
                'message' => "Excel dosyası .xlsx uzantılı olmalı (yüklenen: .{$excelExt})",
            ], 422);
        }

        try {
            $result = $this->importer->parseExcel($request->file('excel_file'));
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        // İlk 10 valid satır + tüm error'lar
        $previewRows = array_slice($result['valid'], 0, 10);

        // Preview tablo için sade veri (UI'da göstermek üzere)
        $rows = array_map(static function (array $row): array {
            return [
                'row_number'      => $row['row_number'] ?? null,
                'tarih'           => $row['tarih'] ?? '',
                'saat'            => $row['saat'] ?? '',
                'tip'             => $row['tip'] ?? '',
                'konu'            => mb_strimwidth((string) ($row['konu'] ?? ''), 0, 60, '…', 'UTF-8'),
                'caption'         => mb_strimwidth((string) ($row['caption'] ?? ''), 0, 60, '…', 'UTF-8'),
                'hashtags'        => is_array($row['hashtags'] ?? null)
                    ? implode(', ', array_slice($row['hashtags'], 0, 5))
                    : '',
                'gorsel_kaynagi'  => $row['gorsel_kaynagi'] ?? '',
                'gorsel_degeri'   => mb_strimwidth((string) ($row['gorsel_degeri'] ?? ''), 0, 40, '…', 'UTF-8'),
                'facebook'        => ! empty($row['facebook']) ? 'evet' : 'hayir',
                'prompt_template' => $row['prompt_template'] ?? '',
            ];
        }, $previewRows);

        return response()->json([
            'success'      => true,
            'total_rows'   => $result['total'],
            'valid_count'  => count($result['valid']),
            'error_count'  => count($result['errors']),
            'preview_rows' => $rows,
            'errors'       => $result['errors'],
            'max_rows'     => BulkScheduleImporter::MAX_ROWS,
        ]);
    }

    /**
     * Konu havuzu önerisi — AI'dan {count} farklı Instagram post konusu üretir.
     *
     * Kullanım: AI Prompt modal'ında "30 Konu Öner" butonu.
     * Cache: 60 saniye (aynı count+theme için tekrar AI çağrısı yapılmaz).
     */
    public function suggestTopics(Request $request, InstagramTopicSuggestionService $service): JsonResponse
    {
        $validated = $request->validate([
            'count' => ['nullable', 'integer', 'min:10', 'max:50'],
            'theme' => ['nullable', 'string', 'max:100'],
        ], [
            'count.integer' => 'Konu sayısı geçerli bir tam sayı olmalı.',
            'count.min'     => 'En az 10 konu istenebilir.',
            'count.max'     => 'En fazla 50 konu istenebilir.',
            'theme.max'     => 'Tema metni en fazla 100 karakter olabilir.',
        ]);

        $result = $service->suggest(
            $validated['count'] ?? 30,
            $validated['theme'] ?? null,
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * AI çıktısı (TSV/SSV) önizleme — yapıştırılan metni parse eder, validation
     * sonuçlarını JSON olarak döner. İndirme/import yapmaz.
     */
    public function fromTsvPreview(Request $request, BulkTsvParser $tsvParser): JsonResponse
    {
        $validated = $request->validate([
            'tsv' => ['required', 'string', 'max:524288'], // 512 KB string limit
        ], [
            'tsv.required' => 'AI çıktısını yapıştırmanız gerekiyor.',
            'tsv.max'      => 'Yapıştırılan metin çok uzun (max 512 KB).',
        ]);

        try {
            $rows = $tsvParser->parse($validated['tsv']);
            $result = $this->importer->parseRows($rows);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $previewRows = array_slice($result['valid'], 0, 10);
        $rows = array_map(static function (array $row): array {
            return [
                'row_number'      => $row['row_number'] ?? null,
                'tarih'           => $row['tarih'] ?? '',
                'saat'            => $row['saat'] ?? '',
                'tip'             => $row['tip'] ?? '',
                'konu'            => mb_strimwidth((string) ($row['konu'] ?? ''), 0, 60, '…', 'UTF-8'),
                'caption'         => mb_strimwidth((string) ($row['caption'] ?? ''), 0, 60, '…', 'UTF-8'),
                'hashtags'        => is_array($row['hashtags'] ?? null)
                    ? implode(', ', array_slice($row['hashtags'], 0, 5))
                    : '',
                'gorsel_kaynagi'  => $row['gorsel_kaynagi'] ?? '',
                'gorsel_degeri'   => mb_strimwidth((string) ($row['gorsel_degeri'] ?? ''), 0, 40, '…', 'UTF-8'),
                'facebook'        => ! empty($row['facebook']) ? 'evet' : 'hayir',
                'prompt_template' => $row['prompt_template'] ?? '',
            ];
        }, $previewRows);

        return response()->json([
            'success'      => true,
            'total_rows'   => $result['total'],
            'valid_count'  => count($result['valid']),
            'error_count'  => count($result['errors']),
            'preview_rows' => $rows,
            'errors'       => $result['errors'],
            'max_rows'     => BulkScheduleImporter::MAX_ROWS,
        ]);
    }

    /**
     * AI çıktısı → .xlsx olarak indir. Kullanıcı yine sayfadan upload eder ama
     * Excel'de Text-to-Columns yapma derdinden kurtulur.
     */
    public function fromTsvDownload(Request $request, BulkTsvParser $tsvParser): StreamedResponse|RedirectResponse
    {
        $validated = $request->validate([
            'tsv' => ['required', 'string', 'max:524288'],
        ]);

        try {
            $rows = $tsvParser->parse($validated['tsv']);
        } catch (\RuntimeException $e) {
            return redirect()->route('admin.instagram-posts.bulk.form')->with('error', $e->getMessage());
        }

        return $this->streamRowsAsXlsx($rows, 'instagram-bulk-' . now()->format('Ymd-His') . '.xlsx');
    }

    /**
     * AI çıktısı → doğrudan içe aktar. Excel adımı atlanır.
     * upload() ile aynı pipeline'ı kullanır ama dosya yerine TSV string okur.
     */
    public function fromTsvImport(Request $request, BulkTsvParser $tsvParser): RedirectResponse
    {
        $validated = $request->validate([
            'tsv'                => ['required', 'string', 'max:524288'],
            'prompt_template_id' => ['nullable', 'integer', 'exists:ai_image_prompts,id'],
        ], [
            'tsv.required'              => 'AI çıktısını yapıştırmanız gerekiyor.',
            'tsv.max'                   => 'Yapıştırılan metin çok uzun (max 512 KB).',
            'prompt_template_id.exists' => 'Seçilen prompt template bulunamadı.',
        ]);

        try {
            $rows   = $tsvParser->parse($validated['tsv']);
            $result = $this->importer->parseRows($rows);
        } catch (\RuntimeException $e) {
            return redirect()->route('admin.instagram-posts.bulk.form')->with('error', $e->getMessage());
        }

        if ($result['total'] === 0) {
            return redirect()->route('admin.instagram-posts.bulk.form')
                ->with('error', 'Yapıştırılan içerikte işlenebilir satır bulunamadı.');
        }

        if (count($result['valid']) === 0) {
            return redirect()->route('admin.instagram-posts.bulk.form')
                ->with('error', 'Tüm satırlar hatalı:')
                ->with('bulk_errors', $result['errors']);
        }

        $bulkImport = BulkImport::create([
            'created_by'         => $request->user()?->id,
            'status'             => BulkImport::STATUS_PENDING,
            'total_rows'         => count($result['valid']),
            'errors'             => $result['errors'] !== [] ? $result['errors'] : null,
            'zip_path'           => null, // TSV akışında ZIP yok
            'prompt_template_id' => $validated['prompt_template_id'] ?? null,
        ]);

        foreach ($result['valid'] as $row) {
            ProcessBulkImportRowJob::dispatch($bulkImport->id, $row);
        }

        return redirect()->route('admin.instagram-posts.bulk.show', $bulkImport->id)
            ->with('success', sprintf(
                'AI çıktısından %d geçerli satır işlemeye alındı. %d satır hata verdi.',
                count($result['valid']),
                count($result['errors']),
            ));
    }

    /**
     * Verilen 2D dizi (header + data) → in-memory xlsx → download stream.
     *
     * @param array<int, array<int, mixed>> $rows
     */
    private function streamRowsAsXlsx(array $rows, string $filename): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Instagram Plan');
        $sheet->fromArray($rows, null, 'A1');

        // Header stillemesi (template() ile aynı görünüm)
        $headerCount = count($rows[0] ?? []);
        if ($headerCount > 0) {
            $lastColLetter = Coordinate::stringFromColumnIndex($headerCount);
            $sheet->getStyle("A1:{$lastColLetter}1")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '198675'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']],
                ],
            ]);
            $sheet->getRowDimension(1)->setRowHeight(24);

            foreach (range(1, $headerCount) as $col) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setAutoSize(true);
            }

            // Tarih + saat sütunlarını metin olarak biçimlendir
            $lastRow = count($rows);
            if ($lastRow > 1) {
                $sheet->getStyle("A2:A{$lastRow}")->getNumberFormat()->setFormatCode('@');
                $sheet->getStyle("B2:B{$lastRow}")->getNumberFormat()->setFormatCode('@');
            }
        }

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(
            static function () use ($writer): void {
                $writer->save('php://output');
            },
            $filename,
            [
                'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control'       => 'no-cache, no-store, must-revalidate',
            ],
        );
    }

    public function upload(Request $request): RedirectResponse
    {
        // mimetypes: bazı sistemler xlsx için generic application/zip veya octet-stream gönderir
        $validated = $request->validate([
            'excel_file' => [
                'required',
                'file',
                'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,application/zip,application/x-zip-compressed,application/octet-stream',
                'max:5120',
            ],
            'zip_file' => [
                'nullable',
                'file',
                'mimetypes:application/zip,application/x-zip-compressed,multipart/x-zip,application/octet-stream',
                'max:102400',
            ],
            'prompt_template_id' => ['nullable', 'integer', 'exists:ai_image_prompts,id'],
        ], [
            'excel_file.required'         => 'Excel dosyası zorunlu.',
            'excel_file.mimetypes'        => 'Excel dosyası geçersiz format. .xlsx uzantılı dosya yükleyin.',
            'excel_file.max'              => 'Excel en fazla 5 MB olabilir.',
            'zip_file.mimetypes'          => 'ZIP dosyası geçersiz format. .zip uzantılı dosya yükleyin.',
            'zip_file.max'                => 'ZIP en fazla 100 MB olabilir.',
            'prompt_template_id.exists'   => 'Seçilen prompt template bulunamadı.',
        ]);

        // Ek defansif kontrol: dosya uzantısı .xlsx olmalı (MIME bypass koruması)
        $excelExt = strtolower($request->file('excel_file')->getClientOriginalExtension());
        if ($excelExt !== 'xlsx') {
            return redirect()
                ->route('admin.instagram-posts.bulk.form')
                ->with('error', "Excel dosyası .xlsx uzantılı olmalı (yüklenen: .{$excelExt})");
        }
        if ($request->hasFile('zip_file')) {
            $zipExt = strtolower($request->file('zip_file')->getClientOriginalExtension());
            if ($zipExt !== 'zip') {
                return redirect()
                    ->route('admin.instagram-posts.bulk.form')
                    ->with('error', "ZIP dosyası .zip uzantılı olmalı (yüklenen: .{$zipExt})");
            }
        }

        // 1. Excel parse + validate
        try {
            $result = $this->importer->parseExcel($request->file('excel_file'));
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('admin.instagram-posts.bulk.form')
                ->with('error', $e->getMessage());
        }

        if ($result['total'] === 0) {
            return redirect()
                ->route('admin.instagram-posts.bulk.form')
                ->with('error', 'Excel içinde işlenebilir satır bulunamadı.');
        }

        // 2. Tüm satırlar hatalıysa rapor + abort
        if (count($result['valid']) === 0) {
            return redirect()
                ->route('admin.instagram-posts.bulk.form')
                ->with('error', 'Excel\'deki tüm satırlar hatalı:')
                ->with('bulk_errors', $result['errors']);
        }

        // 3. ZIP yüklendiyse sakla
        $zipPath = null;
        if ($request->hasFile('zip_file')) {
            $zipPath = $this->uploadService->uploadFile(
                $request->file('zip_file'),
                'bulk-imports',
                'bulk-' . now()->format('Ymd-His'),
            );
        }

        // 4. BulkImport oluştur + her satır için job dispatch
        $bulkImport = BulkImport::create([
            'created_by'         => $request->user()?->id,
            'status'             => BulkImport::STATUS_PENDING,
            'total_rows'         => count($result['valid']),
            'errors'             => $result['errors'] !== [] ? $result['errors'] : null,
            'zip_path'           => $zipPath,
            'prompt_template_id' => $validated['prompt_template_id'] ?? null,
        ]);

        foreach ($result['valid'] as $row) {
            ProcessBulkImportRowJob::dispatch($bulkImport->id, $row);
        }

        return redirect()->route('admin.instagram-posts.bulk.show', $bulkImport->id)
            ->with('success', sprintf(
                '%d geçerli satır işlemeye alındı. %d satır hata verdi.',
                count($result['valid']),
                count($result['errors']),
            ));
    }

    public function show(BulkImport $bulkImport): View
    {
        // Bu bulk import'tan üretilen InstagramPost'ları FK üzerinden temiz çek.
        // aiGeneratedImage: bulk'ta gorsel_kaynagi=ai sırasında üretilen görsel kaydı.
        $posts = \App\Models\InstagramPost::query()
            ->where('bulk_import_id', $bulkImport->id)
            ->with(['additionalImages', 'aiGeneratedImage'])
            ->latest()
            ->get();

        return view('admin.instagram-posts.bulk-status', [
            'bulkImport' => $bulkImport->load(['author', 'promptTemplate']),
            'posts'      => $posts,
        ]);
    }

    /**
     * AJAX: progress polling endpoint.
     */
    public function status(BulkImport $bulkImport): JsonResponse
    {
        $bulkImport->refresh();

        return response()->json([
            'id'              => $bulkImport->id,
            'status'          => $bulkImport->status,
            'total_rows'      => $bulkImport->total_rows,
            'processed_rows'  => $bulkImport->processed_rows,
            'success_count'   => $bulkImport->success_count,
            'fail_count'      => $bulkImport->fail_count,
            'errors'          => $bulkImport->errors ?? [],
            'finished'        => $bulkImport->isFinished(),
            'progress_pct'    => $bulkImport->total_rows > 0
                ? (int) floor(($bulkImport->processed_rows / $bulkImport->total_rows) * 100)
                : 0,
        ]);
    }
}
