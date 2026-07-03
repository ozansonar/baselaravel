<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * MediaLibrary asset düzenleme — sadece metadata (title, product_name,
 * is_active, sort_order). Görsel dosyası değiştirilmek istenirse mevcut
 * silinip yenisi yüklenir (upload akışı).
 */
final class UpdateMediaAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'product_name' => ['nullable', 'string', 'max:191'],
            'title'        => ['nullable', 'string', 'max:191'],
            'is_active'    => ['nullable', 'boolean'],
            'sort_order'   => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_name.max' => 'Ürün adı en fazla 191 karakter olabilir.',
            'title.max'        => 'Başlık en fazla 191 karakter olabilir.',
            'sort_order.min'   => 'Sıralama 0 veya pozitif olmalı.',
        ];
    }
}
