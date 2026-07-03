<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\GalleryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateGalleryItemRequest extends FormRequest
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
            'title'               => ['required', 'string', 'max:255'],
            'description'         => ['nullable', 'string', 'max:1000'],
            'type'                => ['required', Rule::enum(GalleryType::class)],
            'gallery_category_id' => ['nullable', 'integer', 'exists:gallery_categories,id'],
            'image'               => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'video_url'           => ['nullable', 'required_if:type,video', 'string', 'max:500', 'url'],
            'duration'            => ['nullable', 'string', 'max:20'],
            'sort_order'          => ['nullable', 'integer', 'min:0'],
            'is_active'           => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required'                => 'Başlık zorunludur.',
            'title.max'                     => 'Başlık en fazla 255 karakter olabilir.',
            'type.required'                 => 'Tür seçimi zorunludur.',
            'gallery_category_id.exists'    => 'Seçilen kategori geçerli değil.',
            'image.image'                   => 'Dosya bir görsel olmalıdır.',
            'image.mimes'                   => 'Görsel JPG, PNG veya WebP formatında olmalıdır.',
            'image.max'                     => 'Görsel en fazla 4 MB olabilir.',
            'video_url.required_if'         => 'Video türünde video URL zorunludur.',
            'video_url.url'                 => 'Geçerli bir URL giriniz.',
        ];
    }
}
