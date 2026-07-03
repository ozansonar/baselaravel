<?php

declare(strict_types=1);

namespace App\Services\Vertex;

/**
 * Vertex AI şablon prompt'larında {{degisken_adi}} placeholder'larını parse eder
 * ve verilen değerlerle yerini doldurur.
 *
 * Syntax:
 *   {{ozel_gun_mesaji}}     → snake_case değişken adı
 *   {{altMesaj}}             → camelCase de kabul edilir
 *   {{ alt_mesaj }}          → çevresindeki boşluklar tolere edilir
 *
 * Aynı değişken birden çok kez kullanılırsa parse() tek sefer döner (unique liste).
 */
final class PromptVariableParser
{
    /** [A-Za-z_][A-Za-z0-9_]* — geçerli bir değişken adı */
    private const string PATTERN = '/\{\{\s*([A-Za-z_][A-Za-z0-9_]*)\s*\}\}/';

    /**
     * Prompt metninden tüm değişken adlarını çıkarır (sırayı korur, unique).
     *
     * @return list<string>
     */
    public static function parse(string $prompt): array
    {
        if ($prompt === '') {
            return [];
        }

        if (preg_match_all(self::PATTERN, $prompt, $matches) === false) {
            return [];
        }

        // Sırayı koruyarak unique
        $names = [];
        foreach ($matches[1] as $name) {
            if (! in_array($name, $names, true)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Prompt içindeki {{xxx}} placeholder'larını verilen array değerleriyle değiştirir.
     *
     * @param  array<string, string> $values  ['degisken_adi' => 'değer', ...]
     */
    public static function substitute(string $prompt, array $values): string
    {
        if ($prompt === '') {
            return '';
        }

        return (string) preg_replace_callback(
            self::PATTERN,
            static function (array $matches) use ($values): string {
                $name = $matches[1];
                if (! array_key_exists($name, $values)) {
                    // Değer verilmemişse placeholder olduğu gibi kalır.
                    return $matches[0];
                }
                return (string) $values[$name];
            },
            $prompt,
        );
    }

    /**
     * snake_case ya da camelCase değişken adını insan okunur başlığa çevirir.
     *
     * ozel_gun_mesaji → "Özel Gün Mesajı" (sadece İngilizce dönüşüm; Türkçe için
     * kullanıcı zaten anlaşılır snake_case kullanmalı)
     * altMesaj        → "Alt Mesaj"
     */
    public static function humanize(string $name): string
    {
        // camelCase → snake_case
        $snake = preg_replace('/([a-z])([A-Z])/', '$1_$2', $name) ?? $name;
        $snake = strtolower($snake);

        $parts = array_filter(explode('_', $snake), static fn (string $p): bool => $p !== '');

        return implode(' ', array_map(static fn (string $p): string => mb_convert_case($p, MB_CASE_TITLE, 'UTF-8'), $parts));
    }
}
