<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\VertexPrompt;
use App\Services\Vertex\PromptVariableParser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class GenerateVertexImageRequest extends FormRequest
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
            'prompt_id'   => ['required', 'integer', 'exists:vertex_prompts,id'],
            'variables'   => ['nullable', 'array'],
            'variables.*' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'prompt_id.required' => 'Listeden bir şablon seçmelisin.',
            'prompt_id.exists'   => 'Seçili şablon bulunamadı (silinmiş olabilir).',
            'variables.*.max'    => 'Her değişken en fazla 5.000 karakter olabilir.',
        ];
    }

    /**
     * Şablonda tanımlı tüm {{degisken}}'lerin doldurulduğunu kontrol et.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $promptId = (int) $this->input('prompt_id');
            if ($promptId === 0) {
                return; // prompt_id zaten required olarak fail edecek
            }

            $template = VertexPrompt::find($promptId);
            if ($template === null) {
                return;
            }

            $required = PromptVariableParser::parse((string) $template->prompt);
            if ($required === []) {
                return;
            }

            $provided = (array) $this->input('variables', []);
            foreach ($required as $name) {
                $value = $provided[$name] ?? null;
                if (! is_string($value) || trim($value) === '') {
                    $v->errors()->add(
                        'variables.' . $name,
                        '"' . PromptVariableParser::humanize($name) . '" alanı zorunlu.',
                    );
                }
            }
        });
    }
}
