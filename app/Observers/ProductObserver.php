<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Services\Google\IndexingNotifier;
use App\Services\RedirectService;
use App\Services\UploadService;

final class ProductObserver
{
    public function __construct(
        private readonly UploadService $uploadService,
        private readonly RedirectService $redirectService,
        private readonly IndexingNotifier $indexingNotifier,
    ) {}

    public function saved(Product $product): void
    {
        if ($product->status !== ProductStatus::Active) {
            return;
        }

        if ($product->wasRecentlyCreated || $product->wasChanged(['name', 'slug', 'description', 'meta_title', 'meta_description', 'status'])) {
            $this->indexingNotifier->notifyUpdate(
                url('/urun/' . $product->slug),
                $product,
            );
        }
    }

    public function updating(Product $product): void
    {
        if ($product->isDirty('slug')) {
            $oldSlug = $product->getOriginal('slug');
            $this->redirectService->createAutoRedirect(
                '/urun/' . $oldSlug,
                '/urun/' . $product->slug,
            );

            $this->indexingNotifier->notifyDelete(
                url('/urun/' . $oldSlug),
                $product,
            );
        }
    }

    public function deleting(Product $product): void
    {
        $this->indexingNotifier->notifyDelete(
            url('/urun/' . $product->slug),
            $product,
        );

        if ($product->isForceDeleting()) {
            foreach ($product->images as $image) {
                $this->uploadService->deleteImage($image->image);
                $image->delete();
            }

            if ($product->image) {
                $this->uploadService->deleteImage($product->image);
            }

            return;
        }

        $this->redirectService->createAutoRedirect(
            '/urun/' . $product->slug,
            '/urunler',
        );
    }
}
