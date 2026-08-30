<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use App\Rules\UserEmail;
use Illuminate\Foundation\Http\FormRequest;

final class ProfileUpdateRequest extends FormRequest
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
        $userId = auth()->id();

        return [
            'first_name' => ['required', 'string', 'max:50', 'regex:/^[a-zA-ZçÇğĞıİöÖşŞüÜ\s]+$/u'],
            'last_name'  => ['required', 'string', 'max:50', 'regex:/^[a-zA-ZçÇğĞıİöÖşŞüÜ\s]+$/u'],
            'email' => ['required', 'string', 'email', 'max:' . UserEmail::MAX_LENGTH, UserEmail::unique($userId)],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9\s\-\+\(\).]+$/'],
            // Changing a password requires proving the current one, so a hijacked
            // session cannot lock the real owner out.
            'current_password' => ['required_with:password', 'current_password'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed', 'different:current_password'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:1024'],
            'remove_avatar' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Uyarı metinleri panelden yönetiliyor (Dil Yazıları).
     *
     * Koda gömülü olduklarında İngilizce ziyaretçi Türkçe uyarı görüyordu ve
     * yönetici metni değiştiremiyordu. Sayılar :min / :max ile kuraldan
     * geliyor: elle yazılan sayı, kural değişince yalan söylüyor — nitekim
     * söylemişti: iletişim formu sınır 191'ken "255" diyordu.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required_with'    => __('site.account.current_password_required'),
            'current_password.current_password' => __('site.account.current_password_wrong'),
            'password.different'                => __('site.account.password_same'),
            'first_name.required'               => __('site.forms.first_name_required'),
            'first_name.regex'                  => __('site.forms.first_name_letters'),
            'last_name.required'                => __('site.forms.last_name_required'),
            'last_name.regex'                   => __('site.forms.last_name_letters'),
            'email.required'                    => __('site.forms.email_required'),
            'email.email'                       => __('site.forms.email_invalid'),
            'email.unique'                      => __('site.account.email_taken'),
            'phone.max'                         => __('site.account.phone_max'),
            'phone.regex'                       => __('site.forms.phone_format'),
            'password.min'                      => __('site.forms.password_min'),
            'password.confirmed'                => __('site.account.password_confirmed'),
            'avatar.image'                      => __('site.account.avatar_image'),
            'avatar.mimes'                      => __('site.account.avatar_mimes'),
            'avatar.max'                        => __('site.account.avatar_max'),
        ];
    }
}
