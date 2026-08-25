<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\BlogPost;
use App\Services\RedirectService;

final class BlogPostObserver
{
    public function __construct(
        private readonly RedirectService $redirectService,
    ) {}

    public function updating(BlogPost $blogPost): void
    {
        if ($blogPost->isDirty('slug')) {
            $oldSlug = $blogPost->getOriginal('slug');
            $categorySlug = $blogPost->category?->slug ?? 'genel';
            $this->redirectService->createAutoRedirect(
                '/blog/' . $categorySlug . '/' . $oldSlug,
                '/blog/' . $categorySlug . '/' . $blogPost->slug,
            );
        }
    }

    public function deleting(BlogPost $blogPost): void
    {
        if (!$blogPost->isForceDeleting()) {
            $categorySlug = $blogPost->category?->slug ?? 'genel';
            $this->redirectService->createAutoRedirect(
                '/blog/' . $categorySlug . '/' . $blogPost->slug,
                '/blog',
            );
        }
    }
}
