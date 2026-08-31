<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\RedirectStatus;
use App\Rules\NotACircularRedirect;
use App\Rules\SafeRedirectTarget;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateRedirectRequest extends FormRequest
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
        $redirectId = $this->route('redirect')?->id;

        return [
            'old_url'     => 'required|string|max:191|unique:redirects,old_url,' . $redirectId . '|starts_with:/',
            'new_url'     => [
                'nullable',
                'required_unless:status_code,404,410',
                'string',
                'max:191',
                new SafeRedirectTarget(),
                // Kendine ya da halkaya dönen yönlendirme sayfayı hiç açtırmaz.
                new NotACircularRedirect((string) $this->input('old_url'), $this->route('redirect')?->id),
            ],
            'status_code' => ['required', Rule::enum(RedirectStatus::class)],
            'is_active'   => 'boolean',
            'note'        => ['nullable', 'string', 'max:500'],
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
            'status_code.enum'     => 'Geçersiz durum kodu.',
            'old_url.max'          => 'Eski URL en fazla 500 karakter olabilir.',
            'new_url.max'          => 'Yeni URL en fazla 500 karakter olabilir.',
            'note.max'             => 'Not en fazla 500 karakter olabilir.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
        ]);
    }
}
