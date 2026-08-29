<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Menu;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Menü öğesi hangi menüye ekleniyorsa o menü adreste yazıyor
 * (/admin/menus/{menu}/items), formda değil.
 *
 * Kural onu gövdede arıyordu ve form göndermediği için her ekleme "menu id
 * alanı zorunludur" ile düşüyordu — menüye hiçbir öğe eklenemiyordu.
 */
final class StoreMenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'editor']) ?? false;
    }

    public function rules(): array
    {
        return [
            'menu_id'      => ['required', 'integer', 'exists:menus,id'],
            // Üst öğe aynı menüden olmalı: başka bir menünün öğesine
            // bağlanan alt öğe hiçbir yerde görünmez, sessizce kaybolur.
            'parent_id'    => ['nullable', 'integer', Rule::exists('menu_items', 'id')->where('menu_id', $this->menuId())],
            'label'        => ['required', 'string', 'max:191'],
            'icon'         => ['nullable', 'string', 'max:100'],
            'link_type'    => ['required', Rule::in(['route', 'url'])],
            'route_name'   => ['nullable', 'string', 'max:100', 'required_if:link_type,route'],
            'route_params' => ['nullable', 'array'],
            'url'          => ['nullable', 'string', 'max:191', 'required_if:link_type,url'],
            'target'       => ['required', Rule::in(['_self', '_blank'])],
            'display_type' => ['required', Rule::in(['link', 'dropdown', 'mega_menu'])],
            'sort_order'   => ['nullable', 'integer', 'min:0'],
            'is_active'    => ['nullable', 'boolean'],
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'menu_id'   => $this->menuId(),
            'is_active' => $this->boolean('is_active'),
            // Boş gelen üst öğe "kök" demek; boş metin sayı doğrulamasına takılır.
            'parent_id' => $this->input('parent_id') ?: null,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'menu_id.required'    => 'Menü bulunamadı. Sayfayı yenileyip tekrar deneyin.',
            'menu_id.exists'      => 'Menü bulunamadı. Sayfayı yenileyip tekrar deneyin.',
            'parent_id.exists'    => 'Seçilen üst menü öğesi bu menüde yok.',
            'label.required'      => 'Menü etiketi zorunludur.',
            'label.max'           => 'Menü etiketi en fazla :max karakter olabilir.',
            'link_type.required'  => 'Bağlantı tipini seçin.',
            'route_name.required_if' => 'İç sayfa seçtiğinizde bir sayfa seçmelisiniz.',
            'url.required_if'     => 'Özel bağlantı seçtiğinizde adresi yazmalısınız.',
            'url.max'             => 'Bağlantı adresi en fazla :max karakter olabilir.',
            'display_type.required' => 'Görünüm tipini seçin.',
        ];
    }

    /** Adresteki menü — öğe ona ekleniyor. */
    private function menuId(): ?int
    {
        $menu = $this->route('menu');

        return $menu instanceof Menu ? $menu->id : (is_numeric($menu) ? (int) $menu : null);
    }
}
