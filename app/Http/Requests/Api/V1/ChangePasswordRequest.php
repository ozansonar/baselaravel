<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Şifre değiştirme — yalnız API'de ayrı bir uç.
 *
 * Web'de aynı iş profil formundan yapılıyor; orada zaten bütün alanlar
 * ekranda. API'de profil güncelleme TAM bir güncelleme (gönderilmeyen alan
 * "değiştirme" değil "boşalt" demek), yani şifre değiştirmek isteyen mobil
 * uygulamanın ad, soyad ve e-postayı da taşıması gerekirdi. Ayrı uç bunu
 * ortadan kaldırıyor.
 */
final class ChangePasswordRequest extends FormRequest
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
            // Mevcut şifreyi kanıtlamak şart: ele geçirilmiş bir jeton,
            // sahibini kendi hesabından kilitleyebilmemeli.
            'current_password' => ['required', 'string', 'current_password'],
            'password'         => ['required', 'string', Password::min(8), 'confirmed', 'different:current_password'],
            // Öteki cihazları düşürmek isteğe bağlı ve varsayılan olarak
            // kapalı: şifresini rutin olarak yenileyen biri bütün
            // cihazlarından atılmayı beklemiyor. Şifresinin ele geçtiğini
            // düşünen kişi ise bunu açıyor.
            'logout_other_devices' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required'         => __('site.account.current_password_required'),
            'current_password.current_password' => __('site.account.current_password_wrong'),
            'password.different'                => __('site.account.password_same'),
            'password.min'                      => __('site.forms.password_min'),
            'password.confirmed'                => __('site.account.password_confirmed'),
        ];
    }
}
