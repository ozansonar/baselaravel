<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateMenuRequest;
use App\Models\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class MenuController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Menu::class);

        $menus = Menu::withCount('items')->orderBy('location')->get();

        $stats = [
            'total'      => $menus->count(),
            'active'     => $menus->where('is_active', true)->count(),
            'total_items'=> (int) $menus->sum('items_count'),
            'locations'  => $menus->pluck('location')->unique()->count(),
        ];

        return view('admin.menus.index', compact('menus', 'stats'));
    }

    public function update(UpdateMenuRequest $request, Menu $menu): RedirectResponse
    {
        $this->authorize('update', $menu);

        $menu->update($request->validated());

        return redirect()
            ->route('admin.menus.index')
            ->with('success', 'Menü güncellendi.');
    }
}
