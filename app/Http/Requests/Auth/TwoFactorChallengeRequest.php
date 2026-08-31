<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Girişin ikinci adımı.
 *
 * Alan iki farklı şeyi kabul ediyor: altı haneli TOTP kodu ya da tireli
 * kurtarma kodu. Bu yüzden kural biçimi dar tutmuyor, yalnız uzunluğu
 * sınırlıyor — hangisinin geldiğine servis karar veriyor.
 */
final class TwoFactorChallengeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:32'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => __('site.two_factor.code_required'),
            'code.max'      => __('site.two_factor.code_max'),
        ];
    }
}
