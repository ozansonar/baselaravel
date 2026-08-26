<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ValidatesTranslationBlocks;
use App\Services\LanguageService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * An FAQ entry arrives as one block of fields per language. No language is
 * mandatory on its own; see ValidatesTranslationBlocks.
 */
final class StoreTranslatedFaqRequest extends FormRequest
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
            $required = $this->hasContent($locale) ? 'required' : 'nullable';
            $prefix = "translations.{$locale}";

            $rules[$prefix]                = ['array'];
            $rules["{$prefix}.question"]   = [$required, 'string', 'max:500'];
            $rules["{$prefix}.answer"]     = [$required, 'string', 'max:10000'];
            $rules["{$prefix}.sort_order"] = ['nullable', 'integer', 'min:0', 'max:65535'];
            $rules["{$prefix}.is_active"]  = ['nullable', 'boolean'];
        }

        return $rules;
    }
    /**
     * @return list<string>
     */
    protected function contentFields(): array
    {
        return ['question', 'answer'];
    }


    /**

     * @return array<string, string>

     */

    public function messages(): array
    {
        $messages = [];

        foreach (app(LanguageService::class)->active() as $language) {
            $messages["translations.{$language->code}.question.required"] = "{$language->name} sekmesinde soru zorunludur.";
            $messages["translations.{$language->code}.answer.required"]   = "{$language->name} sekmesinde cevap zorunludur.";
        }

        return $messages;
    }
}
