<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMenuItemRequest;
use App\Http\Requests\Admin\UpdateMenuItemRequest;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Services\LanguageService;
use App\Services\MenuItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class MenuItemController extends Controller
{
    public function __construct(
        private readonly MenuItemService $menuItemService,
    ) {}

    public function index(Menu $menu): View
    {
        $this->authorize('viewAny', MenuItem::class);

        $menu->load(['rootItems' => function ($query) {
            $query->with('children');
        }]);

        return view('admin.menus.items', [
            'menu'             => $menu,
            'menuLanguage'     => app(LanguageService::class)->findByCode($menu->locale),
            'availableRoutes'  => $this->menuItemService->getAvailableRoutes(),
            // Only pages in the menu's own language, so a link never points at
            // another language's page.
            'pages'            => Page::query()
                ->where('locale', $menu->locale)
                ->orderBy('title')
                ->get(['id', 'title', 'slug']),
        ]);
    }

    public function store(StoreMenuItemRequest $request, Menu $menu): RedirectResponse
    {
        $this->authorize('create', MenuItem::class);

        $data = $request->validated();
        $data['menu_id'] = $menu->id;

        $this->menuItemService->create($data);

        return redirect()
            ->route('admin.menus.items.index', $menu)
            ->with('success', 'Menü öğesi eklendi.');
    }

    public function update(UpdateMenuItemRequest $request, MenuItem $item): RedirectResponse
    {
        $this->authorize('update', $item);

        $this->menuItemService->update($item, $request->validated());

        return redirect()
            ->route('admin.menus.items.index', $item->menu_id)
            ->with('success', 'Menü öğesi güncellendi.');
    }

    public function destroy(MenuItem $item): RedirectResponse
    {
        $this->authorize('delete', $item);

        $menuId = $item->menu_id;

        $this->menuItemService->delete($item);

        return redirect()
            ->route('admin.menus.items.index', $menuId)
            ->with('success', 'Menü öğesi silindi.');
    }

    public function restore(int $item): RedirectResponse
    {
        $menuItem = MenuItem::withTrashed()->findOrFail($item);

        $this->authorize('restore', $menuItem);

        $this->menuItemService->restore($item);

        return redirect()
            ->route('admin.menus.items.index', $menuItem->menu_id)
            ->with('success', 'Menü öğesi geri yüklendi.');
    }

    public function reorder(Request $request, Menu $menu): JsonResponse
    {
        $this->authorize('reorder', MenuItem::class);

        $tree = $request->input('tree', []);

        if (! is_array($tree)) {
            return response()->json(['success' => false, 'message' => 'Geçersiz veri.'], 422);
        }

        $this->menuItemService->reorder($menu->id, $tree);

        return response()->json(['success' => true]);
    }
}
