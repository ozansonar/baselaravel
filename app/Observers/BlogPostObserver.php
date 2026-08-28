<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\BlogPostFile;
use App\Services\BlogPostFileService;
use App\Services\RedirectService;

final class BlogPostObserver
{
    public function __construct(
        private readonly RedirectService $redirectService,
        private readonly BlogPostFileService $files,
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

    /**
     * Cascade lives here instead of on the foreign key so a soft deleted post
     * hides its comments too, and restoring brings them back.
     */
    public function deleting(BlogPost $blogPost): void
    {
        if ($blogPost->isForceDeleting()) {
            $blogPost->comments()->withTrashed()->each(fn (BlogComment $comment) => $comment->forceDelete());
            // Ekler diskten de gidiyor: satır kalkıp dosya kalsaydı
            // public/uploads altında sahipsiz birikirdi. Yabancı anahtar da
            // silmeyi engellerdi, önce ekler temizlenmeli.
            $blogPost->files()->withTrashed()->each(fn (BlogPostFile $file) => $this->files->delete($file));

            return;
        }

        $categorySlug = $blogPost->category?->slug ?? 'genel';
        $this->redirectService->createAutoRedirect(
            '/blog/' . $categorySlug . '/' . $blogPost->slug,
            '/blog',
        );

        $blogPost->comments()->each(fn (BlogComment $comment) => $comment->delete());
        $blogPost->files()->each(fn (BlogPostFile $file) => $file->delete());
    }

    public function restoring(BlogPost $blogPost): void
    {
        $blogPost->comments()->onlyTrashed()->each(fn (BlogComment $comment) => $comment->restore());
        $blogPost->files()->onlyTrashed()->each(fn (BlogPostFile $file) => $file->restore());
    }
}
