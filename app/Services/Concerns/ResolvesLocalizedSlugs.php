<?php

declare(strict_types=1);

namespace App\Services\Concerns;

use App\Models\Page;
use App\Services\TranslationGroupResolver;

/**
 * Turns a page slug into the slug that same page uses in another language.
 *
 * Navigation stores a slug, not a page id, so a link copied or fallen back from
 * another language would otherwise open the wrong translation.
 */
trait ResolvesLocalizedSlugs
{
    /**
     * Resolved slugs for this request, keyed by "locale|slug".
     *
     * @var array<string, string>
     */
    private array $localizedSlugs = [];

    /**
     * The same page's slug in the given language, or the slug unchanged when
     * that language has no translation yet.
     */
    public function localizedSlug(string $slug, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $key = $locale . '|' . $slug;

        if (array_key_exists($key, $this->localizedSlugs)) {
            return $this->localizedSlugs[$key];
        }

        // Arama ortak çözücüde: adres çeviricisi de aynı soruyu soruyor ve
        // iki ayrı kopya tutulduğunda sorgu her sayfada iki kez gidiyordu.
        $group = app(TranslationGroupResolver::class)->resolve(Page::class, $slug, $locale);

        $resolved = $slug;

        if ($group !== null) {
            $translated = Page::query()
                ->where('lang_group_id', $group)
                ->where('locale', $locale)
                ->value('slug');

            if (is_string($translated) && $translated !== '') {
                $resolved = $translated;
            }
        }

        return $this->localizedSlugs[$key] = $resolved;
    }
}
