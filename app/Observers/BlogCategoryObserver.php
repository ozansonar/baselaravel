<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\BlogCategory;
use App\Services\RedirectService;

final class BlogCategoryObserver
{
    public function __construct(
        private readonly RedirectService $redirectService,
    ) {}

    public function updating(BlogCategory $blogCategory): void
    {
        if ($blogCategory->isDirty('slug')) {
            $oldSlug = $blogCategory->getOriginal('slug');
            $this->redirectService->createAutoRedirect(
                '/blog/' . $oldSlug,
                '/blog/' . $blogCategory->slug,
            );
        }
    }

    public function deleting(BlogCategory $blogCategory): void
    {
        if (!$blogCategory->isForceDeleting()) {
            $this->redirectService->createAutoRedirect(
                '/blog/' . $blogCategory->slug,
                '/blog',
            );
        }
    }
}
