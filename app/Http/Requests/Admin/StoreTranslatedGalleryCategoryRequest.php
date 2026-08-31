<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ValidatesTranslationBlocks;
use App\Services\LanguageService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A category arrives as one block of fields per language. No language is
 * mandatory on its own; see ValidatesTranslationBlocks.
 */
final class StoreTranslatedGalleryCategoryRequest extends FormRequest
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

        $rules = ['translations' => ['required', 'array', $this->atLeastOneLanguage()]];

        foreach ($languages->activeCodes() as $locale) {
            // A language only reaches us when the editor was on that tab.
            $required = $this->isSubmitted($locale) ? 'required' : 'nullable';
            $prefix = "translations.{$locale}";

            $rules[$prefix] = ['array'];
            $rules["{$prefix}.name"]        = [$required, 'string', 'max:191'];
            $rules["{$prefix}.sort_order"]  = ['nullable', 'integer', 'min:0', 'max:65535'];
            $rules["{$prefix}.is_active"]   = ['nullable', 'boolean'];
            $rules["{$prefix}.slug"]        = [
                'nullable', 'string', 'max:191',
                // Slugs only clash within their own language.
                Rule::unique('gallery_categories', 'slug')->where(fn ($query) => $query->where('locale', $locale)),
            ];
        }

        return $rules;
    }
    /**
     * @return list<string>
     */
    protected function contentFields(): array
    {
        return ['name'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [];

        foreach (app(LanguageService::class)->active() as $language) {
            $messages["translations.{$language->code}.name.required"] = "{$language->name} sekmesinde ad zorunludur.";
        }

        return $messages;
    }
}
