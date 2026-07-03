<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\BlogCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class BlogAiFillRequest extends FormRequest
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
            'title'            => ['required', 'string', 'max:255'],
            'blog_category_id' => ['nullable', 'integer', Rule::exists(BlogCategory::class, 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required'            => 'Blog başlığı zorunludur.',
            'title.max'                 => 'Blog başlığı en fazla 255 karakter olabilir.',
            'blog_category_id.exists'   => 'Seçilen kategori geçerli değil.',
        ];
    }
}
