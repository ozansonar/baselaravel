<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * AI çıktısındaki TSV (tab-separated) ya da SSV (multi-space) tablosunu
 * 2D diziye çevirir. Web UI'lardan kopyalanan tablolar bazen tab yerine
 * çoklu boşluk ile gelir — her iki durumu da çözer.
 *
 * Kullanım:
 *   $parser = new BulkTsvParser();
 *   $rows = $parser->parse($tsvString);   // [[header...], [data1...], ...]
 *   $result = $importer->parseRows($rows); // standart validation
 */
final class BulkTsvParser
{
    /** Beklenen sütun sayısı (header + data) */
    private const EXPECTED_COLUMNS = 10;

    /** Boş gelmiş AI çıktısı için minimum satır eşiği */
    private const MIN_LINES = 2; // En az 1 header + 1 veri satırı

    /**
     * TSV/SSV string'ini 2D diziye çevirir.
     *
     * @return array<int, array<int, string>>
     *
     * @throws RuntimeException
     */
    public function parse(string $raw): array
    {
        $cleaned = $this->cleanInput($raw);

        if ($cleaned === '') {
            throw new RuntimeException('Yapıştırılan içerik boş. AI çıktısını tabloyla birlikte yapıştırın.');
        }

        // Satırlara böl (CRLF / CR / LF)
        $lines = preg_split('/\r\n|\r|\n/', $cleaned) ?: [];
        $lines = array_values(array_filter($lines, static fn (string $l): bool => trim($l) !== ''));

        if (count($lines) < self::MIN_LINES) {
            throw new RuntimeException('En az 2 satır gerekli (1 header + 1 veri). Yapıştırılan içerik: ' . count($lines) . ' satır.');
        }

        // Tab vs çoklu boşluk auto-detect: ilk satırda tab yoksa multi-space ayraç dene
        $useTab = $this->detectTabDelimiter($lines);

        $rows = [];
        foreach ($lines as $line) {
            $rows[] = $useTab
                ? $this->splitByTab($line)
                : $this->splitByMultiSpace($line);
        }

        // İlk satır beklenen sütun sayısında değilse: muhtemelen markdown tablosu yapıştırılmış
        $headerCols = count($rows[0] ?? []);
        if ($headerCols < 4) {
            throw new RuntimeException(sprintf(
                'Header satırı sadece %d sütun içeriyor (beklenen ≥4). AI çıktısını eksiksiz yapıştırdığınızdan emin olun.',
                $headerCols,
            ));
        }

        return $this->normalizeRowWidth($rows);
    }

    /**
     * Markdown code fence (```...```) ve baş/son boş satır temizliği.
     */
    private function cleanInput(string $raw): string
    {
        $s = trim($raw);

        // Markdown code fence
        $s = preg_replace('/^```(?:\w+)?\s*\n?/', '', $s) ?? $s;
        $s = preg_replace('/\n?```\s*$/', '', $s) ?? $s;

        return trim($s);
    }

    /**
     * Tab varlığını tespit et — ilk 3 satırın HERHANGİ birinde tab varsa tab'a güven.
     *
     * @param list<string> $lines
     */
    private function detectTabDelimiter(array $lines): bool
    {
        $sample = array_slice($lines, 0, 3);
        foreach ($sample as $line) {
            if (str_contains($line, "\t")) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tab ile bölüp her hücreyi trim eder.
     *
     * @return list<string>
     */
    private function splitByTab(string $line): array
    {
        $parts = explode("\t", $line);

        return array_map(static fn (string $p): string => trim($p), $parts);
    }

    /**
     * Çoklu boşluk (≥2) ile böler. Web UI kopyalarında tab yerine 2-4 boşluk gelir.
     * Markdown pipe tablosunu (|) da destekler.
     *
     * @return list<string>
     */
    private function splitByMultiSpace(string $line): array
    {
        // Markdown table separator | varsa onu da destekle
        if (str_contains($line, '|') && substr_count($line, '|') >= 3) {
            $parts = explode('|', $line);
            // Markdown başında/sonunda boş hücre olur (| header | header |)
            $parts = array_values(array_filter($parts, static fn (string $p): bool => trim($p) !== ''));

            return array_map(static fn (string $p): string => trim($p), $parts);
        }

        // 2+ ardışık boşluk → ayraç
        $parts = preg_split('/ {2,}/', $line) ?: [];

        return array_values(array_map(
            static fn (string $p): string => trim($p),
            array_filter($parts, static fn (string $p): bool => $p !== ''),
        ));
    }

    /**
     * Tüm satırları aynı sütun sayısına padle (eksik hücreleri '' ile doldur).
     *
     * @param array<int, array<int, string>> $rows
     *
     * @return array<int, array<int, string>>
     */
    private function normalizeRowWidth(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        // Hedef genişlik: header ya da EXPECTED_COLUMNS — hangisi büyükse
        $targetWidth = max(count($rows[0] ?? []), self::EXPECTED_COLUMNS);

        return array_map(static function (array $row) use ($targetWidth): array {
            $count = count($row);
            if ($count < $targetWidth) {
                return array_pad($row, $targetWidth, '');
            }
            if ($count > $targetWidth) {
                return array_slice($row, 0, $targetWidth);
            }

            return $row;
        }, $rows);
    }
}
