<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\BlogCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AiGenerateRequest extends FormRequest
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
            'category_id' => ['required', 'integer', Rule::exists(BlogCategory::class, 'id')],
            'topic'       => ['nullable', 'string', 'max:255'],
            'city'        => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_id.required' => 'Lütfen bir kategori seçin.',
            'category_id.exists'   => 'Seçilen kategori geçerli değil.',
            'topic.max'            => 'Konu en fazla 255 karakter olabilir.',
            'city.max'             => 'Şehir adı en fazla 100 karakter olabilir.',
        ];
    }
}
