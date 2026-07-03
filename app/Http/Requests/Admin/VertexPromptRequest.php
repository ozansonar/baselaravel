<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class VertexPromptRequest extends FormRequest
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
            'description'             => ['nullable', 'string', 'max:255'],
            'tags'                    => ['nullable', 'string', 'max:500'],
            'ig_caption_template'     => ['nullable', 'string', 'max:5000'],
            'ig_title_template'       => ['nullable', 'string', 'max:500'],
            'ig_hashtags_template'    => ['nullable', 'string', 'max:2000'],
            'prompt'                  => ['required', 'string', 'min:10', 'max:20000'],
            // Çoklu referans görsel — her biri ayrı file input
            'reference_images'           => ['nullable', 'array', 'max:10'],
            'reference_images.*'         => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:51200'],
            // Index bazlı silme: array of integers (mevcut görsel array index'leri)
            'reference_images_remove'    => ['nullable', 'array'],
            'reference_images_remove.*'  => ['integer', 'min:0'],

            'random_options'             => ['nullable', 'array'],
            'random_options.*'           => ['nullable', 'string', 'max:50000'],

            'model'                   => ['nullable', 'string', 'max:80'],
            'aspect_ratio'            => ['nullable', 'string', 'in:1:1,4:5,5:4,9:16,16:9,3:2,2:3,4:3,3:4'],
            'image_size'              => ['nullable', 'string', 'in:1K,2K'],
            'mime_type'               => ['nullable', 'string', 'in:image/png,image/jpeg,image/webp'],
            'temperature'             => ['nullable', 'numeric', 'min:0', 'max:2'],
            'max_output_tokens'       => ['nullable', 'integer', 'min:256', 'max:65536'],
            'top_p'                   => ['nullable', 'numeric', 'min:0', 'max:1'],
            'thinking_level'          => ['nullable', 'string', 'in:MINIMAL,LOW,MEDIUM,HIGH'],
            'person_generation'       => ['nullable', 'string', 'in:ALLOW_ALL,ALLOW_ADULT,BLOCK_ALL'],
            'safety_off'              => ['nullable', 'boolean'],
            'is_active'               => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required'        => 'Şablon adı zorunlu.',
            'name.max'             => 'Şablon adı en fazla 120 karakter.',
            'prompt.required'      => 'Prompt metni zorunlu.',
            'prompt.min'           => 'Prompt en az 10 karakter olmalı.',
            'prompt.max'           => 'Prompt en fazla 20.000 karakter.',
            'reference_images.max'    => 'En fazla 10 referans görsel yükleyebilirsin.',
            'reference_images.*.mimes'=> 'Referans görseller JPG/PNG/WebP olmalı.',
            'reference_images.*.max'  => 'Her referans görsel en fazla 50 MB olabilir.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): array
    {
        $data = parent::validated();
        $data['is_active']  = $this->boolean('is_active');
        $data['safety_off'] = $this->boolean('safety_off');

        $tagsRaw = trim((string) ($data['tags'] ?? ''));
        $data['tags'] = $tagsRaw !== ''
            ? array_values(array_filter(array_map('trim', explode(',', $tagsRaw))))
            : null;

        $randomOptions = $data['random_options'] ?? [];
        $parsed = [];
        foreach ($randomOptions as $varName => $text) {
            $lines = array_values(array_filter(
                array_map('trim', explode("\n", (string) $text)),
                static fn (string $line): bool => $line !== '',
            ));
            if ($lines !== []) {
                $parsed[$varName] = $lines;
            }
        }
        $data['random_options'] = $parsed !== [] ? $parsed : null;

        return $data;
    }
}
