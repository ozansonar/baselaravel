<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Rules\EmailAddress;
use Illuminate\Foundation\Http\FormRequest;

/**
 * API üzerinden iletişim formu.
 *
 * Kurallar ön yüzdeki {@see \App\Http\Requests\StoreContactMessageRequest} ile
 * aynı sınırları koyuyor — aynı sütuna yazılıyor. reCAPTCHA burada yok (mobil
 * istemcide karşılığı olmadığı için); kötüye kullanımı `throttle:api-contact`
 * tutuyor: dakikada üç istek, IP başına.
 */
final class StoreContactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name'    => ['required', 'string', 'min:2', 'max:191', 'regex:/^[\p{L}\p{M}\s\'’-]+$/u'],
            'email'   => ['required', 'string', ...EmailAddress::rules(), 'max:191'],
            'phone'   => ['nullable', 'string', 'max:20', 'regex:/^[0-9\s\-\+\(\).]+$/'],
            'subject' => ['required', 'string', 'max:191'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required'    => __('site.contact.name_required'),
            'name.min'         => __('site.contact.name_min'),
            'name.max'         => __('site.contact.name_max'),
            'name.regex'       => __('site.forms.name_letters'),
            'email.required'   => __('site.forms.email_required'),
            'email.email'      => __('site.forms.email_invalid_formal'),
            'phone.regex'      => __('site.forms.phone_format'),
            'subject.required' => __('site.contact.subject_required'),
            'subject.max'      => __('site.contact.subject_max'),
            'message.required' => __('site.contact.message_required'),
            'message.min'      => __('site.contact.message_min'),
            'message.max'      => __('site.contact.message_max'),
        ];
    }
}
