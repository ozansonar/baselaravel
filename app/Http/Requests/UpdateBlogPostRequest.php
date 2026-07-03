<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateBlogPostRequest extends FormRequest
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
            'blog_category_id' => ['required', 'integer', 'exists:blog_categories,id'],
            'title'            => ['required', 'string', 'max:255'],
            'excerpt'          => ['nullable', 'string', 'max:500'],
            'body'             => ['required', 'string', 'max:200000'],
            'image'            => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'is_published'     => ['nullable', 'boolean'],
            'published_at'     => ['nullable', 'date'],
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
            'blog_category_id.required' => 'Kategori seçimi zorunludur.',
            'blog_category_id.exists'   => 'Seçilen kategori bulunamadı.',
            'title.required'            => 'İçerik başlığı zorunludur.',
            'title.max'                 => 'Başlık en fazla 255 karakter olabilir.',
            'body.required'             => 'İçerik metni zorunludur.',
            'image.image'               => 'Dosya bir görsel olmalıdır.',
            'image.mimes'               => 'Görsel JPG, PNG veya WebP formatında olmalıdır.',
            'image.max'                 => 'Görsel en fazla 2 MB olabilir.',
            'meta_title.max'            => 'Meta başlık en fazla 70 karakter olabilir.',
            'meta_description.max'      => 'Meta açıklama en fazla 160 karakter olabilir.',
        ];
    }
}
