<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateSliderRequest extends FormRequest
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
            'title'       => ['required', 'string', 'max:191'],
            'subtitle'    => ['nullable', 'string', 'max:191'],
            'image'       => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
            'button_text' => ['nullable', 'string', 'max:50'],
            'button_url'  => ['nullable', 'string', 'max:191', 'url'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
            'is_active'   => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Slider başlığı zorunludur.',
            'title.max'      => 'Slider başlığı en fazla 255 karakter olabilir.',
            'image.image'    => 'Dosya bir görsel olmalıdır.',
            'image.mimes'    => 'Görsel JPG, PNG veya WebP formatında olmalıdır.',
            'image.max'      => 'Görsel en fazla 4 MB olabilir.',
            'button_url.url' => 'Geçerli bir URL giriniz.',
        ];
    }
}
