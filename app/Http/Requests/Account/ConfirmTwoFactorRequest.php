<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Kurulumu tamamlayan ilk kod.
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
            // digits:6 zaten altı hane demek; max:6 onun yanında fazlalık
            // görünüyor ama formdaki maxSize[6] ile sunucunun söylediği sayıyı
            // eşleştiren bekçi (ValidationLimitsMatchTheSchemaTest) sayıyı
            // max: kuralından okuyor. İkisi ayrışırsa istemci sunucudan katı
            // ya da gevşek olur ve bunu ancak kullanıcı fark eder.
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
