<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class ContactMessageReplyRequest extends FormRequest
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
            'reply_body' => ['required', 'string', 'min:5', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reply_body.required' => 'Yanıt mesajı zorunludur.',
            'reply_body.min'      => 'Yanıt mesajı en az 5 karakter olmalıdır.',
            'reply_body.max'      => 'Yanıt mesajı en fazla 10.000 karakter olabilir.',
        ];
    }
}
