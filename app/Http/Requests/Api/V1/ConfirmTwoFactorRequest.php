<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Kurulumu tamamlayan ilk kod (API).
 *
 * Ön yüzün {@see \App\Http\Requests\Account\ConfirmTwoFactorRequest} sınıfının
 * karşılığı. Ayrı duruyor çünkü ön yüzdeki sınıf hata mesajlarını forma göre
 * yazıyor; kural kümesi ikisinde de aynı ve aynı kalmalı.
 *
 * Burada yalnız TOTP kodu kabul ediliyor — kurtarma kodları henüz üretilmedi,
 * onlar kurulumun çıktısı.
 */
final class ConfirmTwoFactorRequest extends FormRequest
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
            'code' => ['required', 'string', 'digits:6', 'max:6'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => __('site.two_factor.code_required'),
            'code.digits'   => __('site.two_factor.code_digits'),
        ];
    }
}
