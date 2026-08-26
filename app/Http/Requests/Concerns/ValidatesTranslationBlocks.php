<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Services\LanguageService;
use Closure;

/**
 * A multilingual form posts one block of fields per language.
 *
 * No single language is mandatory — a record may exist in English only, in
 * which case it simply does not surface on the Turkish site. What is required
 * is that at least one block was filled, and that a block the editor did touch
 * carries the fields the record cannot do without.
 *
 * Only the content fields decide whether a block counts as touched: a sort
 * order, a visibility switch and a status select always post a value, so
 * counting them would make every untouched tab look started.
 */
trait ValidatesTranslationBlocks
{
    /**
     * Fields that say a translation was actually written.
     *
     * @return list<string>
     */
    abstract protected function contentFields(): array;

    /**
     * Message shown when every language block is empty.
     */
    protected function emptyTranslationsMessage(): string
    {
        return 'En az bir dilde içerik girmelisiniz.';
    }

    /**
     * Rule for the translations array itself.
     */
    protected function atLeastOneLanguage(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            foreach (app(LanguageService::class)->activeCodes() as $locale) {
                if ($this->hasContent($locale)) {
                    return;
                }
            }

            $fail($this->emptyTranslationsMessage());
        };
    }

    /**
     * Whether the editor put anything into this language block. HTML is
     * stripped as well, because an empty rich text editor still posts markup.
     */
    protected function hasContent(string $locale): bool
    {
        $fields = (array) $this->input("translations.{$locale}", []);

        $written = array_any(
            $this->contentFields(),
            fn (string $field): bool => is_scalar($fields[$field] ?? null)
                && trim(strip_tags((string) $fields[$field])) !== '',
        );

        return $written || (array) $this->file("translations.{$locale}", []) !== [];
    }
}
