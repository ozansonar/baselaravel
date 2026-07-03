<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PopupPage;
use App\Enums\PopupSize;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class StorePopupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'image'       => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'button_text' => ['nullable', 'string', 'max:100'],
            'button_url'  => ['nullable', 'string', 'max:500'],
            'size'        => ['nullable', new Enum(PopupSize::class)],
            'pages'       => ['required', 'array', 'min:1'],
            'pages.*'     => ['required', new Enum(PopupPage::class)],
            'start_date'  => ['nullable', 'date'],
            'end_date'    => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active'   => ['nullable', 'boolean'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required'          => 'Popup başlığı zorunludur.',
            'title.max'               => 'Popup başlığı en fazla 255 karakter olabilir.',
            'image.image'             => 'Dosya bir görsel olmalıdır.',
            'image.mimes'             => 'Görsel JPG, PNG veya WebP formatında olmalıdır.',
            'image.max'               => 'Görsel en fazla 2 MB olabilir.',
            'button_url.max'          => 'Buton URL en fazla 500 karakter olabilir.',
            'pages.required'          => 'En az bir sayfa seçilmelidir.',
            'pages.min'               => 'En az bir sayfa seçilmelidir.',
            'end_date.after_or_equal' => 'Bitiş tarihi başlangıç tarihinden önce olamaz.',
        ];
    }
}
