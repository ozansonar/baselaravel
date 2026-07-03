<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreFaqRequest extends FormRequest
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
            'question'   => ['required', 'string', 'max:500'],
            'answer'     => ['required', 'string', 'max:10000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active'  => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'question.required' => 'Soru zorunludur.',
            'question.max'      => 'Soru en fazla 500 karakter olabilir.',
            'answer.required'   => 'Cevap zorunludur.',
            'answer.max'        => 'Cevap en fazla 10.000 karakter olabilir.',
        ];
    }
}
