<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Menu;
use App\Services\MenuService;

final class MenuObserver
{
    public function __construct(
        private readonly MenuService $menuService,
    ) {}

    public function saved(Menu $menu): void
    {
        $this->menuService->clearAllCaches();
    }

    /**
     * Cascade is handled here rather than by a foreign key so that a soft
     * deleted menu takes its items with it and restoring brings them back.
     * Items are deleted one by one so MenuItemObserver still runs.
     */
    public function deleting(Menu $menu): void
    {
        if ($menu->isForceDeleting()) {
            $menu->items()->withTrashed()->each(fn (\App\Models\MenuItem $item) => $item->forceDelete());

            return;
        }

        $menu->items()->each(fn (\App\Models\MenuItem $item) => $item->delete());
    }

    public function restoring(Menu $menu): void
    {
        $menu->items()->onlyTrashed()->each(fn (\App\Models\MenuItem $item) => $item->restore());
    }

    public function deleted(Menu $menu): void
    {
        $this->menuService->clearAllCaches();
    }

    public function restored(Menu $menu): void
    {
        $this->menuService->clearAllCaches();
    }
}
