<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ValidatesTranslationBlocks;
use App\Enums\ContentStatus;
use App\Models\BlogPost;
use App\Services\LanguageService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * A blog post arrives as one block of fields per language.
 *
 * No single language is mandatory: a post may exist in English only, in which
 * case it simply does not surface on the Turkish site. What is required is that
 * at least one language block is filled, and that a block the editor did touch
 * carries the fields a post cannot do without.
 *
 * The publish state comes from the save buttons and applies to every language
 * block, because "publish" is a decision about the post rather than about one
 * translation of it.
 */
final class StoreTranslatedBlogPostRequest extends FormRequest
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
        $post = $this->route('blog_post');

        $rules = [
            'translations'  => ['required', 'array', $this->atLeastOneLanguage()],
        ];

        foreach ($languages->activeCodes() as $locale) {
            // A language the editor left untouched stays optional; the moment a
            // block holds anything, it has to be a complete post.
            // A language only reaches us when the editor was on that tab.
            $required = $this->isSubmitted($locale) ? 'required' : 'nullable';
            $prefix = "translations.{$locale}";

            $rules[$prefix]                      = ['array'];
            $rules["{$prefix}.title"]            = [$required, 'string', 'max:120'];
            $rules["{$prefix}.body"]             = [$required, 'string'];
            $rules["{$prefix}.blog_category_id"] = [$required, 'integer', 'exists:blog_categories,id'];
            $rules["{$prefix}.slug"]             = [
                'nullable', 'string', 'max:255',
                // Slugs only clash within their own language.
                Rule::unique('blog_posts', 'slug')
                    ->where(fn ($query) => $query->where('locale', $locale))
                    ->ignore($this->translationIdFor($locale, $post)),
            ];
            $rules["{$prefix}.excerpt"]          = ['nullable', 'string', 'max:300'];
            $rules["{$prefix}.image"]            = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'];
            $rules["{$prefix}.remove_image"]     = ['nullable', 'in:0,1'];
            $rules["{$prefix}.meta_title"]       = ['nullable', 'string', 'max:60'];
            $rules["{$prefix}.meta_description"] = ['nullable', 'string', 'max:160'];
            $rules["{$prefix}.published_at"]     = ['nullable', 'date'];
            $rules["{$prefix}.status"]           = ['nullable', new Enum(ContentStatus::class)];
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

    /**
     * @return list<string>
     */
    protected function contentFields(): array
    {
        return ['title', 'slug', 'excerpt', 'body', 'meta_title', 'meta_description'];
    }

    protected function emptyTranslationsMessage(): string
    {
        return 'En az bir dilde içerik girmelisiniz.';
    }
}
