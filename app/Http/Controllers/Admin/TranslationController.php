<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\Translation;
use App\Services\LanguageService;
use App\Services\TranslationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Dil Yazıları" — the interface strings, editable per language.
 *
 * The key list is read from the default language's file, so a string added in
 * code turns up here on its own. Only edited values are stored; anything left
 * at the shipped default keeps coming from the file.
 */
final class TranslationController extends Controller
{
    private const GROUP = 'site';

    public function __construct(
        private readonly TranslationService $translations,
        private readonly LanguageService $languages,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Translation::class);

        $languages = $this->languages->active();
        $locale = $this->resolveLocale($request, $languages);

        $keys = $this->translations->keysFrom(self::GROUP);
        $current = $this->translations->effectiveLines($locale, self::GROUP);
        $defaults = $this->translations->fileLines($locale, self::GROUP);
        $overrides = $this->translations->overridesFor($locale, self::GROUP);

        $defaultCode = $this->languages->defaultCode();

        return view('admin.translations.index', [
            'languages'      => $languages,
            'locale'         => $locale,
            // Başka bir dili çevirirken kaynak metin ekranda dursun: kullanıcı
            // aslını görmek için ikinci bir sekme açmak zorunda kalmasın.
            'defaultCode'    => $defaultCode,
            'defaultLabel'   => strtoupper($defaultCode),
            'isDefaultLocale' => $locale === $defaultCode,
            'sections'       => $this->translations->groupIntoSections($keys, $current, $defaults, $overrides),
            'overrideCounts' => $this->translations->overrideCounts(self::GROUP),
            'stats'          => [
                'total'     => count($keys),
                'changed'   => count($overrides),
                'languages' => $languages->count(),
                'missing'   => $this->missingCount($keys, $locale),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('update', Translation::class);

        $validated = $request->validate([
            'locale'   => ['required', 'string', 'size:2'],
            'values'   => ['required', 'array'],
            'values.*' => ['nullable', 'string', 'max:5000'],
        ]);

        abort_unless($this->languages->isSupported($validated['locale']), 404);

        $result = $this->translations->save($validated['locale'], self::GROUP, $validated['values']);

        return redirect()
            ->route('admin.translations.index', ['locale' => $validated['locale']])
            ->with('success', $this->saveMessage($result));
    }

    /**
     * @param array{saved: int, reset: int} $result
     */
    private function saveMessage(array $result): string
    {
        return match (true) {
            $result['saved'] === 0 && $result['reset'] === 0 => 'Değişiklik yapılmadı.',
            $result['reset'] === 0 => "{$result['saved']} metin kaydedildi.",
            $result['saved'] === 0 => "{$result['reset']} metin varsayılana döndü.",
            default => "{$result['saved']} metin kaydedildi, {$result['reset']} metin varsayılana döndü.",
        };
    }

    /**
     * Send one language's texts back to the shipped file.
     */
    public function reset(Request $request): RedirectResponse
    {
        $this->authorize('update', Translation::class);

        $locale = (string) $request->input('locale');

        abort_unless($this->languages->isSupported($locale), 404);

        $count = $this->translations->resetGroup($locale, self::GROUP);

        return redirect()
            ->route('admin.translations.index', ['locale' => $locale])
            ->with('success', "{$count} metin varsayılana döndürüldü.");
    }

    /**
     * @param \Illuminate\Support\Collection<int, Language> $languages
     */
    private function resolveLocale(Request $request, $languages): string
    {
        $requested = (string) $request->string('locale');

        if ($requested !== '' && $languages->contains('code', $requested)) {
            return $requested;
        }

        return $this->languages->defaultCode();
    }

    /**
     * Break the flat key list into the sections the screen shows.
     *
     * The top-level part of the key ("nav" in "nav.home") is already how the
     * file is organised, so it doubles as the section.
     *
     * @param array<string, string> $keys
     * @param array<string, string> $current
     * @param array<string, string> $defaults
     * @param array<string, string> $overrides
     * @return array<string, array{label: string, icon: string, rows: array<int, array<string, mixed>>}>
     */
    /**
     * Keys the default language defines but this language has neither a file
     * entry nor an override for — the ones that silently fall back.
     *
     * @param array<string, string> $keys
     */
    private function missingCount(array $keys, string $locale): int
    {
        $have = $this->translations->effectiveLines($locale, self::GROUP);

        return count(array_diff_key($keys, $have));
    }
}
