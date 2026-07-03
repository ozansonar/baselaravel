<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Görsel auto-fix (server crop) endpoint validation.
 *
 * Mode:
 *   - auto:   center crop, ek parametre yok
 *   - manual: crop_x, crop_y, crop_width, crop_height (CropperJS'ten gelen pixel koordinatları)
 */
final class AutoFixImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Admin middleware route'ta zaten kontrol ediyor
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'image'        => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'media_type'   => ['required', 'string', 'in:image,story'],
            'mode'         => ['required', 'string', 'in:auto,manual'],
            // Manual mode'da crop koordinatları
            'crop_x'       => ['nullable', 'required_if:mode,manual', 'integer', 'min:0'],
            'crop_y'       => ['nullable', 'required_if:mode,manual', 'integer', 'min:0'],
            'crop_width'   => ['nullable', 'required_if:mode,manual', 'integer', 'min:1'],
            'crop_height'  => ['nullable', 'required_if:mode,manual', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.required'    => 'Görsel zorunlu.',
            'image.image'       => 'Yüklenen dosya görsel olmalı.',
            'image.mimes'       => 'Sadece JPG/PNG/WebP kabul edilir.',
            'image.max'         => 'Görsel max 8 MB olabilir.',
            'media_type.in'     => 'Geçersiz media tipi (image veya story olmalı).',
            'mode.in'           => 'Geçersiz mod (auto veya manual olmalı).',
            'crop_x.required_if'      => 'Manuel kırpma için x koordinatı gerekli.',
            'crop_y.required_if'      => 'Manuel kırpma için y koordinatı gerekli.',
            'crop_width.required_if'  => 'Manuel kırpma için genişlik gerekli.',
            'crop_height.required_if' => 'Manuel kırpma için yükseklik gerekli.',
        ];
    }
}
