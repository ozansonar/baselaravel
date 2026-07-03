<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateSettingRequest extends FormRequest
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
            'settings'         => ['required', 'array'],
            'settings.*.key'   => ['required', 'string', 'max:255'],
            'settings.*.value' => ['nullable', 'string', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'settings.required'         => 'En az bir ayar gönderilmelidir.',
            'settings.*.key.required'   => 'Ayar anahtarı zorunludur.',
            'settings.*.value.max'      => 'Ayar değeri en fazla 10.000 karakter olabilir.',
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function settingsData(): array
    {
        $data = [];
        foreach ($this->validated('settings') as $setting) {
            $data[$setting['key']] = $setting['value'] ?? null;
        }

        return $data;
    }
}
