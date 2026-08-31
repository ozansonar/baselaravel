<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Rules\EmailAddress;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Bülten aboneliği.
 *
 * Kurallar ön yüzdeki {@see \App\Http\Controllers\NewsletterController::subscribe()}
 * ile aynı: aynı tabloya yazılıyor ve adresin gerçekten posta alabildiğine
 * bakan denetim ({@see EmailAddress}) burada da geçerli — bülten listesine
 * uydurma alan adları girmesin diye.
 */
final class SubscribeNewsletterRequest extends FormRequest
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
            'email'      => ['required', EmailAddress::rule(), 'max:191'],
            'first_name' => ['nullable', 'string', 'max:191'],
            'last_name'  => ['nullable', 'string', 'max:191'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => __('site.forms.email_required'),
            'email.email'    => __('site.forms.email_invalid_formal'),
        ];
    }
}
