<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Rules\UserEmail;
use App\Enums\TokenAbility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class LoginRequest extends FormRequest
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
            'email'    => ['required', 'string', 'email', 'max:' . UserEmail::MAX_LENGTH],
            'password' => ['required', 'string', 'min:8'],
            'device_name' => ['nullable', 'string', 'max:100'],
            // Yetki istemek yalnızca DARALTIR: gönderilmezse jeton tam yetkili
            // olur, gönderilirse yalnız listedekiler verilir. Bu yol hiçbir
            // koşulda `*` üretemez.
            'abilities'   => ['nullable', 'array'],
            'abilities.*' => ['string', Rule::enum(TokenAbility::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required'    => __('site.forms.email_required'),
            'email.email'       => __('site.forms.email_invalid'),
            'password.required' => __('site.forms.password_required'),
            'password.min'      => __('site.forms.password_min'),
        ];
    }
}
