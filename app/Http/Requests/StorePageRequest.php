<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ContentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class StorePageRequest extends FormRequest
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
            'title'            => ['required', 'string', 'max:255'],
            'slug'             => ['nullable', 'string', 'max:255', 'unique:pages,slug'],
            'content'          => ['required', 'string', 'max:100000'],
            'excerpt'          => ['nullable', 'string', 'max:500'],
            'image'            => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'status'           => ['nullable', new Enum(ContentStatus::class)],
            'sort_order'       => ['nullable', 'integer', 'min:0'],
            'meta_title'       => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'published_at'     => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required'       => 'Sayfa başlığı zorunludur.',
            'title.max'            => 'Sayfa başlığı en fazla 255 karakter olabilir.',
            'content.required'     => 'Sayfa içeriği zorunludur.',
            'image.image'          => 'Dosya bir görsel olmalıdır.',
            'image.mimes'          => 'Görsel JPG, PNG veya WebP formatında olmalıdır.',
            'image.max'            => 'Görsel en fazla 2 MB olabilir.',
            'meta_title.max'       => 'Meta başlık en fazla 70 karakter olabilir.',
            'meta_description.max' => 'Meta açıklama en fazla 160 karakter olabilir.',
        ];
    }
}
