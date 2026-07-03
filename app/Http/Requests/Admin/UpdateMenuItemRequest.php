<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateMenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'editor']) ?? false;
    }

    public function rules(): array
    {
        return [
            'label'        => ['required', 'string', 'max:255'],
            'icon'         => ['nullable', 'string', 'max:100'],
            'link_type'    => ['required', Rule::in(['route', 'url'])],
            'route_name'   => ['nullable', 'string', 'max:100', 'required_if:link_type,route'],
            'route_params' => ['nullable', 'array'],
            'url'          => ['nullable', 'string', 'max:500', 'required_if:link_type,url'],
            'target'       => ['required', Rule::in(['_self', '_blank'])],
            'display_type' => ['required', Rule::in(['link', 'dropdown', 'mega_menu'])],
            'is_active'    => ['nullable', 'boolean'],
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
