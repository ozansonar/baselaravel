<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\BlogPost;
use App\Services\LanguageService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A blog post arrives as one block of fields per language.
 *
 * The publish state comes from the save buttons and applies to every language
 * block, because "publish" is a decision about the post rather than about one
 * translation of it.
 */
final class StoreTranslatedBlogPostRequest extends FormRequest
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
        $post = $this->route('blogPost');

        $rules = [
            'translations'  => ['required', 'array'],
            'is_published'  => ['nullable', 'boolean'],
        ];

        foreach ($languages->activeCodes() as $locale) {
            $required = $locale === $default ? 'required' : 'nullable';
            $prefix = "translations.{$locale}";

            $rules[$prefix]                      = ['array'];
            $rules["{$prefix}.title"]            = [$required, 'string', 'max:255'];
            $rules["{$prefix}.body"]             = [$required, 'string', 'max:200000'];
            $rules["{$prefix}.blog_category_id"] = [$required, 'integer', 'exists:blog_categories,id'];
            $rules["{$prefix}.slug"]             = [
                'nullable', 'string', 'max:255',
                // Slugs only clash within their own language.
                Rule::unique('blog_posts', 'slug')
                    ->where(fn ($query) => $query->where('locale', $locale))
                    ->ignore($this->translationIdFor($locale, $post)),
            ];
            $rules["{$prefix}.excerpt"]          = ['nullable', 'string', 'max:1000'];
            $rules["{$prefix}.image"]            = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'];
            $rules["{$prefix}.meta_title"]       = ['nullable', 'string', 'max:70'];
            $rules["{$prefix}.meta_description"] = ['nullable', 'string', 'max:160'];
            $rules["{$prefix}.published_at"]     = ['nullable', 'date'];
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
            $code = $language->code;
            $name = $language->name;

            $messages["translations.{$code}.title.required"]            = "{$name} sekmesinde başlık zorunludur.";
            $messages["translations.{$code}.body.required"]             = "{$name} sekmesinde içerik zorunludur.";
            $messages["translations.{$code}.blog_category_id.required"] = "{$name} sekmesinde kategori seçilmelidir.";
            $messages["translations.{$code}.slug.unique"]               = "{$name} dilinde bu URL zaten kullanılıyor.";
        }

        return $messages;
    }

    private function translationIdFor(string $locale, ?BlogPost $post): ?int
    {
        return $post?->translation($locale)?->id;
    }
}
