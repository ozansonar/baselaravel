<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\MenuItem;
use App\Services\MenuService;

final class MenuItemObserver
{
    public function __construct(
        private readonly MenuService $menuService,
    ) {}

    public function saved(MenuItem $item): void
    {
        $this->menuService->clearAllCaches();
    }

    public function deleted(MenuItem $item): void
    {
        if ($item->isForceDeleting()) {
            $item->children()->withTrashed()->each(fn (MenuItem $child) => $child->forceDelete());
        }

        $this->menuService->clearAllCaches();
    }

    public function restored(MenuItem $item): void
    {
        $this->menuService->clearAllCaches();
    }
}
