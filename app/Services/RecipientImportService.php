<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\PersonName;
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
 * name — "e-posta", "email", "ad", "soyad" — and falls back to "the column that
 * actually holds addresses" when there is no usable header.
 *
 * Ad ve soyad ayrı sütunlarda bekleniyor. Tek sütunda "Ad Soyad" veren eski
 * dosyalar da okunuyor; o durumda son kelime soyad sayılarak bölünüyor.
 */
final class RecipientImportService
{
    private const MAX_ROWS = 50_000;

    /**
     * Önizleme ekranında elle düzeltilebilecek satır sayısının tavanı.
     *
     * Tarayıcıda binlerce satırı tek tek düzenletmek gerçekçi değil; bu sayıyı
     * aşan dosyalar önizlemede kesiliyor ve kullanıcıya söyleniyor.
     */
    private const PREVIEW_MAX_ROWS = 1_000;

    /**
     * @var array<int, string>
     */
    private const EMAIL_HEADERS = [
        'email', 'e-mail', 'e_mail', 'eposta', 'e-posta', 'e posta',
        'mail', 'mail adresi', 'eposta adresi', 'e-posta adresi',
    ];

    /**
     * Ad sütunu — tek başına "ad", yani soyadı ayrı gelen dosyalarda.
     *
     * @var array<int, string>
     */
    private const FIRST_NAME_HEADERS = [
        'ad', 'adi', 'adı', 'isim', 'first name', 'firstname', 'first_name', 'name',
    ];

    /**
     * @var array<int, string>
     */
    private const LAST_NAME_HEADERS = [
        'soyad', 'soyadi', 'soyadı', 'soyisim', 'last name', 'lastname',
        'last_name', 'surname',
    ];

    /**
     * Ad ve soyadın tek sütunda geldiği dosyalar. Panel artık ayrı sütunlu
     * şablon veriyor ama elde eski dosyalar var; bunlar okunmaya devam ediyor
     * ve son kelime soyad sayılarak bölünüyor.
     *
     * @var array<int, string>
     */
    private const FULL_NAME_HEADERS = [
        'ad soyad', 'adsoyad', 'ad-soyad', 'ad_soyad', 'isim soyisim',
        'full name', 'fullname', 'full_name', 'adi soyadi', 'adı soyadı',
    ];

    /**
     * @return array{rows: array<int, array{first_name: ?string, last_name: ?string, email: string}>, total: int, invalid: int}
     */
    public function parse(UploadedFile $file): array
    {
        return $this->extract($this->readFile($file));
    }

    /**
     * Yüklenen dosyayı satır dizisine çevirir.
     *
     * parse() ve preview() aynı okumayı paylaşıyor; biçim desteği tek yerde
     * kalsın diye ayrıldı.
     *
     * @return array<int, array<int, string>>
     */
    private function readFile(UploadedFile $file): array
    {
        $extension = mb_strtolower($file->getClientOriginalExtension());
        $path = $file->getRealPath();

        if ($path === false) {
            throw new RuntimeException('Yüklenen dosya okunamadı.');
        }

        return match ($extension) {
            'csv', 'txt'  => $this->read(new CsvReader($this->csvOptions($path)), $path),
            'xlsx'        => $this->read(new XlsxReader(), $path),
            default       => throw new RuntimeException(
                "Desteklenmeyen dosya biçimi: .{$extension}. Excel (.xlsx) veya CSV yükleyin."
            ),
        };
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
     * Önizleme için dosyayı satır satır, kararıyla birlikte okur.
     *
     * parse() geçersiz satırı sessizce atıyor; içe aktarma ekranında ise
     * kullanıcının onu görüp düzeltmesi gerekiyor — "12 kayıt atlandı"
     * cümlesi hangi kaydın neden atlandığını söylemiyor, dosyayı Excel'de
     * açıp aramaktan başka yol bırakmıyordu.
     *
     * parse() bilerek değiştirilmedi: kampanya tarafı onu kullanıyor ve
     * oradaki davranışın değişmesi için bir sebep yok.
     *
     * @return array{rows: array<int, array{first_name: ?string, last_name: ?string, email: string, valid: bool, reason: ?string}>, total: int, valid: int, invalid: int, truncated: bool}
     */
    public function preview(UploadedFile $file): array
    {
        $table = $this->readFile($file);

        if ($table === []) {
            throw new RuntimeException('Dosya boş görünüyor.');
        }

        $columns = $this->resolveColumns($table);

        if ($columns['email'] === null) {
            throw new RuntimeException(
                'Dosyada e-posta sütunu bulunamadı. Başlık satırına "E-posta" yazabilir, '
                . 'adresleri ilk sütuna koyabilir veya örnek şablonu indirebilirsiniz.'
            );
        }

        $rows = [];
        $seen = [];
        $valid = 0;
        $invalid = 0;
        $truncated = false;

        foreach (array_slice($table, $columns['start']) as $row) {
            $raw = trim($row[$columns['email']] ?? '');
            $names = $this->namesFor($row, $columns);

            // Tamamen boş satır dosyanın sonundaki artık; kullanıcıya
            // düzeltmesi gereken bir şeymiş gibi gösterilmiyor.
            if ($raw === '' && $names['first_name'] === null && $names['last_name'] === null) {
                continue;
            }

            if (count($rows) >= self::PREVIEW_MAX_ROWS) {
                $truncated = true;

                break;
            }

            $email = mb_strtolower($raw);
            $reason = match (true) {
                $email === ''                                       => 'E-posta boş',
                ! filter_var($email, FILTER_VALIDATE_EMAIL)         => 'Geçersiz e-posta biçimi',
                isset($seen[$email])                                => 'Dosyada tekrar ediyor',
                default                                             => null,
            };

            if ($reason === null) {
                $seen[$email] = true;
                $valid++;
            } else {
                $invalid++;
            }

            $rows[] = $names + [
                'email'  => $email,
                'valid'  => $reason === null,
                'reason' => $reason,
            ];
        }

        if ($rows === []) {
            throw new RuntimeException('Dosyada okunabilir satır bulunamadı.');
        }

        return [
            'rows'      => $rows,
            'total'     => count($rows),
            'valid'     => $valid,
            'invalid'   => $invalid,
            'truncated' => $truncated,
        ];
    }

    /**
     * @param array<int, array<int, string>> $table
     * @return array{rows: array<int, array{first_name: ?string, last_name: ?string, email: string}>, total: int, invalid: int}
     */
    private function extract(array $table): array
    {
        if ($table === []) {
            throw new RuntimeException('Dosya boş görünüyor.');
        }

        $columns = $this->resolveColumns($table);

        if ($columns['email'] === null) {
            throw new RuntimeException(
                'Dosyada e-posta sütunu bulunamadı. Başlık satırına "E-posta" yazabilir, '
                . 'adresleri ilk sütuna koyabilir veya örnek şablonu indirebilirsiniz.'
            );
        }

        $rows = [];
        $seen = [];
        $invalid = 0;

        foreach (array_slice($table, $columns['start']) as $row) {
            $email = mb_strtolower(trim($row[$columns['email']] ?? ''));

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

            $rows[] = $this->namesFor($row, $columns) + ['email' => $email];
        }

        if ($rows === []) {
            throw new RuntimeException('Dosyada geçerli e-posta adresi bulunamadı.');
        }

        return ['rows' => $rows, 'total' => count($rows), 'invalid' => $invalid];
    }

    /**
     * Bir satırdan ad ve soyadı çıkarır.
     *
     * Ayrı sütunlar varsa doğrudan okunur; yalnızca birleşik bir isim sütunu
     * varsa son kelime soyad sayılarak bölünür.
     *
     * @param array<int, string> $row
     * @param array{email: ?int, first: ?int, last: ?int, full: ?int, start: int} $columns
     * @return array{first_name: ?string, last_name: ?string}
     */
    private function namesFor(array $row, array $columns): array
    {
        if ($columns['first'] !== null || $columns['last'] !== null) {
            $first = $columns['first'] !== null ? trim($row[$columns['first']] ?? '') : '';
            $last = $columns['last'] !== null ? trim($row[$columns['last']] ?? '') : '';

            return [
                'first_name' => $first !== '' ? $first : null,
                'last_name'  => $last !== '' ? $last : null,
            ];
        }

        if ($columns['full'] !== null) {
            return PersonName::split($row[$columns['full']] ?? '');
        }

        return ['first_name' => null, 'last_name' => null];
    }

    /**
     * Hangi sütunun ne olduğunu bulur.
     *
     * Başlıklar ada göre eşleşiyor; ayrı Ad/Soyad sütunları birleşik "Ad Soyad"
     * sütununa tercih ediliyor. Kullanılabilir başlık yoksa adresi taşıyan sütun
     * aranıyor ve ilk satır da veri sayılıyor.
     *
     * @param array<int, array<int, string>> $table
     * @return array{email: ?int, first: ?int, last: ?int, full: ?int, start: int}
     */
    private function resolveColumns(array $table): array
    {
        $header = array_map(
            static fn (string $cell): string => mb_strtolower(trim($cell)),
            $table[0] ?? [],
        );

        $columns = ['email' => null, 'first' => null, 'last' => null, 'full' => null, 'start' => 0];

        foreach ($header as $index => $label) {
            if ($columns['email'] === null && in_array($label, self::EMAIL_HEADERS, true)) {
                $columns['email'] = $index;

                continue;
            }

            // "Ad Soyad" başlığı "ad" listesinde de geçmesin diye birleşik
            // başlıklar önce sınanıyor.
            if ($columns['full'] === null && in_array($label, self::FULL_NAME_HEADERS, true)) {
                $columns['full'] = $index;

                continue;
            }

            if ($columns['first'] === null && in_array($label, self::FIRST_NAME_HEADERS, true)) {
                $columns['first'] = $index;

                continue;
            }

            if ($columns['last'] === null && in_array($label, self::LAST_NAME_HEADERS, true)) {
                $columns['last'] = $index;
            }
        }

        if ($columns['email'] !== null) {
            $columns['start'] = 1;

            return $columns;
        }

        // Kullanılabilir başlık yok: adresleri taşıyan sütunu bul ve ilk satırı
        // da veri say.
        foreach ($table[0] ?? [] as $index => $cell) {
            if (filter_var(trim($cell), FILTER_VALIDATE_EMAIL)) {
                $columns['email'] = $index;
                // Başlıksız dosyada isim sütunu tahmin ediliyor; tek sütun
                // olduğu varsayımıyla birleşik okunuyor.
                $columns['full'] = $index === 0 ? (count($table[0]) > 1 ? 1 : null) : 0;

                return $columns;
            }
        }

        return $columns;
    }

    /**
     * Write the sample file the panel offers, so nobody has to guess the layout.
     */
    public function writeTemplate(string $path): void
    {
        $writer = new XlsxWriter();
        $writer->openToFile($path);

        $header = (new Style())->withFontBold(true)->withFontSize(12);

        // Ad ve soyad ayrı sütunlarda: kayıtlarda da ayrı tutuluyorlar, şablon
        // tek sütun önerirse herkes birleşik dosya hazırlıyor.
        $writer->addRow(Row::fromValuesWithStyle(['Ad', 'Soyad', 'E-posta'], $header));

        foreach ([
            ['Ahmet', 'Yılmaz', 'ahmet@ornek.com'],
            ['Ayşe', 'Demir', 'ayse@ornek.com'],
            ['Mehmet', 'Kaya', 'mehmet@ornek.com'],
        ] as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();
    }
}
