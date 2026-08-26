<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Services\LanguageService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A slider arrives as one block of fields per language.
 *
 * The image is required for the default language on create only; other
 * languages may reuse nothing and be filled in later, and an edit keeps the
 * artwork each language already has.
 */
final class StoreTranslatedSliderRequest extends FormRequest
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
        $languages = app(LanguageService::class);
        $default = $languages->defaultCode();
        $isCreate = $this->route('slider') === null;

        $rules = ['translations' => ['required', 'array']];

        foreach ($languages->activeCodes() as $locale) {
            $required = $locale === $default ? 'required' : 'nullable';
            $prefix = "translations.{$locale}";

            $rules[$prefix]                 = ['array'];
            $rules["{$prefix}.title"]       = [$required, 'string', 'max:255'];
            $rules["{$prefix}.subtitle"]    = ['nullable', 'string', 'max:500'];
            $rules["{$prefix}.image"]       = [
                $isCreate && $locale === $default ? 'required' : 'nullable',
                'image', 'mimes:jpg,jpeg,png,webp', 'max:4096',
            ];
            $rules["{$prefix}.button_text"] = ['nullable', 'string', 'max:100'];
            $rules["{$prefix}.button_url"]  = ['nullable', 'string', 'max:500'];
            $rules["{$prefix}.sort_order"]  = ['nullable', 'integer', 'min:0', 'max:65535'];
            $rules["{$prefix}.is_active"]   = ['nullable', 'boolean'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [];

        foreach (app(LanguageService::class)->active() as $language) {
            $messages["translations.{$language->code}.title.required"] = "{$language->name} sekmesinde başlık zorunludur.";
            $messages["translations.{$language->code}.image.required"] = "{$language->name} sekmesinde görsel zorunludur.";
        }

        return $messages;
    }
}
