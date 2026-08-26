<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLanguageRequest;
use App\Http\Requests\Admin\UpdateLanguageRequest;
use App\Models\Language;
use App\Services\LanguageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

final class LanguageController extends Controller
{
    public function __construct(
        private readonly LanguageService $languages,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', Language::class);

        $languages = Language::orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.languages.index', [
            'languages'    => $languages,
            'translated'   => $this->translatedLocales(),
            'contentStats' => $this->contentCounts(),
            'stats'        => [
                'total'    => $languages->count(),
                'active'   => $languages->where('is_active', true)->count(),
                'inactive' => $languages->where('is_active', false)->count(),
                'default'  => $languages->firstWhere('is_default', true)?->code,
            ],
        ]);
    }

    public function store(StoreLanguageRequest $request): RedirectResponse
    {
        $this->authorize('create', Language::class);

        // An unchecked checkbox is simply absent from the request, so the
        // default has to be false — with a default of true no language could
        // ever be switched off from the form.
        $language = $this->languages->create($request->validated() + [
            'is_active' => $request->boolean('is_active'),
        ]);

        $message = "{$language->name} eklendi.";

        // A language with no lang/{code} directory still works — the interface
        // falls back to the default — but the admin should know it will not be
        // translated until those files exist.
        if (! $this->hasTranslationFiles($language->code)) {
            $message .= " Arayüz çevirisi için lang/{$language->code}/ klasörü oluşturulmalı;"
                . ' o zamana kadar arayüz varsayılan dilde görünür.';
        }

        return redirect()
            ->route('admin.languages.index')
            ->with('success', $message);
    }

    public function update(UpdateLanguageRequest $request, Language $language): RedirectResponse
    {
        $this->authorize('update', $language);

        $this->languages->update($language, $request->validated() + [
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('admin.languages.index')
            ->with('success', "{$language->name} güncellendi.");
    }

    /**
     * Move the default flag. Exactly one language keeps it.
     */
    public function makeDefault(Language $language): RedirectResponse
    {
        $this->authorize('update', $language);

        $this->languages->makeDefault($language);

        return redirect()
            ->route('admin.languages.index')
            ->with('success', "Varsayılan dil {$language->name} oldu.");
    }

    public function destroy(Language $language): RedirectResponse
    {
        $this->authorize('delete', $language);

        $result = $this->languages->delete($language);

        return redirect()
            ->route('admin.languages.index')
            ->with($result['deleted'] ? 'success' : 'error', $result['message']);
    }

    /**
     * Which languages have interface translation files on disk.
     *
     * @return array<int, string>
     */
    private function translatedLocales(): array
    {
        if (! File::isDirectory(lang_path())) {
            return [];
        }

        return collect(File::directories(lang_path()))
            ->map(fn (string $path): string => basename($path))
            ->values()
            ->all();
    }

    private function hasTranslationFiles(string $code): bool
    {
        return File::exists(lang_path($code . '/site.php'));
    }

    /**
     * How much content exists in each language, so a language is not switched
     * off or deleted without knowing what goes with it.
     *
     * @return array<string, int>
     */
    private function contentCounts(): array
    {
        $tables = [
            'pages', 'blog_posts', 'blog_categories', 'gallery_categories',
            'gallery_items', 'faqs', 'sliders', 'popups', 'menus',
        ];

        $counts = [];

        foreach ($tables as $table) {
            foreach (
                \Illuminate\Support\Facades\DB::table($table)
                    ->selectRaw('locale, COUNT(*) as total')
                    ->whereNull('deleted_at')
                    ->groupBy('locale')
                    ->get() as $row
            ) {
                $locale = (string) $row->locale;
                $counts[$locale] = ($counts[$locale] ?? 0) + (int) $row->total;
            }
        }

        return $counts;
    }
}
