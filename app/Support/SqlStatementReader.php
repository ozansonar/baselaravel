<?php

declare(strict_types=1);

namespace App\Support;

use Generator;

/**
 * Bir SQL dökümünü tek tek ifadelere ayırır.
 *
 * Naif çözüm noktalı virgülden bölmektir ve **yanlıştır**: bir metin alanının
 * içindeki noktalı virgül (`INSERT ... VALUES ('Merhaba; dünya')`) ifadeyi
 * ortasından keser ve geri yükleme yarım kalır. Kaçırılmış bir tırnak da aynı
 * sonucu verir. Bu yüzden burada karakter karakter ilerleyen bir durum
 * makinesi var: tırnak içinde miyiz, ters bölü ile kaçırılmış mı, yorumun
 * içinde miyiz.
 *
 * Dosya parça parça okunuyor ve ifadeler üretildikçe veriliyor: yüz megabaytlık
 * bir döküm belleğe sığmayabilir, geri yükleme de tam olarak o boyutlarda
 * gerekir.
 *
 * Desteklenmeyen tek şey `DELIMITER`: onu yalnızca tetikleyici ve saklı yordam
 * döken çıktılar üretir, bu projenin dökümü ikisini de içermez.
 */
final class SqlStatementReader
{
    private const CHUNK_BYTES = 65536;

    /**
     * İleri-bakış payı. `/*!` üç karakter; bir sonraki parça çekilmeden önce
     * elde en az bu kadar karakter kalmalı, yoksa kalıp tam parça sınırına
     * denk geldiğinde kaçar.
     */
    private const LOOKAHEAD = 3;

    /**
     * Dosyadaki ifadeleri sırayla verir; noktalı virgül ifadeye dahil değildir.
     *
     * @return Generator<int, string>
     */
    public function fromFile(string $path): Generator
    {
        // @: dosyanın yokluğu bu sınıf için hata değil, boş sonuç.
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return;
        }

        try {
            yield from $this->parse((static function () use ($handle): Generator {
                while (! feof($handle)) {
                    $chunk = fread($handle, self::CHUNK_BYTES);

                    if ($chunk === false || $chunk === '') {
                        return;
                    }

                    yield $chunk;
                }
            })());
        } finally {
            fclose($handle);
        }
    }

    /**
     * Aynı ayrıştırma, hazır bir metin üzerinde.
     *
     * @return Generator<int, string>
     */
    public function fromString(string $sql): Generator
    {
        yield from $this->parse((static function () use ($sql): Generator {
            yield $sql;
        })());
    }

    /**
     * @param  Generator<int, string> $chunks
     * @return Generator<int, string>
     */
    private function parse(Generator $chunks): Generator
    {
        $statement = '';
        $quote = null;      // içinde bulunduğumuz tırnak: ' " veya `
        $escaped = false;   // önceki karakter ters bölü müydü
        $lineComment = false;
        $blockComment = false;

        $pending = '';
        $i = 0;
        $exhausted = false;

        while (true) {
            if (! $exhausted && strlen($pending) - $i < self::LOOKAHEAD) {
                if ($chunks->valid()) {
                    // İşlenmemiş kuyruk yeni parçanın başına ekleniyor, yani
                    // sınırı aşan kalıplar bütün hâlde görülüyor.
                    $pending = substr($pending, $i) . $chunks->current();
                    $i = 0;
                    $chunks->next();

                    continue;
                }

                $exhausted = true;
            }

            $length = strlen($pending);

            // Akış bitmediyse son iki karakter ileri-bakış payı olarak
            // bırakılıyor; bittiyse sonuna kadar okunuyor.
            $end = $exhausted ? $length : $length - (self::LOOKAHEAD - 1);

            for (; $i < $end; $i++) {
                $char = $pending[$i];
                $next = $i + 1 < $length ? $pending[$i + 1] : '';

                // ── Yorum içindeyken ──
                if ($lineComment) {
                    if ($char === "\n") {
                        $lineComment = false;
                        $statement .= $char;
                    }

                    continue;
                }

                if ($blockComment) {
                    if ($char === '*' && $next === '/') {
                        $blockComment = false;
                        $i++;
                    }

                    continue;
                }

                // ── Tırnak içindeyken ──
                if ($quote !== null) {
                    $statement .= $char;

                    if ($escaped) {
                        $escaped = false;

                        continue;
                    }

                    // Ters bölü kaçışı geri tırnak içinde geçerli değil.
                    if ($char === '\\' && $quote !== '`') {
                        $escaped = true;

                        continue;
                    }

                    if ($char === $quote) {
                        // İkilenmiş tırnak ('' veya "") kapatmaz, kaçırır.
                        if ($next === $quote) {
                            $statement .= $next;
                            $i++;

                            continue;
                        }

                        $quote = null;
                    }

                    continue;
                }

                // ── Normal metin ──
                if ($char === "'" || $char === '"' || $char === '`') {
                    $quote = $char;
                    $statement .= $char;

                    continue;
                }

                if ($char === '-' && $next === '-') {
                    $lineComment = true;
                    $i++;

                    continue;
                }

                if ($char === '#') {
                    $lineComment = true;

                    continue;
                }

                if ($char === '/' && $next === '*') {
                    // `/*! ... */` çalıştırılabilir yorumdur, atılmaz:
                    // mysqldump karakter kümesi ve kısıt ayarlarını böyle
                    // yazıyor ve o satırlar geri yüklemede gerçekten gerekli.
                    if (($i + 2 < $length ? $pending[$i + 2] : '') !== '!') {
                        $blockComment = true;
                        $i++;

                        continue;
                    }
                }

                if ($char === ';') {
                    $ready = trim($statement);
                    $statement = '';

                    if ($ready !== '') {
                        yield $ready;
                    }

                    continue;
                }

                $statement .= $char;
            }

            if ($exhausted) {
                break;
            }
        }

        $ready = trim($statement);

        if ($ready !== '') {
            yield $ready;
        }
    }
}
