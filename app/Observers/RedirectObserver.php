<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Redirect;
use App\Services\RedirectService;

final class RedirectObserver
{
    public function __construct(
        private readonly RedirectService $redirectService,
    ) {}

    public function saved(Redirect $redirect): void
    {
        $this->redirectService->clearCache();
    }

    public function deleted(Redirect $redirect): void
    {
        $this->redirectService->clearCache();
    }

    public function restored(Redirect $redirect): void
    {
        $this->redirectService->clearCache();
    }
}
