<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Page;
use App\Services\RedirectService;

final class PageObserver
{
    public function __construct(
        private readonly RedirectService $redirectService,
    ) {}

    public function updating(Page $page): void
    {
        if ($page->isDirty('slug')) {
            $oldSlug = $page->getOriginal('slug');
            $this->redirectService->createAutoRedirect(
                '/' . $oldSlug,
                '/' . $page->slug,
            );
        }
    }

    public function deleting(Page $page): void
    {
        if (!$page->isForceDeleting()) {
            $this->redirectService->createAutoRedirect(
                '/' . $page->slug,
                '/',
            );
        }
    }
}
