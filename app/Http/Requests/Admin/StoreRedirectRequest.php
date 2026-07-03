<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class StoreRedirectRequest extends FormRequest
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
            'old_url'     => 'required|string|max:500|unique:redirects,old_url|starts_with:/',
            'new_url'     => 'nullable|required_unless:status_code,404,410|string|max:500',
            'status_code' => 'required|integer|in:301,302,307,308,404,410',
            'is_active'   => 'boolean',
            'note'        => 'nullable|string|max:500',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'old_url.required'    => 'Eski URL zorunludur.',
            'old_url.unique'      => 'Bu eski URL zaten tanımlı.',
            'old_url.starts_with' => 'Eski URL / ile başlamalıdır.',
            'new_url.required_unless' => 'Yönlendirme yapılan durum kodları için yeni URL zorunludur.',
            'status_code.required' => 'Durum kodu zorunludur.',
            'status_code.in'       => 'Geçersiz durum kodu.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
        ]);
    }
}
