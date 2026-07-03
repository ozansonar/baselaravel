<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreCityLandingPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'city_name'        => ['required', 'string', 'max:100'],
            'city_slug'        => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9\-]+$/'],
            'region'           => ['nullable', 'string', 'max:60'],
            'tier'             => ['nullable', 'integer', 'between:1,4'],
            'title'            => ['required', 'string', 'max:255'],
            'meta_title'       => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:200'],
            'hero_heading'     => ['required', 'string', 'max:255'],
            'hero_description' => ['nullable', 'string', 'max:500'],
            'content'          => ['nullable', 'string', 'max:200000'],
            'shipping_note'    => ['nullable', 'string', 'max:255'],
            'delivery_time'    => ['nullable', 'string', 'max:60'],
            'is_active'        => ['nullable', 'boolean'],
            'sort_order'       => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'city_name.required'    => 'Şehir adı zorunludur.',
            'city_name.max'         => 'Şehir adı en fazla 100 karakter olabilir.',
            'city_slug.regex'       => 'URL slug yalnızca küçük harf, sayı ve tire içerebilir.',
            'tier.between'          => 'Tier 1 ile 4 arasında olmalıdır.',
            'title.required'        => 'Sayfa başlığı zorunludur.',
            'meta_title.max'        => 'Meta başlık en fazla 70 karakter olabilir.',
            'meta_description.max'  => 'Meta açıklama en fazla 200 karakter olabilir.',
            'hero_heading.required' => 'H1 başlığı zorunludur.',
        ];
    }
}
