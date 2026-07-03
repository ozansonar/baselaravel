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

    public function deleted(Menu $menu): void
    {
        $this->menuService->clearAllCaches();
    }

    public function restored(Menu $menu): void
    {
        $this->menuService->clearAllCaches();
    }
}
