<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\PopupPage;
use App\Enums\PopupSize;
use App\Services\LanguageService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A popup arrives as one block of fields per language, artwork included.
 */
final class StoreTranslatedPopupRequest extends FormRequest
{
    use \App\Http\Requests\Concerns\ValidatesTranslationBlocks;

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

        $rules = ['translations' => ['required', 'array', $this->atLeastOneLanguage()]];

        foreach ($languages->activeCodes() as $locale) {
            $required = $this->hasContent($locale) ? 'required' : 'nullable';
            $prefix = "translations.{$locale}";

            $rules[$prefix]                  = ['array'];
            $rules["{$prefix}.title"]        = [$required, 'string', 'max:255'];
            $rules["{$prefix}.description"]  = ['nullable', 'string', 'max:2000'];
            $rules["{$prefix}.image"]        = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'];
            $rules["{$prefix}.button_text"]  = ['nullable', 'string', 'max:100'];
            $rules["{$prefix}.button_url"]   = ['nullable', 'string', 'max:500'];
            $rules["{$prefix}.size"]         = ['nullable', Rule::enum(PopupSize::class)];
            $rules["{$prefix}.pages"]        = [$required, 'array', 'min:1'];
            $rules["{$prefix}.pages.*"]      = ['required', Rule::enum(PopupPage::class)];
            $rules["{$prefix}.start_date"]   = ['nullable', 'date'];
            $rules["{$prefix}.end_date"]     = ['nullable', 'date', "after_or_equal:{$prefix}.start_date"];
            $rules["{$prefix}.is_active"]    = ['nullable', 'boolean'];
            $rules["{$prefix}.sort_order"]   = ['nullable', 'integer', 'min:0', 'max:65535'];
        }

        return $rules;
    }
    /**
     * @return list<string>
     */
    protected function contentFields(): array
    {
        return ['title', 'description', 'button_text', 'button_url'];
    }


    /**

     * @return array<string, string>

     */

    public function messages(): array
    {
        $messages = [];

        foreach (app(LanguageService::class)->active() as $language) {
            $messages["translations.{$language->code}.title.required"] = "{$language->name} sekmesinde başlık zorunludur.";
            $messages["translations.{$language->code}.pages.required"] = "{$language->name} sekmesinde en az bir sayfa seçilmelidir.";
        }

        return $messages;
    }
}
