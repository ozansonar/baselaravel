<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'parent_id'        => ['nullable', 'integer', Rule::exists('categories', 'id'), Rule::notIn([$this->route('category')?->id])],
            'name'             => ['required', 'string', 'max:255'],
            'description'      => ['nullable', 'string', 'max:5000'],
            'image'            => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'sort_order'       => ['nullable', 'integer', 'min:0'],
            'is_active'        => ['nullable', 'boolean'],
            'meta_title'       => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:160'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'parent_id.exists'     => 'Seçilen üst kategori bulunamadı.',
            'parent_id.not_in'     => 'Kategori kendisinin alt kategorisi olamaz.',
            'name.required'        => 'Kategori adı zorunludur.',
            'name.max'             => 'Kategori adı en fazla 255 karakter olabilir.',
            'image.image'          => 'Dosya bir görsel olmalıdır.',
            'image.mimes'          => 'Görsel JPG, PNG veya WebP formatında olmalıdır.',
            'image.max'            => 'Görsel en fazla 2 MB olabilir.',
            'meta_title.max'       => 'Meta başlık en fazla 70 karakter olabilir.',
            'meta_description.max' => 'Meta açıklama en fazla 160 karakter olabilir.',
        ];
    }
}
