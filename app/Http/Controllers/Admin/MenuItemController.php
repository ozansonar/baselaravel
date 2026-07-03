<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMenuItemRequest;
use App\Http\Requests\Admin\UpdateMenuItemRequest;
use App\Models\Menu;
use App\Models\MenuItem;
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
        $menu->load(['rootItems' => function ($query) {
            $query->with('children');
        }]);

        return view('admin.menus.items', [
            'menu'             => $menu,
            'availableRoutes'  => $this->menuItemService->getAvailableRoutes(),
            'pages'            => \App\Models\Page::orderBy('title')->get(['id', 'title', 'slug']),
        ]);
    }

    public function store(StoreMenuItemRequest $request, Menu $menu): RedirectResponse
    {
        $data = $request->validated();
        $data['menu_id'] = $menu->id;

        $this->menuItemService->create($data);

        return redirect()
            ->route('admin.menus.items.index', $menu)
            ->with('success', 'Menü öğesi eklendi.');
    }

    public function update(UpdateMenuItemRequest $request, MenuItem $item): RedirectResponse
    {
        $this->menuItemService->update($item, $request->validated());

        return redirect()
            ->route('admin.menus.items.index', $item->menu_id)
            ->with('success', 'Menü öğesi güncellendi.');
    }

    public function destroy(MenuItem $item): RedirectResponse
    {
        $menuId = $item->menu_id;

        $this->menuItemService->delete($item);

        return redirect()
            ->route('admin.menus.items.index', $menuId)
            ->with('success', 'Menü öğesi silindi.');
    }

    public function restore(int $item): RedirectResponse
    {
        $menuItem = MenuItem::withTrashed()->findOrFail($item);

        $this->menuItemService->restore($item);

        return redirect()
            ->route('admin.menus.items.index', $menuItem->menu_id)
            ->with('success', 'Menü öğesi geri yüklendi.');
    }

    public function reorder(Request $request, Menu $menu): JsonResponse
    {
        $tree = $request->input('tree', []);

        if (! is_array($tree)) {
            return response()->json(['success' => false, 'message' => 'Geçersiz veri.'], 422);
        }

        $this->menuItemService->reorder($menu->id, $tree);

        return response()->json(['success' => true]);
    }
}
