<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Category;
use App\Services\RedirectService;

final class CategoryObserver
{
    public function __construct(
        private readonly RedirectService $redirectService,
    ) {}

    public function updating(Category $category): void
    {
        if ($category->isDirty('slug')) {
            $oldSlug = $category->getOriginal('slug');
            $this->redirectService->createAutoRedirect(
                '/urunler/' . $oldSlug,
                '/urunler/' . $category->slug,
            );
        }
    }

    public function deleting(Category $category): void
    {
        if ($category->isForceDeleting()) {
            $category->children()->withTrashed()->each(fn ($child) => $child->forceDelete());
            return;
        }

        $this->redirectService->createAutoRedirect(
            '/urunler/' . $category->slug,
            '/urunler',
        );

        $category->children()->each(fn ($child) => $child->delete());
    }

    public function restoring(Category $category): void
    {
        $category->children()->withTrashed()
            ->where('deleted_at', '>=', $category->deleted_at)
            ->each(fn ($child) => $child->restore());
    }
}
