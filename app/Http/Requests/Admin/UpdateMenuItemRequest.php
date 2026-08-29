<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\MenuItem;
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
            // Üst öğe aynı menüden ve kendisi olmamalı: bir öğe kendi
            // altına taşınırsa ağaç kendine kapanır ve menü hiç basılmaz.
            'parent_id'    => [
                'nullable', 'integer',
                Rule::exists('menu_items', 'id')->where('menu_id', $this->item()?->menu_id),
                Rule::notIn([$this->item()?->id]),
            ],
            'label'        => ['required', 'string', 'max:191'],
            'icon'         => ['nullable', 'string', 'max:100'],
            'link_type'    => ['required', Rule::in(['route', 'url'])],
            'route_name'   => ['nullable', 'string', 'max:100', 'required_if:link_type,route'],
            'route_params' => ['nullable', 'array'],
            'url'          => ['nullable', 'string', 'max:191', 'required_if:link_type,url'],
            'target'       => ['required', Rule::in(['_self', '_blank'])],
            'display_type' => ['required', Rule::in(['link', 'dropdown', 'mega_menu'])],
            'is_active'    => ['nullable', 'boolean'],
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'parent_id' => $this->input('parent_id') ?: null,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'parent_id.exists'    => 'Seçilen üst menü öğesi bu menüde yok.',
            'parent_id.not_in'    => 'Bir menü öğesi kendisinin altına taşınamaz.',
            'label.required'      => 'Menü etiketi zorunludur.',
            'label.max'           => 'Menü etiketi en fazla :max karakter olabilir.',
            'link_type.required'  => 'Bağlantı tipini seçin.',
            'route_name.required_if' => 'İç sayfa seçtiğinizde bir sayfa seçmelisiniz.',
            'url.required_if'     => 'Özel bağlantı seçtiğinizde adresi yazmalısınız.',
            'url.max'             => 'Bağlantı adresi en fazla :max karakter olabilir.',
            'display_type.required' => 'Görünüm tipini seçin.',
        ];
    }

    private function item(): ?MenuItem
    {
        $item = $this->route('item');

        return $item instanceof MenuItem ? $item : null;
    }
}
