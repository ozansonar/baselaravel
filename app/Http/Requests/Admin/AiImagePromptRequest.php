<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class AiImagePromptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'name'                    => ['required', 'string', 'max:120'],
            'prompt'                  => ['required', 'string', 'min:10', 'max:20000'],
            'description'             => ['nullable', 'string', 'max:255'],
            'is_active'               => ['nullable', 'boolean'],
            'reference_image'         => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'reference_image_remove'  => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required'           => 'Şablon adı zorunlu.',
            'name.max'                => 'Şablon adı en fazla 120 karakter olabilir.',
            'prompt.required'         => 'Prompt metni zorunlu.',
            'prompt.min'               => 'Prompt en az 10 karakter olmalı.',
            'prompt.max'              => 'Prompt en fazla 20.000 karakter olabilir.',
            'description.max'         => 'Açıklama en fazla 255 karakter olabilir.',
            'reference_image.file'    => 'Referans görsel geçerli bir dosya değil.',
            'reference_image.mimes'   => 'Referans görsel JPG, JPEG, PNG veya WebP olmalı.',
            'reference_image.max'     => 'Referans görsel en fazla 5 MB olabilir.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): array
    {
        $data = parent::validated();
        $data['is_active'] = $this->boolean('is_active', true);

        return $data;
    }
}
