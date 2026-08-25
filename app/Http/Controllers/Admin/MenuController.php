<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateMenuRequest;
use App\Models\Menu;
use App\Services\LanguageService;
use App\Services\MenuService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class MenuController extends Controller
{
    public function __construct(
        private readonly MenuService $menuService,
        private readonly LanguageService $languageService,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', Menu::class);

        $menus = Menu::withCount('items')
            ->orderBy('location')
            ->orderBy('locale')
            ->get();

        $stats = [
            'total'      => $menus->count(),
            'active'     => $menus->where('is_active', true)->count(),
            'total_items'=> (int) $menus->sum('items_count'),
            'locations'  => $menus->pluck('location')->unique()->count(),
        ];

        // Only languages that do not have this menu yet can be copied into.
        $languages = $this->languageService->active()->keyBy('code');
        $missingLanguages = $menus->mapWithKeys(
            fn (Menu $menu): array => [$menu->id => $menu->missingLanguages()],
        );

        return view('admin.menus.index', compact('menus', 'stats', 'languages', 'missingLanguages'));
    }

    /**
     * Clone a menu and its whole item tree into another language.
     */
    public function copy(Menu $menu, string $locale): RedirectResponse
    {
        $this->authorize('create', Menu::class);

        if (! $this->languageService->isSupported($locale)) {
            return redirect()
                ->route('admin.menus.index')
                ->with('error', 'Geçersiz dil.');
        }

        $copy = $this->menuService->copyToLocale($menu, $locale);

        return redirect()
            ->route('admin.menus.items.index', $copy)
            ->with('success', 'Menü bu dile kopyalandı, etiketleri çevirebilirsiniz.');
    }

    public function update(UpdateMenuRequest $request, Menu $menu): RedirectResponse
    {
        $this->authorize('update', $menu);

        $menu->update($request->validated());
        $this->menuService->clearAllCaches();

        return redirect()
            ->route('admin.menus.index')
            ->with('success', 'Menü güncellendi.');
    }
}
