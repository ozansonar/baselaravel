<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CampaignType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class StoreCampaignRequest extends FormRequest
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
            'name'             => ['required', 'string', 'max:255'],
            'type'             => ['required', new Enum(CampaignType::class)],
            'description'      => ['nullable', 'string', 'max:5000'],
            'discount_value'   => ['required', 'numeric', 'min:0', 'max:100'],
            'discount_type'    => ['required', 'string', Rule::in(['percentage', 'fixed'])],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'code'             => ['nullable', 'string', 'max:50', Rule::unique('campaigns', 'code')],
            'start_date'       => ['required', 'date', 'after_or_equal:today'],
            'end_date'         => ['required', 'date', 'after:start_date'],
            'is_active'        => ['nullable', 'boolean'],
            'usage_limit'      => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required'           => 'Kampanya adı zorunludur.',
            'type.required'           => 'Kampanya türü zorunludur.',
            'discount_value.required' => 'İndirim değeri zorunludur.',
            'discount_value.max'      => 'İndirim değeri en fazla 100 olabilir.',
            'discount_type.required'  => 'İndirim türü zorunludur.',
            'discount_type.in'        => 'İndirim türü yüzde veya sabit olmalıdır.',
            'code.unique'             => 'Bu kupon kodu zaten kullanılıyor.',
            'start_date.required'     => 'Başlangıç tarihi zorunludur.',
            'start_date.after_or_equal' => 'Başlangıç tarihi bugün veya sonrası olmalıdır.',
            'end_date.required'       => 'Bitiş tarihi zorunludur.',
            'end_date.after'          => 'Bitiş tarihi başlangıç tarihinden sonra olmalıdır.',
        ];
    }
}
