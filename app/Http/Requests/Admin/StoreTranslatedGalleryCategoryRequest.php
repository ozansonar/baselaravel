<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Services\LanguageService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A category arrives as one block of fields per language; only the default
 * language has to be filled in.
 */
final class StoreTranslatedGalleryCategoryRequest extends FormRequest
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

            $rules[$prefix] = ['array'];
            $rules["{$prefix}.name"]        = [$required, 'string', 'max:255'];
            $rules["{$prefix}.sort_order"]  = ['nullable', 'integer', 'min:0', 'max:65535'];
            $rules["{$prefix}.is_active"]   = ['nullable', 'boolean'];
            $rules["{$prefix}.slug"]        = [
                'nullable', 'string', 'max:255',
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
