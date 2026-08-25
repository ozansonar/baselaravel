<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\BlogCategory;
use App\Models\BlogPost;
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

    /**
     * Cascade lives here instead of on the foreign key so a soft deleted
     * category takes its posts with it and restoring brings them back.
     * Posts go one by one so BlogPostObserver still cascades to comments.
     */
    public function deleting(BlogCategory $blogCategory): void
    {
        if ($blogCategory->isForceDeleting()) {
            $blogCategory->posts()->withTrashed()->each(fn (BlogPost $post) => $post->forceDelete());

            return;
        }

        $this->redirectService->createAutoRedirect(
            '/blog/' . $blogCategory->slug,
            '/blog',
        );

        $blogCategory->posts()->each(fn (BlogPost $post) => $post->delete());
    }

    public function restoring(BlogCategory $blogCategory): void
    {
        $blogCategory->posts()->onlyTrashed()->each(fn (BlogPost $post) => $post->restore());
    }
}
