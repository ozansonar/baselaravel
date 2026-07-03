<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class CityAiFillRequest extends FormRequest
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
        return [
            'city_name' => ['required', 'string', 'max:100'],
            'region'    => ['nullable', 'string', 'max:60'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'city_name.required' => 'Şehir adı zorunludur.',
            'city_name.max'      => 'Şehir adı en fazla 100 karakter olabilir.',
        ];
    }
}
