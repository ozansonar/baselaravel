<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\SubscriberStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Kayıtlı bir abonenin düzeltilmesi.
 *
 * Adres benzersizliği kendi kaydı dışarıda bırakılarak sınanıyor: yalnızca adı
 * düzeltmek için formu açan biri, adresine dokunmadığı hâlde "bu e-posta zaten
 * kayıtlı" hatası almamalı.
 */
final class UpdateSubscriberRequest extends FormRequest
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
            'email' => [
                'required', 'email', 'max:191',
                Rule::unique('subscribers', 'email')->ignore($this->route('subscriber')),
            ],
            // Silinmiş bir kaydın adresi de tutuluyor; benzersizlik kısıtı
            // onları da kapsadığı için kural withoutTrashed ile gevşetilmiyor.
            'first_name' => ['nullable', 'string', 'max:191'],
            'last_name'  => ['nullable', 'string', 'max:191'],
            'locale'     => ['nullable', 'string', 'size:2'],
            'status'     => ['required', new Enum(SubscriberStatus::class)],
            'list_ids'   => ['nullable', 'array'],
            'list_ids.*' => ['integer', 'exists:subscriber_lists,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'E-posta adresi zorunludur.',
            'email.email'    => 'Geçerli bir e-posta adresi girin.',
            'email.unique'   => 'Bu e-posta adresi başka bir aboneye ait.',
            'status.required' => 'Durum seçilmelidir.',
        ];
    }
}
