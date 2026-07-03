<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class BulkCreateCityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'fill_with_ai' => $this->boolean('fill_with_ai'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cities'              => ['required', 'array', 'min:1', 'max:50'],
            'cities.*.city_name'  => ['required', 'string', 'max:100'],
            'cities.*.region'     => ['nullable', 'string', 'max:60'],
            'cities.*.tier'       => ['nullable', 'integer', 'between:1,4'],
            'fill_with_ai'        => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cities.required'           => 'En az bir şehir seçmelisiniz.',
            'cities.max'                => 'Bir defada en fazla 50 şehir oluşturulabilir.',
            'cities.*.city_name.required' => 'Şehir adı zorunludur.',
            'cities.*.tier.between'     => 'Tier 1 ile 4 arasında olmalıdır.',
        ];
    }
}
