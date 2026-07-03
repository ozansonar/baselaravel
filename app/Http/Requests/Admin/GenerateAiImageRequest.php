<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class GenerateAiImageRequest extends FormRequest
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
            'prompt'             => ['required', 'string', 'min:10'],
            'prompt_template_id' => ['nullable', 'integer', 'exists:ai_image_prompts,id'],
            'aspect_ratio'       => ['nullable', 'string', 'in:1:1,4:5,5:4,9:16,16:9,3:2,2:3,4:3,3:4'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'prompt.required' => 'Görsel oluşturmak için bir prompt yazmalısın.',
            'prompt.min'      => 'Prompt en az 10 karakter olmalı.',
        ];
    }
}
