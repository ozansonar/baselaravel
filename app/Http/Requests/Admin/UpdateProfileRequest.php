<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateProfileRequest extends FormRequest
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
        $userId = $this->user()?->id;

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'email'      => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone'      => ['nullable', 'string', 'max:20'],
            'bio'        => ['nullable', 'string', 'max:1000'],
            'location'   => ['nullable', 'string', 'max:255'],
            'avatar'     => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
            // Changing a password requires proving the current one, so a hijacked
            // session cannot lock the real owner out. Without nullable the rule
            // also ran on an empty field, so saving the profile without touching
            // the password failed with "şifre yanlış".
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password'   => ['nullable', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required_with' => 'Şifrenizi değiştirmek için mevcut şifrenizi girmelisiniz.',
            'current_password.current_password' => 'Mevcut şifreniz hatalı.',
            'password.different' => 'Yeni şifre mevcut şifrenizle aynı olamaz.',
            'first_name.required' => 'Ad alanı zorunludur.',
            'first_name.max'      => 'Ad en fazla 100 karakter olabilir.',
            'last_name.required'  => 'Soyad alanı zorunludur.',
            'last_name.max'       => 'Soyad en fazla 100 karakter olabilir.',
            'email.required'      => 'E-posta alanı zorunludur.',
            'email.email'         => 'Geçerli bir e-posta adresi giriniz.',
            'email.unique'        => 'Bu e-posta adresi zaten kullanılıyor.',
            'phone.max'           => 'Telefon en fazla 20 karakter olabilir.',
            'bio.max'             => 'Biyografi en fazla 1000 karakter olabilir.',
            'location.max'        => 'Konum en fazla 255 karakter olabilir.',
            'avatar.image'        => 'Avatar bir görsel dosyası olmalıdır.',
            'avatar.mimes'        => 'Avatar JPG, PNG veya WebP formatında olmalıdır.',
            'avatar.max'          => 'Avatar en fazla 2MB olabilir.',
            'password.min'        => 'Şifre en az 8 karakter olmalıdır.',
            'password.confirmed'  => 'Şifre tekrarı eşleşmiyor.',
        ];
    }
}
