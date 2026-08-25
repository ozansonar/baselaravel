<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\PermissionKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SyncPermissionMatrixRequest extends FormRequest
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
            'permissions'     => ['nullable', 'array'],
            'permissions.*'   => ['nullable', 'array'],
            'permissions.*.*' => ['string', Rule::in(PermissionKey::values())],
        ];
    }
}
