<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;

/**
 * Toplu Instagram post planlama — Excel (.xlsx) parse + validate.
 *
 * Excel formatı (UTF-8, header zorunlu, ilk satır):
 *   tarih | saat | tip | konu | caption | hashtags | gorsel_kaynagi | gorsel_degeri | facebook
 *
 * Akıllı varsayılanlar (boş bırakılan alanlar):
 *   - saat              → 19:00
 *   - tip               → image
 *   - caption           → konu'dan AI üretir (panel mode hariç)
 *   - hashtags          → AI caption'dan alır (panel mode hariç)
 *   - gorsel_kaynagi    → panel (sonradan admin panelden eklenir)
 *   - gorsel_degeri     → konu (AI prompt için) / panel modda boş
 *   - facebook          → evet (Story ise zorla hayır)
 *
 * Görsel kaynağı modları:
 *   - ai     → Imagen ile AI görsel üret (Reels desteklenmez)
 *   - url    → public URL'den indir
 *   - upload → ZIP içindeki dosya adı
 *   - panel  → görsel yüklenmez, post Draft olarak oluşur — admin panelden manuel eklenir
 *
 * Limit:
 *   - Max 50 satır (AI maliyetini sınırla, ~$2 üst sınır)
 *   - Excel max 5 MB
 */
final class BulkScheduleImporter
{
    public const int MAX_ROWS = 50;
    public const int MAX_EXCEL_BYTES = 5_242_880; // 5 MB

    private const array REQUIRED_HEADERS = ['tarih'];
    private const array ALL_HEADERS = [
        'tarih', 'saat', 'tip', 'konu', 'caption', 'hashtags',
        'gorsel_kaynagi', 'gorsel_degeri', 'facebook', 'prompt_template',
    ];
    private const array VALID_TIPS = ['image', 'reels', 'story'];
    private const array VALID_SOURCES = ['ai', 'url', 'upload', 'panel', 'galeri', 'kutuphane'];
    private const string DEFAULT_SOURCE = 'panel';

    /**
     * Excel dosyasını parse et + her satırı validate et.
     *
     * @return array{
     *     valid: list<array<string, mixed>>,
     *     errors: list<array{row: int, message: string}>,
     *     total: int,
     * }
     *
     * @throws RuntimeException
     */
    public function parseExcel(UploadedFile $file): array
    {
        if ($file->getSize() > self::MAX_EXCEL_BYTES) {
            throw new RuntimeException(sprintf(
                'Excel dosyası çok büyük (%d bayt). Maksimum %d MB.',
                $file->getSize(),
                self::MAX_EXCEL_BYTES / 1_048_576,
            ));
        }

        $path = $file->getRealPath() ?: $file->getPathname();

        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
        } catch (ReaderException $e) {
            throw new RuntimeException('Excel dosyası okunamadı: ' . $e->getMessage());
        }

        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);

        return $this->parseRows($rows);
    }

    /**
     * Ham 2D dizi (header + veri) üzerinden validation çalıştırır.
     *
     * Hem Excel'den okunan diziler için (parseExcel) hem TSV'den parse edilen
     * diziler için (BulkTsvParser) ortak validation pipeline sağlar.
     *
     * @param array<int, array<int, mixed>> $rows İlk satır header
     *
     * @return array{valid: list<array<string, mixed>>, errors: list<array{row: int, message: string}>, total: int}
     *
     * @throws RuntimeException
     */
    public function parseRows(array $rows): array
    {
        // Sondaki tamamen boş satırları kırp
        while ($rows !== [] && $this->isEmptyRow(end($rows))) {
            array_pop($rows);
        }

        if ($rows === []) {
            throw new RuntimeException('Veri boş.');
        }

        $headerRow = array_shift($rows);
        $headers = $this->normalizeHeaders($headerRow);

        foreach (self::REQUIRED_HEADERS as $required) {
            if (! in_array($required, $headers, true)) {
                throw new RuntimeException("Veride '{$required}' sütunu eksik. Şablonu indirip kullanın.");
            }
        }

        $valid = [];
        $errors = [];
        $rowNum = 1; // Header satırı = 1, ilk veri satırı = 2

        foreach ($rows as $row) {
            $rowNum++;

            if ($this->isEmptyRow($row)) {
                continue;
            }

            if (count($valid) + count($errors) >= self::MAX_ROWS) {
                $errors[] = [
                    'row' => $rowNum,
                    'message' => sprintf('Maksimum %d satır sınırı aşıldı, kalan satırlar atlandı.', self::MAX_ROWS),
                ];
                break;
            }

            $assoc = $this->mapRowToAssoc($headers, $row);
            $validation = $this->validateRow($assoc, $rowNum);

            if ($validation['valid']) {
                $valid[] = $validation['data'];
            } else {
                $errors[] = [
                    'row' => $rowNum,
                    'message' => $validation['message'],
                ];
            }
        }

        return [
            'valid'  => $valid,
            'errors' => $errors,
            'total'  => count($valid) + count($errors),
        ];
    }

    /**
     * @param array<int, mixed> $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, mixed> $row
     * @return list<string>
     */
    private function normalizeHeaders(array $row): array
    {
        $headers = [];
        foreach ($row as $cell) {
            $headers[] = trim(mb_strtolower((string) $cell));
        }

        return $headers;
    }

    /**
     * @param list<string> $headers
     * @param array<int, mixed> $row
     * @return array<string, string>
     */
    private function mapRowToAssoc(array $headers, array $row): array
    {
        $assoc = [];
        foreach ($headers as $i => $name) {
            $value = $row[$i] ?? '';

            // Excel tarih hücreleri serial number olarak gelir → Y-m-d formatına çevir
            if ($name === 'tarih' && is_numeric($value) && $value > 0) {
                try {
                    $value = ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
                } catch (\Throwable) {
                    // pass through; validateRow yakalar
                }
            }

            // Saat hücresi 0-1 arası ondalık veya datetime serial olabilir
            if ($name === 'saat' && is_numeric($value)) {
                $num = (float) $value;
                if ($num >= 0 && $num < 1) {
                    $totalMinutes = (int) round($num * 24 * 60);
                    $value = sprintf('%02d:%02d', intdiv($totalMinutes, 60), $totalMinutes % 60);
                } elseif ($num >= 1) {
                    try {
                        $value = ExcelDate::excelToDateTimeObject($num)->format('H:i');
                    } catch (\Throwable) {
                        // pass through
                    }
                }
            }

            $assoc[$name] = trim((string) $value);
        }

        // Bilinmeyen sütunları temizle, eksik olanları boş string yap
        $clean = [];
        foreach (self::ALL_HEADERS as $col) {
            $clean[$col] = $assoc[$col] ?? '';
        }

        return $clean;
    }

    /**
     * Satır validation + normalizasyon (akıllı varsayılanlar).
     *
     * @param array<string, string> $row
     * @return array{valid: bool, message?: string, data?: array<string, mixed>}
     */
    private function validateRow(array $row, int $rowNum): array
    {
        // Tarih (zorunlu)
        $tarihRaw = $row['tarih'];
        if ($tarihRaw === '') {
            return ['valid' => false, 'message' => 'tarih sütunu boş olamaz.'];
        }

        try {
            $tarih = Carbon::parse($tarihRaw)->format('Y-m-d');
        } catch (\Throwable) {
            return ['valid' => false, 'message' => "tarih geçersiz: '{$tarihRaw}' (örn. 2026-05-01)"];
        }

        // Saat (default 19:00)
        $saat = $row['saat'] !== '' ? $row['saat'] : '19:00';
        if (! preg_match('/^\d{1,2}:\d{2}$/', $saat)) {
            return ['valid' => false, 'message' => "saat geçersiz: '{$saat}' (HH:MM bekleniyor)"];
        }

        try {
            $scheduledAt = Carbon::parse("{$tarih} {$saat}");
        } catch (\Throwable) {
            return ['valid' => false, 'message' => "tarih + saat birleşimi geçersiz."];
        }

        // Geçmiş tarihi engelleme — taslağa düşürebiliriz; şimdilik 5 dk öncesinden eski olanlar reddedilir
        if ($scheduledAt->lt(now()->subMinutes(5))) {
            return ['valid' => false, 'message' => "yayın zamanı geçmişte ({$scheduledAt->format('d.m.Y H:i')})"];
        }

        // Tip (default image)
        $tip = $row['tip'] !== '' ? mb_strtolower($row['tip']) : 'image';
        if (! in_array($tip, self::VALID_TIPS, true)) {
            return ['valid' => false, 'message' => "tip geçersiz: '{$tip}' (image|reels|story)"];
        }

        // Konu / caption — en az birinin dolu olması veya gorsel_degeri'nin ai için anlamlı olması beklenir
        $konu = $row['konu'];
        $caption = $row['caption'];

        // Görsel kaynağı (default panel — sonradan admin panelden manuel eklenir)
        $source = $row['gorsel_kaynagi'] !== '' ? mb_strtolower($row['gorsel_kaynagi']) : self::DEFAULT_SOURCE;
        if (! in_array($source, self::VALID_SOURCES, true)) {
            return ['valid' => false, 'message' => "gorsel_kaynagi geçersiz: '{$source}' (ai|url|upload|panel|galeri|kutuphane)"];
        }

        // Reels için AI desteklenmiyor (Imagen video üretmiyor)
        if ($tip === 'reels' && $source === 'ai') {
            return ['valid' => false, 'message' => 'Reels için gorsel_kaynagi=ai desteklenmiyor (video AI üretim yok). url, upload veya panel kullanın.'];
        }

        $sourceValue = $row['gorsel_degeri'];

        // url mode: image_value zorunlu + format kontrolü (basit)
        if ($source === 'url' && $sourceValue === '') {
            return ['valid' => false, 'message' => 'gorsel_kaynagi=url ise gorsel_degeri zorunlu (public URL).'];
        }
        if ($source === 'url' && ! filter_var($sourceValue, FILTER_VALIDATE_URL)) {
            return ['valid' => false, 'message' => "gorsel_degeri geçerli bir URL değil: '{$sourceValue}'"];
        }

        // upload mode: image_value zorunlu (zip içindeki dosya adı)
        if ($source === 'upload' && $sourceValue === '') {
            return ['valid' => false, 'message' => 'gorsel_kaynagi=upload ise gorsel_degeri zorunlu (ZIP içindeki dosya adı).'];
        }
        if ($source === 'upload' && (str_contains($sourceValue, '..') || str_starts_with($sourceValue, '/'))) {
            return ['valid' => false, 'message' => 'gorsel_degeri güvenli olmayan bir yol içeriyor (../ veya / başlangıç).'];
        }

        // ai mode: en az konu veya gorsel_degeri (custom prompt) gerekli
        if ($source === 'ai' && $konu === '' && $sourceValue === '') {
            return ['valid' => false, 'message' => 'gorsel_kaynagi=ai için en az konu veya gorsel_degeri (özel prompt) gerekli.'];
        }

        // panel mode: hiçbir şey zorunlu değil — gorsel_degeri görmezden gelinir,
        //             post Draft olarak oluşur, kullanıcı panelden görsel + caption ekler.
        // Bu yüzden caption/konu zorunluluğu sadece non-panel modlar için geçerli.
        if ($source !== 'panel' && $caption === '' && $konu === '') {
            return ['valid' => false, 'message' => 'caption ve konu ikisi birden boş olamaz (AI caption için en az konu lazım).'];
        }

        // Hashtag: virgülle ayrılmış string → array
        $hashtags = [];
        if ($row['hashtags'] !== '') {
            $parts = preg_split('/[\s,]+/u', $row['hashtags']) ?: [];
            foreach ($parts as $p) {
                $tag = trim($p, " \t\n\r\0\x0B#");
                if ($tag !== '') {
                    $hashtags[] = $tag;
                }
            }
            $hashtags = array_slice(array_unique($hashtags), 0, 30);
        }

        // Facebook: Story için zorla false
        $facebookRaw = mb_strtolower($row['facebook']);
        $facebook = in_array($facebookRaw, ['', 'evet', '1', 'true', 'yes'], true);
        if ($facebookRaw === 'hayir' || $facebookRaw === 'hayır' || $facebookRaw === '0' || $facebookRaw === 'false') {
            $facebook = false;
        }
        if ($tip === 'story') {
            $facebook = false; // Story FB'de yok
        }

        // Prompt template (opsiyonel, per-row override) — boş ise bulk'in default'u
        // kullanılır (form-level seçim). Burada sadece adı taşırız, ID resolve
        // job tarafında yapılır (DB lookup gerekiyor).
        $promptTemplateName = trim((string) ($row['prompt_template'] ?? ''));

        return [
            'valid' => true,
            'data' => [
                'tarih'           => $tarih,
                'saat'            => $saat,
                'scheduled_at'    => $scheduledAt->toDateTimeString(),
                'tip'             => $tip,
                'konu'            => $konu,
                'caption'         => $caption,
                'hashtags'        => $hashtags,
                'gorsel_kaynagi'  => $source,
                'gorsel_degeri'   => $sourceValue,
                'facebook'        => $facebook,
                'prompt_template' => $promptTemplateName,
                'row_number'      => $rowNum,
            ],
        ];
    }
}
