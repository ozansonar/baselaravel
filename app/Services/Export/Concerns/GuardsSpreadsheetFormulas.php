<?php

declare(strict_types=1);

namespace App\Services\Export\Concerns;

use Illuminate\Support\Str;

/**
 * Hesap tablosu formül enjeksiyonuna karşı ortak koruma.
 *
 * "=CMD(...)" gibi bir metin, hücre elle onaylandığında ya da dosya CSV olarak
 * açıldığında formüle döner. Öndeki tek tırnak hesap tablosuna "bu metindir"
 * der. XLSX ve CSV yazıcılarının ikisi de aynı kuralı uygular — CSV tarafında
 * tehlike daha büyük, çünkü orada tip diye bir şey yok: her hücre metin olarak
 * gidiyor ve açan program ne olduğuna kendi karar veriyor.
 */
trait GuardsSpreadsheetFormulas
{
    private function neutralizeFormula(string $value): string
    {
        return Str::startsWith($value, ['=', '+', '-', '@', "\t", "\r"])
            ? "'" . $value
            : $value;
    }
}
