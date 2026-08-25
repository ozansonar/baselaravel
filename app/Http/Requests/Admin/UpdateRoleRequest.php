<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateRoleRequest extends FormRequest
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
        $roleId = $this->route('role')?->id;

        return [
            'name'        => ['required', 'string', 'max:100'],
            'slug'        => ['nullable', 'string', 'max:100', 'alpha_dash', Rule::unique('roles', 'slug')->ignore($roleId)],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
