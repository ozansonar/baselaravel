<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\NotDisposableEmail;
use App\Rules\RecaptchaRule;
use App\Services\LocationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('shipping_phone')) {
            $this->merge([
                'shipping_phone' => preg_replace('/\s+/', '', (string) $this->input('shipping_phone')),
            ]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $nameRegex = 'regex:/^[a-zA-ZçÇğĞıİöÖşŞüÜ\s]+$/u';

        $rules = [
            'shipping_name' => ['required', 'string', 'min:3', 'max:100', $nameRegex],
            'shipping_phone' => ['required', 'string', 'regex:/^05\d{9}$/'],
            'shipping_city' => ['required', 'string', Rule::in(LocationService::getCities())],
            'shipping_district' => ['required', 'string', 'max:100'],
            'shipping_zip_code' => ['nullable', 'string', 'regex:/^\d{5}$/'],
            'shipping_address' => ['required', 'string', 'max:500'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'g-recaptcha-response' => ['sometimes', new RecaptchaRule()],
        ];

        if (!auth()->check()) {
            $rules['guest_name'] = ['required', 'string', 'min:3', 'max:100', $nameRegex];
            $rules['guest_email'] = ['required', 'email:rfc,dns', 'max:255', new NotDisposableEmail()];
        }

        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $city = $this->input('shipping_city');
            $district = $this->input('shipping_district');

            if ($city && $district && !LocationService::isValidDistrict($city, $district)) {
                $validator->errors()->add('shipping_district', 'Seçilen il için geçersiz ilçe.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'shipping_name.required' => 'Teslimat alıcı adı zorunludur.',
            'shipping_name.min' => 'Ad soyad en az 3 karakter olmalıdır.',
            'shipping_name.max' => 'Ad soyad en fazla 100 karakter olabilir.',
            'shipping_name.regex' => 'Ad soyad sadece harf ve boşluk içerebilir.',
            'shipping_phone.required' => 'Teslimat telefon numarası zorunludur.',
            'shipping_phone.regex' => 'Telefon numarası 05 ile başlamalı ve 11 haneli olmalıdır. Örn: 05XX XXX XX XX',
            'shipping_city.required' => 'Teslimat ili zorunludur.',
            'shipping_city.in' => 'Geçerli bir il seçiniz.',
            'shipping_district.required' => 'Teslimat ilçesi zorunludur.',
            'shipping_zip_code.regex' => 'Posta kodu 5 haneli olmalıdır.',
            'shipping_address.required' => 'Teslimat adresi zorunludur.',
            'shipping_address.max' => 'Teslimat adresi en fazla 500 karakter olabilir.',
            'notes.max' => 'Sipariş notu en fazla 1000 karakter olabilir.',
            'guest_name.required' => 'Ad soyad zorunludur.',
            'guest_name.min' => 'Ad soyad en az 3 karakter olmalıdır.',
            'guest_name.max' => 'Ad soyad en fazla 100 karakter olabilir.',
            'guest_name.regex' => 'Ad soyad sadece harf ve boşluk içerebilir.',
            'guest_email.required' => 'E-posta adresi zorunludur.',
            'guest_email.email' => 'Geçerli bir e-posta adresi giriniz.',
        ];
    }
}
