<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use OpenSpout\Reader\CSV\Options as CsvReaderOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use RuntimeException;
use Throwable;

/**
 * Reads a recipient list out of an uploaded Excel or CSV file, and writes the
 * sample file the panel offers for download.
 *
 * OpenSpout streams rather than loading the whole sheet into memory, which is
 * what makes a list of tens of thousands of addresses safe on shared hosting.
 *
 * Nobody keeps their columns in a fixed order, so the header row is matched by
 * name — "e-posta", "email", "ad", "isim" — and falls back to "the column that
 * actually holds addresses" when there is no usable header.
 */
final class RecipientImportService
{
    private const MAX_ROWS = 50_000;

    /**
     * @var array<int, string>
     */
    private const EMAIL_HEADERS = [
        'email', 'e-mail', 'e_mail', 'eposta', 'e-posta', 'e posta',
        'mail', 'mail adresi', 'eposta adresi', 'e-posta adresi',
    ];

    /**
     * @var array<int, string>
     */
    private const NAME_HEADERS = [
        'name', 'ad', 'isim', 'ad soyad', 'adsoyad', 'ad-soyad',
        'isim soyisim', 'full name', 'fullname', 'adi soyadi', 'adı soyadı',
    ];

    /**
     * @return array{rows: array<int, array{name: ?string, email: string}>, total: int, invalid: int}
     */
    public function parse(UploadedFile $file): array
    {
        $extension = mb_strtolower($file->getClientOriginalExtension());
        $path = $file->getRealPath();

        if ($path === false) {
            throw new RuntimeException('Yüklenen dosya okunamadı.');
        }

        $table = match ($extension) {
            'csv', 'txt'  => $this->read(new CsvReader($this->csvOptions($path)), $path),
            'xlsx'        => $this->read(new XlsxReader(), $path),
            default       => throw new RuntimeException(
                "Desteklenmeyen dosya biçimi: .{$extension}. Excel (.xlsx) veya CSV yükleyin."
            ),
        };

        return $this->extract($table);
    }

    /**
     * Excel on a Turkish locale writes CSV with semicolons, so the separator is
     * detected from the first line rather than assumed.
     */
    private function csvOptions(string $path): CsvReaderOptions
    {
        $delimiter = ',';
        $handle = fopen($path, 'rb');

        if ($handle !== false) {
            $firstLine = (string) fgets($handle);
            fclose($handle);

            if (substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
                $delimiter = ';';
            }
        }

        // Options is readonly, so the delimiter goes in through the constructor.
        return new CsvReaderOptions(FIELD_DELIMITER: $delimiter);
    }

    /**
     * @param CsvReader|XlsxReader $reader
     * @return array<int, array<int, string>>
     */
    private function read(object $reader, string $path): array
    {
        $rows = [];

        try {
            $reader->open($path);

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    if (count($rows) >= self::MAX_ROWS) {
                        break 2;
                    }

                    $rows[] = array_map(
                        static fn ($value): string => is_scalar($value) ? trim((string) $value) : '',
                        $row->toArray(),
                    );
                }

                break; // only the first sheet
            }
        } catch (Throwable $e) {
            throw new RuntimeException('Dosya okunamadı: ' . $e->getMessage(), previous: $e);
        } finally {
            $reader->close();
        }

        // A UTF-8 BOM would otherwise glue itself to the first header cell and
        // stop it matching "email".
        if (isset($rows[0][0])) {
            $rows[0][0] = preg_replace('/^\xEF\xBB\xBF/', '', $rows[0][0]) ?? $rows[0][0];
        }

        return $rows;
    }

    /**
     * @param array<int, array<int, string>> $table
     * @return array{rows: array<int, array{name: ?string, email: string}>, total: int, invalid: int}
     */
    private function extract(array $table): array
    {
        if ($table === []) {
            throw new RuntimeException('Dosya boş görünüyor.');
        }

        [$emailIndex, $nameIndex, $startRow] = $this->resolveColumns($table);

        if ($emailIndex === null) {
            throw new RuntimeException(
                'Dosyada e-posta sütunu bulunamadı. Başlık satırına "E-posta" yazabilir, '
                . 'adresleri ilk sütuna koyabilir veya örnek şablonu indirebilirsiniz.'
            );
        }

        $rows = [];
        $seen = [];
        $invalid = 0;

        foreach (array_slice($table, $startRow) as $row) {
            $email = mb_strtolower(trim($row[$emailIndex] ?? ''));

            if ($email === '') {
                continue;
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $invalid++;

                continue;
            }

            if (isset($seen[$email])) {
                continue;
            }

            $seen[$email] = true;
            $name = $nameIndex !== null ? trim($row[$nameIndex] ?? '') : '';

            $rows[] = ['name' => $name !== '' ? $name : null, 'email' => $email];
        }

        if ($rows === []) {
            throw new RuntimeException('Dosyada geçerli e-posta adresi bulunamadı.');
        }

        return ['rows' => $rows, 'total' => count($rows), 'invalid' => $invalid];
    }

    /**
     * @param array<int, array<int, string>> $table
     * @return array{0: ?int, 1: ?int, 2: int} email index, name index, first data row
     */
    private function resolveColumns(array $table): array
    {
        $header = array_map(
            static fn (string $cell): string => mb_strtolower(trim($cell)),
            $table[0] ?? [],
        );

        $emailIndex = null;
        $nameIndex = null;

        foreach ($header as $index => $label) {
            if ($emailIndex === null && in_array($label, self::EMAIL_HEADERS, true)) {
                $emailIndex = $index;
            }

            if ($nameIndex === null && in_array($label, self::NAME_HEADERS, true)) {
                $nameIndex = $index;
            }
        }

        if ($emailIndex !== null) {
            return [$emailIndex, $nameIndex, 1];
        }

        // No usable header: find the column that actually holds addresses and
        // treat every row as data, including the first.
        foreach ($table[0] ?? [] as $index => $cell) {
            if (filter_var(trim($cell), FILTER_VALIDATE_EMAIL)) {
                $nameGuess = $index === 0 ? (count($table[0]) > 1 ? 1 : null) : 0;

                return [$index, $nameGuess, 0];
            }
        }

        return [null, null, 0];
    }

    /**
     * Write the sample file the panel offers, so nobody has to guess the layout.
     */
    public function writeTemplate(string $path): void
    {
        $writer = new XlsxWriter();
        $writer->openToFile($path);

        $header = (new Style())->withFontBold(true)->withFontSize(12);

        $writer->addRow(Row::fromValuesWithStyle(['Ad Soyad', 'E-posta'], $header));

        foreach ([
            ['Ahmet Yılmaz', 'ahmet@ornek.com'],
            ['Ayşe Demir', 'ayse@ornek.com'],
            ['Mehmet Kaya', 'mehmet@ornek.com'],
        ] as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();
    }
}
