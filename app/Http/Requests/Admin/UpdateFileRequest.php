<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FileManager dosya metadata güncelleme — sadece title + alt_text.
 * Dosyanın kendisi değiştirilemez (yeni dosya yüklenir).
 */
final class UpdateFileRequest extends FormRequest
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
            'title'    => ['nullable', 'string', 'max:191'],
            'alt_text' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.max'    => 'Başlık en fazla 191 karakter olabilir.',
            'alt_text.max' => 'Alt metin en fazla 500 karakter olabilir.',
        ];
    }
}
