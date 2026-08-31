<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ValidatesTranslationBlocks;
use App\Services\LanguageService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A slider arrives as one block of fields per language.
 *
 * The image is required on create for the language actually being written; other
 * languages may reuse nothing and be filled in later, and an edit keeps the
 * artwork each language already has.
 */
final class StoreTranslatedSliderRequest extends FormRequest
{
    use ValidatesTranslationBlocks;

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
        $isCreate = $this->route('slider') === null;

        $rules = ['translations' => ['required', 'array', $this->atLeastOneLanguage()]];

        foreach ($languages->activeCodes() as $locale) {
            // A language only reaches us when the editor was on that tab.
            $required = $this->isSubmitted($locale) ? 'required' : 'nullable';
            $prefix = "translations.{$locale}";

            $rules[$prefix]                 = ['array'];
            $rules["{$prefix}.title"]       = [$required, 'string', 'max:191'];
            $rules["{$prefix}.subtitle"]    = ['nullable', 'string', 'max:191'];
            $rules["{$prefix}.image"]       = [
                $isCreate && $this->isSubmitted($locale) ? 'required' : 'nullable',
                'image', 'mimes:jpg,jpeg,png,webp', 'max:1024',
            ];
            $rules["{$prefix}.button_text"] = ['nullable', 'string', 'max:50'];
            $rules["{$prefix}.button_url"]  = ['nullable', 'string', 'max:191'];
            $rules["{$prefix}.sort_order"]  = ['nullable', 'integer', 'min:0', 'max:65535'];
            $rules["{$prefix}.is_active"]   = ['nullable', 'boolean'];
        }

        return $rules;
    }
    /**
     * @return list<string>
     */
    protected function contentFields(): array
    {
        return ['title', 'subtitle', 'button_text', 'button_url'];
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
