<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLanguageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy is checked in the controller.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $language = $this->route('language');

        return [
            // Two letters: the app locale, the lang/ directory and the
            // <html lang> attribute all use the same short code.
            'code' => [
                'required',
                'string',
                'size:2',
                'alpha',
                'lowercase',
                Rule::unique('languages', 'code')->ignore($language?->id),
            ],
            'name'        => ['required', 'string', 'max:60'],
            'native_name' => ['nullable', 'string', 'max:60'],
            'flag'        => ['nullable', 'string', 'max:8'],
            'sort_order'  => ['nullable', 'integer', 'min:0', 'max:255'],
            'is_active'   => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.size'      => 'Dil kodu iki harf olmalı (tr, en, de gibi).',
            'code.alpha'     => 'Dil kodu yalnızca harf içerebilir.',
            'code.lowercase' => 'Dil kodu küçük harf olmalı.',
            'code.unique'    => 'Bu dil kodu zaten kayıtlı.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => mb_strtolower(trim((string) $this->input('code')))]);
        }
    }
}
