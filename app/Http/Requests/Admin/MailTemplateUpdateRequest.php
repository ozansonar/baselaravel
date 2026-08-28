<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class MailTemplateUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'subject'   => ['required', 'string', 'max:500'],
            'body'      => ['required', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subject.required' => 'E-posta konusu zorunludur.',
            'subject.max'      => 'E-posta konusu en fazla 500 karakter olabilir.',
            'body.required'    => 'E-posta içeriği zorunludur.',
            'body.max'         => 'E-posta içeriği en fazla 65.000 karakter olabilir.',
        ];
    }
}
