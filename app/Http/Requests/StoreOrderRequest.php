<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreOrderRequest extends FormRequest
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
            'user_id'               => ['nullable', 'integer', Rule::exists('users', 'id')],
            'items'                 => ['required', 'array', 'min:1'],
            'items.*.product_id'    => ['required', 'integer', Rule::exists('products', 'id')],
            'items.*.product_name'  => ['required', 'string', 'max:255'],
            'items.*.product_price' => ['required', 'numeric', 'min:0'],
            'items.*.quantity'      => ['required', 'integer', 'min:1'],
            'items.*.unit'          => ['nullable', 'string', 'max:50'],
            'shipping_name'         => ['required', 'string', 'max:255'],
            'shipping_phone'        => ['required', 'string', 'max:20'],
            'shipping_city'         => ['required', 'string', 'max:100'],
            'shipping_district'     => ['required', 'string', 'max:100'],
            'shipping_zip_code'     => ['nullable', 'string', 'max:10'],
            'shipping_address'      => ['required', 'string', 'max:1000'],
            'campaign_code'         => ['nullable', 'string', 'max:50'],
            'notes'                 => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required'                => 'En az bir ürün eklemelisiniz.',
            'items.min'                     => 'En az bir ürün eklemelisiniz.',
            'items.*.product_id.required'   => 'Ürün seçimi zorunludur.',
            'items.*.product_id.exists'     => 'Seçilen ürün bulunamadı.',
            'items.*.product_price.required' => 'Ürün fiyatı zorunludur.',
            'items.*.quantity.required'     => 'Ürün adedi zorunludur.',
            'items.*.quantity.min'          => 'Ürün adedi en az 1 olmalıdır.',
            'shipping_name.required'        => 'Alıcı adı zorunludur.',
            'shipping_phone.required'       => 'Telefon numarası zorunludur.',
            'shipping_city.required'        => 'Şehir zorunludur.',
            'shipping_district.required'    => 'İlçe zorunludur.',
            'shipping_address.required'     => 'Adres zorunludur.',
        ];
    }
}
