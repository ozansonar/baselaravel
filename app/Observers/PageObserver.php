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

    /**
     * Ekler yabancı anahtarla değil burada zincirleniyor: yumuşak silinen sayfa
     * eklerini de gizlemeli, geri alındığında birlikte dönmeli.
     */
    public function deleting(Page $page): void
    {
        if ($page->isForceDeleting()) {
            // Ekler diskten de gidiyor: satır kalkıp dosya kalsaydı
            // public/uploads altında sahipsiz birikirdi.
            $page->purgeFiles();

            return;
        }

        $this->redirectService->createAutoRedirect(
            '/' . $page->slug,
            '/',
        );

        $page->softDeleteFiles();
    }

    public function restoring(Page $page): void
    {
        $page->restoreFiles();
    }
}
