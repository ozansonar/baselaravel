<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

final class AddressRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:100'],
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'city' => ['required', 'string', 'max:100'],
            'district' => ['required', 'string', 'max:100'],
            'zip_code' => ['nullable', 'string', 'max:10'],
            'address_line' => ['required', 'string', 'max:500'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Adres başlığı zorunludur.',
            'title.max' => 'Adres başlığı en fazla 100 karakter olabilir.',
            'full_name.required' => 'Ad soyad zorunludur.',
            'full_name.max' => 'Ad soyad en fazla 255 karakter olabilir.',
            'phone.required' => 'Telefon numarası zorunludur.',
            'phone.max' => 'Telefon numarası en fazla 20 karakter olabilir.',
            'city.required' => 'İl zorunludur.',
            'city.max' => 'İl en fazla 100 karakter olabilir.',
            'district.required' => 'İlçe zorunludur.',
            'district.max' => 'İlçe en fazla 100 karakter olabilir.',
            'zip_code.max' => 'Posta kodu en fazla 10 karakter olabilir.',
            'address_line.required' => 'Adres satırı zorunludur.',
            'address_line.max' => 'Adres en fazla 500 karakter olabilir.',
        ];
    }
}
