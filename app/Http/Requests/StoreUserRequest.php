<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\EmailAddress;
use App\Rules\UserEmail;
use App\Enums\Department;
use App\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name'  => ['required', 'string', 'max:50', 'regex:/^[\p{L}\p{M}\s\'’-]+$/u'],
            'last_name'   => ['required', 'string', 'max:50', 'regex:/^[\p{L}\p{M}\s\'’-]+$/u'],
            'email'       => ['required', 'string', EmailAddress::rule(), 'max:' . UserEmail::MAX_LENGTH, UserEmail::unique()],
            'phone'       => ['nullable', 'string', 'max:20'],
            'birth_date'  => ['nullable', 'date', 'before:today'],
            'gender'      => ['nullable', 'string', Rule::enum(Gender::class)],
            'location'    => ['nullable', 'string', 'max:100'],
            'department'  => ['nullable', 'string', Rule::enum(Department::class)],
            'bio'         => ['nullable', 'string', 'max:500'],
            'avatar'      => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
            'is_active'   => ['nullable', 'boolean'],
            'password'    => ['required', 'string', Password::min(8)->letters()->numbers(), 'confirmed'],
            'roles'       => ['nullable', 'array'],
            'roles.*'     => ['integer', Rule::exists('roles', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'Ad alanı zorunludur.',
            'first_name.regex'    => 'Ad yalnızca harf ve boşluk içerebilir.',
            'last_name.required'  => 'Soyad alanı zorunludur.',
            'last_name.regex'     => 'Soyad yalnızca harf ve boşluk içerebilir.',
            'email.required'      => 'E-posta adresi zorunludur.',
            'email.email'         => 'Geçerli bir e-posta adresi giriniz.',
            'email.unique'        => 'Bu e-posta adresi zaten kullanılıyor.',
            'avatar.image'        => 'Dosya bir görsel olmalıdır.',
            'avatar.max'          => 'Avatar en fazla 1 MB olabilir.',
            'password.required'   => 'Şifre zorunludur.',
            'password.confirmed'  => 'Şifre tekrarı eşleşmiyor.',
            'roles.*.exists'      => 'Seçilen rol bulunamadı.',
        ];
    }
}
