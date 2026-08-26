<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\GalleryType;
use App\Services\LanguageService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A gallery item arrives as one block of fields per language.
 *
 * The category is picked per language too, because categories are translated as
 * well and an English item belongs to the English category row.
 */
final class StoreTranslatedGalleryItemRequest extends FormRequest
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
        $isCreate = $this->route('gallery_item') === null;

        $rules = ['translations' => ['required', 'array']];

        foreach ($languages->activeCodes() as $locale) {
            $required = $locale === $default ? 'required' : 'nullable';
            $prefix = "translations.{$locale}";

            $rules[$prefix]                         = ['array'];
            $rules["{$prefix}.title"]               = [$required, 'string', 'max:255'];
            $rules["{$prefix}.description"]         = ['nullable', 'string', 'max:2000'];
            $rules["{$prefix}.type"]                = [$required, Rule::enum(GalleryType::class)];
            $rules["{$prefix}.gallery_category_id"] = ['nullable', 'integer', 'exists:gallery_categories,id'];
            $rules["{$prefix}.image"]               = [
                $isCreate && $locale === $default ? 'required' : 'nullable',
                'image', 'mimes:jpg,jpeg,png,webp', 'max:4096',
            ];
            $rules["{$prefix}.video_url"]  = ['nullable', "required_if:{$prefix}.type,video", 'string', 'max:500', 'url'];
            $rules["{$prefix}.duration"]   = ['nullable', 'integer', 'min:0'];
            $rules["{$prefix}.sort_order"] = ['nullable', 'integer', 'min:0'];
            $rules["{$prefix}.is_active"]  = ['nullable', 'boolean'];
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

            $messages["translations.{$code}.title.required"]        = "{$name} sekmesinde başlık zorunludur.";
            $messages["translations.{$code}.type.required"]         = "{$name} sekmesinde tür seçilmelidir.";
            $messages["translations.{$code}.image.required"]        = "{$name} sekmesinde görsel zorunludur.";
            $messages["translations.{$code}.video_url.required_if"] = "{$name} sekmesinde video türü için video URL zorunludur.";
        }

        return $messages;
    }
}
