<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use Closure;

/**
 * A multilingual form is a single form whose fields are separated by language:
 * translations[tr][title], translations[en][title], and so on.
 *
 * Only the tab on screen is being worked on, so that is the only block the form
 * sends: the browser side leaves the hidden tabs out of the request entirely.
 * What arrives here is therefore the language the editor meant to save, and it
 * has to be complete. Languages that were not sent keep whatever is stored.
 */
trait ValidatesTranslationBlocks
{
    /**
     * Fields a translation cannot do without. Used to reject a block that was
     * sent but left empty.
     *
     * @return list<string>
     */
    abstract protected function contentFields(): array;

    /**
     * Message shown when the form arrives without a single language block.
     */
    protected function emptyTranslationsMessage(): string
    {
        return 'Kaydetmek için bulunduğunuz dildeki alanları doldurun.';
    }

    /**
     * The languages this request is actually carrying.
     *
     * @return list<string>
     */
    protected function submittedLocales(): array
    {
        $blocks = (array) $this->input('translations', []);

        return array_values(array_filter(
            array_keys($blocks),
            fn (mixed $locale): bool => is_string($locale) && is_array($blocks[$locale]),
        ));
    }

    /**
     * Whether this language was sent, which is what makes its fields required.
     */
    protected function isSubmitted(string $locale): bool
    {
        return in_array($locale, $this->submittedLocales(), true);
    }

    /**
     * Rule for the translations array itself.
     */
    protected function atLeastOneLanguage(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($this->submittedLocales() === []) {
                $fail($this->emptyTranslationsMessage());
            }
        };
    }

    /**
     * Kept for the odd caller that still asks; a submitted block counts as
     * content because the form only sends the language being saved.
     */
    protected function hasContent(string $locale): bool
    {
        return $this->isSubmitted($locale);
    }
}
