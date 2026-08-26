<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ValidatesTranslationBlocks;
use App\Enums\ContentStatus;
use App\Services\LanguageService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * A page arrives as one block of fields per language.
 *
 * Only the default language is required; the rest may be filled in later, so a
 * translator is never forced to complete every tab in one sitting.
 */
final class StoreTranslatedPageRequest extends FormRequest
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
        $pageId = $this->route('page')?->id;

        $rules = [
            'translations' => ['required', 'array', $this->atLeastOneLanguage()],
        ];

        foreach ($languages->activeCodes() as $locale) {
            // A language only reaches us when the editor was on that tab.
            $required = $this->isSubmitted($locale) ? 'required' : 'nullable';
            $prefix = "translations.{$locale}";

            $rules[$prefix]                     = ['array'];
            $rules["{$prefix}.title"]           = [$required, 'string', 'max:255'];
            $rules["{$prefix}.content"]         = [$required, 'string', 'max:100000'];
            $rules["{$prefix}.slug"]            = [
                'nullable', 'string', 'max:255',
                // Slugs only clash within their own language.
                Rule::unique('pages', 'slug')
                    ->where(fn ($query) => $query->where('locale', $locale))
                    ->ignore($this->translationIdFor($locale, $pageId)),
            ];
            $rules["{$prefix}.excerpt"]          = ['nullable', 'string', 'max:500'];
            $rules["{$prefix}.image"]            = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'];
            $rules["{$prefix}.status"]           = ['nullable', new Enum(ContentStatus::class)];
            $rules["{$prefix}.sort_order"]       = ['nullable', 'integer', 'min:0', 'max:65535'];
            $rules["{$prefix}.meta_title"]       = ['nullable', 'string', 'max:70'];
            $rules["{$prefix}.meta_description"] = ['nullable', 'string', 'max:160'];
            $rules["{$prefix}.published_at"]     = ['nullable', 'date'];
            $rules["{$prefix}.sections"]         = ['nullable', 'array'];
        }

        return $rules;
    }
    /**
     * @return list<string>
     */
    protected function contentFields(): array
    {
        return ['title', 'slug', 'excerpt', 'content', 'meta_title', 'meta_description'];
    }


    /**

     * @return array<string, string>

     */

    public function messages(): array
    {
        $messages = [];

        foreach (app(LanguageService::class)->active() as $language) {
            $name = $language->name;

            $messages["translations.{$language->code}.title.required"]   = "{$name} sekmesinde başlık zorunludur.";
            $messages["translations.{$language->code}.content.required"] = "{$name} sekmesinde içerik zorunludur.";
            $messages["translations.{$language->code}.slug.unique"]      = "{$name} dilinde bu URL zaten kullanılıyor.";
        }

        return $messages;
    }

    /**
     * Id of the existing row for this language, so editing does not clash with
     * its own slug.
     */
    private function translationIdFor(string $locale, ?int $pageId): ?int
    {
        if ($pageId === null) {
            return null;
        }

        $page = \App\Models\Page::find($pageId);

        return $page?->translation($locale)?->id;
    }
}
