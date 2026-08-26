<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Services\LanguageService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A category arrives as one block of fields per language.
 *
 * No single language is mandatory: a category may exist in English only. What
 * is required is that at least one language block is filled, and that a block
 * the editor did touch carries a name.
 *
 * Sort order and the visibility switch always carry a value, so only the
 * content fields decide whether a block counts as touched.
 */
final class StoreTranslatedBlogCategoryRequest extends FormRequest
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

        $rules = [
            'translations' => ['required', 'array', function (string $attribute, mixed $value, callable $fail): void {
                foreach (app(LanguageService::class)->activeCodes() as $locale) {
                    if ($this->hasContent($locale)) {
                        return;
                    }
                }

                $fail('En az bir dilde kategori adı girmelisiniz.');
            }],
        ];

        foreach ($languages->activeCodes() as $locale) {
            // A language the editor left untouched stays optional.
            $required = $this->hasContent($locale) ? 'required' : 'nullable';
            $prefix = "translations.{$locale}";

            $rules[$prefix] = ['array'];
            $rules["{$prefix}.name"]        = [$required, 'string', 'max:255'];
            $rules["{$prefix}.icon"]        = ['nullable', 'string', 'max:100'];
            $rules["{$prefix}.sort_order"]  = ['nullable', 'integer', 'min:0', 'max:999'];
            $rules["{$prefix}.is_active"]   = ['nullable', 'boolean'];
            $rules["{$prefix}.slug"]        = [
                'nullable', 'string', 'max:255',
                // Slugs only clash within their own language.
                Rule::unique('blog_categories', 'slug')->where(fn ($query) => $query->where('locale', $locale)),
            ];
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
            $messages["translations.{$language->code}.name.required"] = "{$language->name} sekmesinde ad zorunludur.";
        }

        return $messages;
    }

    /**
     * Whether the editor put anything into this language block.
     */
    private function hasContent(string $locale): bool
    {
        $fields = (array) $this->input("translations.{$locale}", []);

        return array_any(
            ['name', 'icon'],
            fn (string $field): bool => trim((string) ($fields[$field] ?? '')) !== '',
        );
    }
}
