<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class StoreRoleRequest extends FormRequest
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
            'name'        => ['required', 'string', 'max:100'],
            'slug'        => ['required', 'string', 'max:100', 'alpha_dash', 'unique:roles,slug'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required'   => 'Rol adı zorunludur.',
            'slug.required'   => 'Rol anahtarı zorunludur.',
            'slug.unique'     => 'Bu rol anahtarı zaten kullanılıyor.',
            'slug.alpha_dash' => 'Rol anahtarı yalnızca harf, rakam, tire ve alt çizgi içerebilir.',
        ];
    }
}
