<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateAddressRequest extends FormRequest
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
            'title'        => ['required', 'string', 'max:100'],
            'full_name'    => ['required', 'string', 'max:255'],
            'phone'        => ['required', 'string', 'max:20'],
            'city'         => ['required', 'string', 'max:100'],
            'district'     => ['required', 'string', 'max:100'],
            'zip_code'     => ['nullable', 'string', 'max:10'],
            'address_line' => ['required', 'string', 'max:1000'],
            'is_default'   => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required'        => 'Adres başlığı zorunludur.',
            'title.max'             => 'Adres başlığı en fazla 100 karakter olabilir.',
            'full_name.required'    => 'Ad soyad zorunludur.',
            'phone.required'        => 'Telefon numarası zorunludur.',
            'city.required'         => 'Şehir zorunludur.',
            'district.required'     => 'İlçe zorunludur.',
            'address_line.required' => 'Adres zorunludur.',
            'address_line.max'      => 'Adres en fazla 1.000 karakter olabilir.',
        ];
    }
}
