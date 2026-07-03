<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Page;
use App\Services\Google\IndexingNotifier;
use App\Services\RedirectService;

final class PageObserver
{
    public function __construct(
        private readonly RedirectService $redirectService,
        private readonly IndexingNotifier $indexingNotifier,
    ) {}

    public function saved(Page $page): void
    {
        if (! $page->isPublished()) {
            return;
        }

        if ($page->wasRecentlyCreated || $page->wasChanged(['title', 'slug', 'content', 'meta_title', 'meta_description', 'status'])) {
            $this->indexingNotifier->notifyUpdate(
                url('/' . $page->slug),
                $page,
            );
        }
    }

    public function updating(Page $page): void
    {
        if ($page->isDirty('slug')) {
            $oldSlug = $page->getOriginal('slug');
            $this->redirectService->createAutoRedirect(
                '/' . $oldSlug,
                '/' . $page->slug,
            );

            $this->indexingNotifier->notifyDelete(
                url('/' . $oldSlug),
                $page,
            );
        }
    }

    public function deleting(Page $page): void
    {
        $this->indexingNotifier->notifyDelete(
            url('/' . $page->slug),
            $page,
        );

        if (!$page->isForceDeleting()) {
            $this->redirectService->createAutoRedirect(
                '/' . $page->slug,
                '/',
            );
        }
    }
}
