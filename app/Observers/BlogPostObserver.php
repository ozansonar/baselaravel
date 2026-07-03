<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\BlogPost;
use App\Services\Google\IndexingNotifier;
use App\Services\RedirectService;

final class BlogPostObserver
{
    public function __construct(
        private readonly RedirectService $redirectService,
        private readonly IndexingNotifier $indexingNotifier,
    ) {}

    public function saved(BlogPost $blogPost): void
    {
        if (! $blogPost->is_published) {
            return;
        }

        if ($blogPost->wasRecentlyCreated || $blogPost->wasChanged(['title', 'slug', 'content', 'meta_title', 'meta_description', 'is_published'])) {
            $categorySlug = $blogPost->category?->slug ?? 'genel';
            $this->indexingNotifier->notifyUpdate(
                url('/blog/' . $categorySlug . '/' . $blogPost->slug),
                $blogPost,
            );
        }
    }

    public function updating(BlogPost $blogPost): void
    {
        if ($blogPost->isDirty('slug')) {
            $oldSlug = $blogPost->getOriginal('slug');
            $categorySlug = $blogPost->category?->slug ?? 'genel';
            $this->redirectService->createAutoRedirect(
                '/blog/' . $categorySlug . '/' . $oldSlug,
                '/blog/' . $categorySlug . '/' . $blogPost->slug,
            );

            $this->indexingNotifier->notifyDelete(
                url('/blog/' . $categorySlug . '/' . $oldSlug),
                $blogPost,
            );
        }
    }

    public function deleting(BlogPost $blogPost): void
    {
        $categorySlug = $blogPost->category?->slug ?? 'genel';

        $this->indexingNotifier->notifyDelete(
            url('/blog/' . $categorySlug . '/' . $blogPost->slug),
            $blogPost,
        );

        if (!$blogPost->isForceDeleting()) {
            $this->redirectService->createAutoRedirect(
                '/blog/' . $categorySlug . '/' . $blogPost->slug,
                '/blog',
            );
        }
    }
}
