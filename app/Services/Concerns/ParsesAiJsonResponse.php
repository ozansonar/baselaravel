<?php

declare(strict_types=1);

namespace App\Services\Concerns;

/**
 * AI yanıtlarından JSON extract eder. AI bazen prompt'a rağmen:
 *   - Markdown code fence kullanır (```json ... ```)
 *   - JSON öncesi/sonrası açıklama metni ekler ("Tabii, işte:" vb.)
 *   - CRLF / leading whitespace ile döner
 *
 * Bu trait her durumu deterministik şekilde çözer.
 *
 * Kullanım:
 *   use ParsesAiJsonResponse;
 *   $json = $this->extractJsonFromAiResponse($raw);
 *   $data = $json !== null ? json_decode($json, true) : null;
 */
trait ParsesAiJsonResponse
{
    /**
     * AI yanıtından geçerli JSON string'ini çıkarır.
     *
     *  1) ```json ... ``` (veya ```...```) içindeki bloğu öncelikli çek
     *  2) Yoksa: hangisi önce başlıyorsa (array veya object) onu dene.
     *     Array yanıtlar için object fallback'i atlamamamız KRİTİK —
     *     dizinin ortasındaki ilk { ile son } arası geçersiz JSON üretir.
     *  3) Adayı json_decode ile doğrula — sadece gerçekten parse olan döner
     *  4) Hiçbiri tutmazsa null
     */
    protected function extractJsonFromAiResponse(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        // 1) Markdown code fence varsa içeriğini öncelikli olarak çıkar
        if (preg_match('/```(?:json|JSON)?\s*(.*?)\s*```/su', $raw, $m)) {
            $candidate = trim($m[1]);
            if ($this->isParseableJson($candidate)) {
                return $candidate;
            }
        }

        // 2) Array veya object — hangisi daha erken başlıyorsa onu öncelikle dene.
        $objStart = mb_strpos($raw, '{');
        $arrStart = mb_strpos($raw, '[');

        $candidates = [];
        if ($arrStart !== false && ($objStart === false || $arrStart < $objStart)) {
            $candidates[] = ['type' => 'array',  'start' => $arrStart];
            if ($objStart !== false) $candidates[] = ['type' => 'object', 'start' => $objStart];
        } elseif ($objStart !== false) {
            $candidates[] = ['type' => 'object', 'start' => $objStart];
            if ($arrStart !== false) $candidates[] = ['type' => 'array',  'start' => $arrStart];
        }

        foreach ($candidates as $c) {
            if ($c['type'] === 'object') {
                $end = mb_strrpos($raw, '}');
                $endChar = '}';
            } else {
                $end = mb_strrpos($raw, ']');
                $endChar = ']';
            }
            if ($end === false || $end <= $c['start']) {
                continue;
            }
            $candidate = mb_substr($raw, $c['start'], $end - $c['start'] + 1);
            if ($this->isParseableJson($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Yapısal sanity check + gerçek json_decode validation.
     * Sadece hem doğru sınırlayıcılarla başlıyor/bitiyor hem de parse edilebilirse true.
     */
    private function isParseableJson(string $s): bool
    {
        $s = trim($s);
        if ($s === '') return false;
        $first = $s[0];
        $last  = substr($s, -1);
        if (! (($first === '{' && $last === '}') || ($first === '[' && $last === ']'))) {
            return false;
        }
        json_decode($s);
        return json_last_error() === JSON_ERROR_NONE;
    }

    private function looksLikeJsonObject(string $s): bool
    {
        $s = trim($s);
        return $s !== '' && $s[0] === '{' && substr($s, -1) === '}';
    }

    private function looksLikeJsonArray(string $s): bool
    {
        $s = trim($s);
        return $s !== '' && $s[0] === '[' && substr($s, -1) === ']';
    }
}
