<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

/**
 * Sliderlar listesinde seçilen satırlar üzerinde toplu işlem.
 *
 * Kural ve mesajlar {@see BulkActionRequest} içinde; burada yalnız tablo var.
 */
final class BulkSliderRequest extends BulkActionRequest
{
    protected function table(): string
    {
        return 'sliders';
    }
}
